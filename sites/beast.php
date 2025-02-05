<?php
/**
 * @file beast.php
 * @brief File where user can see data of a certain beast. Beast is taken from database given by GET variable. 
 */

if(!isset($_GET['id_beast']) || !is_numeric($_GET['id_beast'])){
  $beastErrorID = true;
}

try{
  $checkBeast = $pdo->prepare("SELECT (id=?) FROM prj.zwierzę WHERE id=?");
  $checkBeast->bindParam(1, $_GET['id_beast'], PDO::PARAM_INT);
  $checkBeast->bindParam(2, $_GET['id_beast'], PDO::PARAM_INT);
  $checkBeast->execute();
  if($checkBeast->fetchColumn() == 0){
    $beastErrorID = true;
  }
}catch(PDOException $e){
  $beastErrorID = true;
}

if($beastErrorID){
  echo "<p style=\"color: red;\">Zwierzę z podanym ID nie istnieje</p>";
  return;
}


try{
  $beast = getBeastData($_GET['id_beast']);
  
  echo "<h1>".$beast['nazwa']."</h1>";
  echo "<p>".$beast['zwierzę_opis']."</p>";
  echo "<hr>";
  echo "<p>Rasa: ".$beast['nazwa_rasy']."</p>";
  echo "<p>".$beast['rasa_opis']."</p>";
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

/**
 * Function that get beast's data.
 *
 * Function returns beast's data as associative array with informations such as: id, name, description, race, race description.
 *
 * @param[in] $id_beast ID of a beast.
 *
 * @return Returns associative array with beast's data.
 */
function getBeastData($id_beast){
  global $pdo;
  $beastQuery = $pdo->prepare("SELECT id, nazwa, zwierzę_opis, nazwa_rasy, rasa_opis FROM zwierze_rasa WHERE id=?");
  $beastQuery->bindParam(1, $_GET['id_beast'], PDO::PARAM_INT);
  $beastQuery->execute();
  return $beastQuery->fetch();
}

?>