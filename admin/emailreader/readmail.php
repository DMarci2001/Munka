<?php

error_reporting(E_ALL);

require('../../config.php');
require('mime_parser.php');
require('rfc822_addresses.php');
require("pop3.php");


stream_wrapper_register('pop3','pop3_stream');  /* Register the pop3 stream handler class */

$pop3=new pop3_class;
$pop3->hostname="pizza.hu";					     /* POP 3 server host name                      */
$pop3->port=110;                         /* POP 3 server host port,
                                            usually 110 but some servers use other ports
                                            Gmail uses 995                              */
$pop3->tls=0;                            /* Establish secure connections using TLS      */
$puser="pizza@pizza.hu";                 /* Authentication user name                    */
$password="P322inf";										 /* Authentication password                     */
$pop3->realm="";                         /* Authentication realm or domain              */
$pop3->workstation="";                   /* Workstation for NTLM authentication         */
$apop=0;                                 /* Use APOP authentication                     */
$pop3->authentication_mechanism="USER";  /* SASL authentication mechanism               */
$pop3->debug=0;                          /* Output debug information                    */
$pop3->html_debug=1;                     /* Debug information is in HTML                */
$pop3->join_continuation_header_lines=1; /* Concatenate headers split in multiple lines */




//echo getTajFromString($subject);
//die();



if (!$pop3->Open()=="") {
	die("POP3 Connect error!");
} else {
	if (!$pop3->Login($puser,$password,$apop)=="") {
		die("POP3 login error!");
	} else {
		$pop3->Statistics($messages,$size);
		$pop3->GetConnectionName($connection_name);
		for ($message=1;$message<=$messages;$message++) {
			$message_file='pop3://'.$connection_name.'/'.$message;
			$mime=new mime_parser_class;
			$mime->decode_bodies=1;
			$parameters=array('File'=>$message_file,);
			$success=$mime->Decode($parameters,$decoded);
			if ($success) {
				$messageid=$decoded[0]["Headers"]["message-id:"];
				echo $messageid."\n";
				ob_flush();
				//print_r($decoded[0]);
				//die();

				if (!$rowm=sql_fetch_array(sql_query("select azo,datum from email where messageid=?",array($messageid)))) {			
					//var_dump($decoded[0]);
					//die();
					if($mime->Analyze($decoded[0], $results)) {
						if ($results["From"][0]["name"]=="") $results["From"][0]["name"]=$results["From"][0]["address"];
						if ($results["From"][0]["name"]=="") continue;
						if ($results["Subject"]=="") $results["Subject"]="Nincs tárgy";
						if (strtolower($results["Encoding"])!="utf-8") {
							$results["Subject"]=iconv("iso-8859-2","utf-8",$results["Subject"]);
							$results["From"][0]["name"]=iconv("iso-8859-2","utf-8",$results["From"][0]["name"]);
							$results["Data"]=iconv("iso-8859-2","utf-8",$results["Data"]);
						}

						sql_query("insert into email set
						datum=now(),
						messageid='".addslashes($messageid)."',
						tipus='be',
						type='".addslashes($results["Type"])."',
						encoding='".addslashes($results["Encoding"])."',
						maildate='".addslashes($results["Date"])."',
						erkezett='".date("Y-m-d H:i:s",strtotime($results["Date"]))."',
						frommail='".addslashes($results["From"][0]["address"])."',
						fromname='".addslashes($results["From"][0]["name"])."',
						tomail='".addslashes($results["To"][0]["address"])."',
						toname='".addslashes($results["To"][0]["name"])."',
						subject='".addslashes($results["Subject"])."',
						body='".addslashes($results["Data"])."'");
						$id=sql_insert_id();
						
						for ($i=0;$i<count($results["Attachments"]);$i++) {
							sql_query("insert into emailattachments set
							eid='{$id}',
							tipus='".addslashes($results["Attachments"][$i]["Type"])."',
							filename='".addslashes($results["Attachments"][$i]["FileName"])."',
							description='".addslashes($results["Attachments"][$i]["Description"])."',
							disposition='".addslashes($results["Attachments"][$i]["FileDisposition"])."'");
							$aid=sql_insert_id();
							file_put_contents("/var/www/emailattachments/attachment{$aid}.bin",$results["Attachments"][$i]["Data"]);
							
							addAttachmentToPaciens($aid);
							
							//var_dump($results["Attachments"][$i]);
							while (list($key, $val) = each($results["Attachments"][$i])) {
 							   echo "key:{$key}";
 							   if ($key<>"Data") echo $val;
 							   echo "\r\n";
							}
						}

						
						
						//echo $results["From"][0]["address"]." ".count($results["Attachments"])."\r\n";
						
						//echo '<pre>';
						//var_dump($results);
						//die();
						//echo "</pre>";
					}
				} else {
					//break;
				}
			}
		}
	}
}




die("readmail done\r\n");

?>