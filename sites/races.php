<?php
/**
 * @file races.php
 * @brief File where user can see data of a all races that are available in database. Both hunters and creatures races. 
 */

$loggedInAsQuestMaker = 0;
try{
  $loggedInAsQuestMaker = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

$createRaceError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['createRace'])){
    $race_name = validateInput($_POST['raceName']);
    $race_description = validateInput($_POST['raceDescription']);
    if($_POST['hunterRace'] == 1){
      $hunter_race = true;
    }else{
      $hunter_race = false;
    }
    
    $createRaceError = createNewRace($race_name, $hunter_race, $race_description);
  }
}

/**
 * Function that creates new rase.
 * 
 * Function checks for unique name and then if name was in fact unique, it creates new race.
 * 
 * @param[in] $race_name Name of a new race.
 * @param[in] $description Description of a new race.
 * 
 * @return Returns empty string on success, else return error message.
 */
function createNewRace($race_name, $hunter_race, $description){
  global $pdo;
  try{
    if(empty($race_name)){
      throw new Exception("Nazwa nie może być pusta.");
    }

    try{
      $checkName = $pdo->prepare("SELECT (nazwa=?) FROM prj.rasa WHERE nazwa=?");
      $checkName->bindParam(1, $race_name, PDO::PARAM_STR);
      $checkName->bindParam(2, $race_name, PDO::PARAM_STR);
      $checkName->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się sprawdzić nazwy rasy.");
    }
    if($checkName->fetchColumn() == 1){
      throw new Exception("Już istnieje rasa o podanje nazwie.");
    }

    try{
        $createRaceQuery = $pdo->prepare("INSERT INTO prj.rasa (id, nazwa, rasa_łowcy, opis) VALUES (DEFAULT, ?, ?, ?)");
        $createRaceQuery->bindParam(1, $race_name, PDO::PARAM_STR);
        $createRaceQuery->bindParam(2, $hunter_race, PDO::PARAM_BOOL);
        $createRaceQuery->bindParam(3, $description, PDO::PARAM_STR);  
        $createRaceQuery->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się utworzyć rasy.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

if($loggedInAsQuestMaker > 0){
?>
<h2>Stworz nową rase</h2>
<form class="centered-form" method="POST" action="<?php htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
  <p>Nazwa:</p>
  <input type="text" name="raceName">
  
  <div style="display: inline-block">
    <p style="display: inline-block; width: auto;">Czy jest to rasa rozumna:</p>&nbsp&nbsp
    <p style="display: inline-block; width: auto;">Tak</p> 
    <input style="width: auto;" type="radio" name="hunterRace" value="1" checked="checked">&nbsp&nbsp
    <p style="display: inline-block; width: auto;">Nie</p>
    <input style="width: auto;" type="radio" name="hunterRace" value="0">
  </div>
  <p>Opis:</p>
  <textarea name="raceDescription"></textarea>
  <input id="createRaceButton" class="form-button" type="submit" name="createRace" value="Stworz rasę">
</form>

<?php 
}
echo "<p class=\"error-text\">".$createRaceError."</p>";
?>
<br>
<h2>Lista Ras</h2>
<table>
<tr>
  <th>ID</th>
  <th>Nazwa</th>
  <th>Opis</th>
  <th>Rasa rozumna</th>
</tr>

<?php
try{
  // wypisanie wszystkich ras do tabeli z bazy danych, tj. ich: id, nazwy, opsiu
  $racesQuery = $pdo->query("SELECT id, nazwa, rasa_łowcy, opis FROM prj.rasa");
  $racesQuery->execute();
  if($racesQuery == false)
    $error = $error . " Nie można otrzymać ras.<br>";
  foreach($racesQuery as $race){
    echo "<tr>";
    echo "<td>".$race['id']."</td>";
    echo "<td>".$race['nazwa']."</td>";
    echo "<td>".$race['opis']."</td>";
    echo "<td style=\"text-align: center;\">";
    if($race['rasa_łowcy'] == 1)
      echo "Tak";
    else
      echo "Nie";
    echo "</td>";
    echo "</tr>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
?>

</table>
