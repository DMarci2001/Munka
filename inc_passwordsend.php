<?php

if (!isset($_POST["email"])) {
	$_POST["email"]="";
}



echo displayFejlec($webText["ujjelszokerese"]);

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

echo "<div>";
echo "<form name='iform' method='post' enctype='multipart/form-data'>";

echo "<div style='margin-top:0px;'>";
echo "{$webText["kerjukadjamegemail"]}";
echo "</div>";


echo "<table style='font-size:12px;margin-top:20px;'>";
echo "<tr><td>{$webText["email"]}:&nbsp;&nbsp;&nbsp;</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
echo "</table>";

echo "<br/><input type='submit' name='passwordsend' value='{$webText["ujjelszokerese"]}'> ";
echo "</form>";

echo "</div>";




?>