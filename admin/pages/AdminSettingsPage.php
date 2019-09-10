<?php

class AdminSettingsPage extends AdminCorePage {


    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["settingsmentes"])) {
            $szunnapok = str_replace(".","-",$_POST["szunnapok"]);
            sql_query("update settings set szunnapok=?", array($szunnapok));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_REQUEST['uploadfiles'])) {
            $smartFile 	  = array();
            $missingUsers = array();
            $successFind  = array();
            $z = 0;
            $t = 0;
            foreach ($_FILES['FileUpload']['name'] as $key => $file) {
                //Ha a fájl neve tartalmazza a "!" karaktert hagyja ki a vizsgálatból:
                if (strpos($file, "!") === false) {
                    $infoString  = explode( "_", $file );
                    $type 		 = explode( ".", $file );
                    $szuldatum   = explode( ".", $infoString[3] );
                    $smartFile[] = array( "filename" => $infoString[0],
                        "taj" => $infoString[2],
                        "szuldatum" => $szuldatum[0],
                        "release" => $infoString[1],
                        "type" => $type[1],
                        "TEMP" => $_FILES['FileUpload']['tmp_name'][$key],
                        "size" => $_FILES['FileUpload']['size'][$key],
                        "origin" => $file );
                }
                else continue;
            }
            $f = 0;
            $z = 0;
            for ($i = 0; $i < count( $smartFile ); $i++) {
                if (in_array( $smartFile[$i]['type'], array( "pdf", "doc", "xls", "docx", "xlsx"))) {
                    if( $result = sql_fetch_array( sql_query( "SELECT felh.*,c.megnev FROM felhasznalok felh
													   LEFT JOIN cegek c ON c.id = felh.cegid
													   WHERE taj = ? AND szuldatum = ?", array( $smartFile[$i]['taj'], $smartFile[$i]['szuldatum'] )))) {
                        $successFind[] = array (       "taj" => $smartFile[$i]['taj'],
                            "szuldatum" => $smartFile[$i]['szuldatum'],
                            "file" => $smartFile[$i]['origin'],
                            "nev" => $result['nev'],
                            "ceg" => $result['megnev']);
                        sql_query("INSERT INTO dokumentumok SET 
						   beutaloid = 0, userid =".$result['id'].", 
						   megnev    = '".addslashes($smartFile[$i]["filename"])."',
						   filename  = '".addslashes($smartFile[$i]["filename"]).".pdf',
						   size      = '".addslashes($smartFile[$i]['size'])."',
						   tipus     = '{$smartFile[$i]['type']}',
						   datum     = now(),
						   kod       = SHA1(MD5(CONCAT(NOW(),RAND()*20000)))");
                        $id = sql_insert_id();
                        $destination = getDocPath( $id );
                        if( move_uploaded_file( $smartFile[$i]["TEMP"], $destination )){

                        } else {
                            echo "Nem sikerült a feltöltés! ({$smartFile[$i]['origin']})<br/>";
                        }
                    } else {
                        $z++;
                        $missingUsers[] = array (       "taj" => $smartFile[$i]['taj'],
                            "szuldatum" => $smartFile[$i]['szuldatum'],
                            "file" => $smartFile[$i]['origin'] );
                    }
                } else {
                    echo "Hibás formátum!({$smartFile[$i]["filename"]})<br/>";
                }
            }

            $k     = 0;
            $exist = "({$f})Sikeres feltöltési próbálkozások:<br/>";
            if (!empty($successFind)) {
                foreach ($successFind as $success) {
                    $k++;
                    $exist.= "<p>[{$k}][Sikeres][{$success['nev']}][ {$success['ceg']} ]-[ {$success['file']} ]</p>";
                }
            } else {
                $exist = "";
            }

            $n        = 0;
            $nonExist =  "({$z})Sikertelen feltöltési próbálkozások:<br/>";
            if (!empty($missingUsers)) {
                foreach ($missingUsers as $miss) {
                    $n++;
                    $nonExist.= "<p>[{$n}][Sikertelen]=>[ {$miss['taj']} ]=>[ {$miss['szuldatum']} ]=>[ {$miss['file']} ]</p>";
                }
            } else {
                $nonExist = "";
            }
            $output = $exist.$nonExist;
            echo $output;
        }
    }

    public function showPage() {
        echo "<div style='margin-bottom:20px;'>";
        echo "<a href='index.php?page=langsettings'>Többnyelvű szövegek beállítása</a>";
        echo "</div>";

        $row = sql_fetch_array(sql_query("select * from settings"));

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<div>Munkaszüneti napok: </div>";
        echo "<div style='font-size:11px;'>(dátumok vesszővel elválasztva)</div>";
        echo "<div><textarea name='szunnapok' style=' box-sizing: border-box;width:100%;height:100px;'>{$row["szunnapok"]}</textarea></div>";

        echo "<div style='margin-top:10px;'><input type='submit' name='settingsmentes' value='Mentés'></div>";
        echo "</form>";

        echo "<div style='margin-top:20px'>Páciens dokumentumok feltöltése (MAX 300db):</div>";
        echo "<div><form method='POST' enctype='multipart/form-data' name='patient_docs' id = 'massDocs'>";
        echo "<input type='file' name='FileUpload[]' webkitdirectory mozdirectory msdirectory odirectory directory multiple />";
        echo "<div style = 'margin-top:10px'><input type = 'submit' onClick = 'massUpload();return false' name='uploadfiles' value='Feltöltés'></div>";
        echo "<div style = 'font-size:11px;color:gray'>a fájl név formátum pedig a következő legyen :fájlnév_tajszám_ÉÉÉÉ-HH-NN</div>";
        echo "</form></div>";
        echo "<div id = 'upload-result'></div>";
    }

}

