<?php
/**
 * @file database.php
 * @brief This file connects to database and provides a few functions for form validation and checking if user is already logged in.
 */

$dbHost = "pascal.fis.agh.edu.pl";
$dbPort = "5432";
$dbName = "u2mueller";
$dbUser = "u2mueller";
$dbPassword = "2mueller";
$dbDsn = "pgsql:host=$dbHost;dbname=$dbName;user=$dbUser;port=$dbPort;password=$dbPassword;";

try{
	if(!session_id()) session_start(); 
	$pdo = new PDO($dbDsn);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//echo "Connected";
}catch(PDOException $e){
	echo "<p>Connection failed: " . $e->getMessage() . "</p>";
}


/**
 * Function checking if user, is logged in as Hunter.
 *
 * Function checks, if locally saved cookie authLoginToken, exists in database prj.łowca.
 *
 * @return If user is logged in as Hunter, return his ID, else return false (zero).
 */
function loggedIn(){
	// check cookie if user is logged in
	global $pdo;
	$loggedInCheck = $pdo->prepare("SELECT id, (token_autoryzacji = :authLoginCookie) as token_match FROM prj.łowca WHERE token_autoryzacji=:authLoginCookie");
  $loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginToken']]);
  $data = $loggedInCheck->fetch();
	if($data['token_match'] == 1){
		// user already logged in
		return $data['id'];
	}// else could not redirect, possibly wrong cookie loginToken, or user is just not logged in.
	return false;
}

/**
 * Function checking if user, is logged in as Quest Maker.
 *
 * Function checks, if locally saved cookie authLoginQuestMakerToken exists in database prj.zleceniodawca.
 *
 * @return If user is logged in as Quest Maker, return his ID, else return false (zero).
 */
function loggedInQuestMaker(){
  global $pdo;
  try{
    $loggedInCheck = $pdo->prepare("SELECT id, (token_autoryzacji = :authLoginCookie) as token_match FROM prj.zleceniodawca WHERE token_autoryzacji=:authLoginCookie");
  	$loggedInCheck->execute(['authLoginCookie' => $_COOKIE['authLoginQuestMakerToken']]);
    $data = $loggedInCheck->fetch();
  }catch(PDOException $e){
    return 0;
  }
	if($data['token_match'] == 1){
		// questMaker already logged in
		return $data['id'];
  }
  return 0;
}

/**
 * Function checking if hunter has any active quest.
 *
 * @param[in] id_hunter ID of a hunter, to check.
 *
 * @return Return true (1) if hunter has active quest, else return false (0).
 */
function checkForActiveQuest($id_hunter){
  global $pdo;
  $checkForActiveQuest = $pdo->prepare("SELECT COUNT(pz.id_zlecenie) FROM prj.przypisane_zlecenie pz JOIN prj.zlecenie z ON z.id=pz.id_zlecenie WHERE status='w trakcie' AND id_łowca=?");
  $checkForActiveQuest->bindParam(1, $id_hunter, PDO::PARAM_INT);
  $checkForActiveQuest->execute();
  return $checkForActiveQuest->fetchColumn();
}

function validateInput($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}
?>
