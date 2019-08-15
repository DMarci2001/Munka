<?php



echo displayFejlec();



echo "A regisztráció érvényesítése sikerült.</b><br/>
<br/>

<a href='/'>Tovább</a>

";


if (isset($_SESSION["captcha"])) unset($_SESSION["captcha"]);

//sendVisszaIgazolas(2);



?>