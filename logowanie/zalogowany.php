<?php
// check cookie if user is logged in
$loggedInCheck = $pdo->prepare("SELECT (token_autoryzacji = :authLoginCookie) FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken']]);
if($loggedInCheck->fetchColumn() == 1){
    // user already logged in
    echo $_COOKIE['authLoginToken'];
    return true;
}// else could not redirect, possibly wrong cookie loginToken, or user is just not logged in.
return false;
?>
