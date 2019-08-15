<?php



echo displayFejlec();



echo "{$webText["torlessikerult"]}<br/>
<br/>

<a href='/'>{$webText["visszafooldal"]}</a>

";



if (isset($_SESSION["captcha"])) unset($_SESSION["captcha"]);

//sendVisszaIgazolas(2);



?>