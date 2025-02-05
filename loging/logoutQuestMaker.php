<?php
/**
 * @file logoutQuestMaker.php
 * @brief This file is for logging out Quest Maker, if user wants to logout from currently logged-in account, he should be redirected here.
 */

// delete existing cookie for a quest maker
session_start();
setcookie("authLoginQuestMakerToken", "", time() - 3600, "/", NULL);
header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
die();
?>