<?php
setcookie('authLoginToken', "", time() - 3600); 
header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
die();
?>