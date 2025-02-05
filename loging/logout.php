<?php
/**
 * @file logout.php
 * @brief This file is for logging out hunter, if user wants to logout from currently logged-in account, he should be redirected here.
 */

// delete existing cookie for a hunter
session_start();
setcookie("authLoginToken", "", time() - 3600, "/", NULL);
header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
die();
?>