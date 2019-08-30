<?php



echo "<div style='margin-bottom:20px;'>";
echo "<a href='index.php?page=settings_lang'>Többnyelvű szövegek beállítása</a>";
echo "</div>";


$row=sql_fetch_array(sql_query("select * from settings"));

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

?>