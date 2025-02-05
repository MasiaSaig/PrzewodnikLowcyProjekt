<?php
/**
 * @file bestiary.php
 * @brief File where user can see data of all unique animals and creatures that can be hunted down in quests. Together with its race data. 
 */

$loggedInAsQuestMaker = false;
try{
  $loggedInAsQuestMaker = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}


$createAnimalError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['createAnimal'])){
    $animal_name = $_POST['animalName'];
    $id_race = $_POST['animalRace'];
    $animal_race = validateInput($_POST['animalRace']);
    $animal_description = validateInput($_POST['animalDescription']);
    
    $createAnimalError = createBeast($animal_name, $animal_race, $animal_description);
  }
}

/**
 * Function creates beast with provided, data.
 * 
 * Function checks for existing race id and if name of a beast is unique. If all criteria are met, function creates new beast.
 * 
 * @param[in] $name Name of a beast.
 * @param[in] $id_race Race's ID, of a beast.
 * @param[in] $description Description of a beast.
 * 
 * @return Returns empty string on success, else return error message.
 */
function createBeast($name, $id_race, $description){
  global $pdo;
  try{
    if(empty($name))
      throw new Exception("Imię zwierzęcia nie może być puste.");  
    try{
      $checkRaceId = $pdo->prepare("SELECT (id=?) FROM prj.rasa WHERE id=?");
      $checkRaceId->bindParam(1, $id_race, PDO::PARAM_INT);
      $checkRaceId->bindParam(2, $id_race, PDO::PARAM_INT);
      $checkRaceId->execute();
    }catch(PDOException $e){
      throw new Exception("Nie się otrzymać rasy.");
    }
    if($checkRaceId->fetchColumn() == 0){
      throw new Exception("Podana rasa nie istnieje.");
    }

    try{
      $checkName = $pdo->prepare("SELECT (nazwa=:name) FROM prj.zwierzę WHERE nazwa=:name");
      $checkName->execute(['name'=>$name]);
    }catch(PDOException $e){
      throw new Exception("Nie udało się zweryfikować nazwy.");
    }
    if($checkName->fetchColumn() == 1){
      throw new Exception("Już istnieje zwierzę z podaną nazwą.");
    }

    try{
      $addAnimal = $pdo->prepare("INSERT INTO prj.zwierzę (id, id_rasa, opis, nazwa) VALUES(DEFAULT, ?, ?, ?)");
      $addAnimal->bindParam(1, $id_race, PDO::PARAM_INT);
      $addAnimal->bindParam(2, $description, PDO::PARAM_STR);
      $addAnimal->bindParam(3, $name, PDO::PARAM_STR);
      $addAnimal->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się stworzyć zwierzęcia.");
    }
  }catch(Exception $e){
    return $e->getMessage() . "<br>";
  }
  return "";
}

if($loggedInAsQuestMaker == true){ ?>
<br>
<h2>Dodaj nowe zwierzę</h2>
<form class="centered-form" method="POST" action="<?php htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
  <p>Nazwa<sup>*</sup></p>
  <input type="text" name="animalName"><br>
  <p>Rasa</p>
  <select name="animalRace">
  <?php 
    try{
    // wypisanie wszystkich ras, jako opcje w elemencie <select>
      $racesQuery = $pdo->query("SELECT id, nazwa FROM prj.rasa;");
      $racesQuery->execute();
      foreach($racesQuery as $race){
        echo "<option value=\"".$race['id']."\">".$race['nazwa']."</option>";
      }
    }catch(PDOException $e){
      $sqlError = $sqlError . $e->getMessage() . " | ";
    }
  ?>
  </select>
  <p>Opis zwierzęcia</p>
  <textarea name="animalDescription"></textarea>
  <input id="createAnimalButton" class="form-button" type="submit" name="createAnimal" value="Stworz nowe zwierzę">
</form>
<br>

<?php } echo "<p class=\"error-text\">".$createAnimalError."</p>"; ?>

<h1>Bestiariusz</h1>
<table>
<tr>
  <th>ID</th>
  <th>Nazwa</th>
  <th>Rasa</th>
</tr>

<?php

try{
  // wypisanie wszystkich zwierząt do tabeli z bazy danych, tj. ich: id, rasy, nazwy
  $bestiaryQuery = $pdo->query("SELECT id, nazwa_rasy, nazwa FROM zwierze_rasa");
  $bestiaryQuery->execute();
  if($bestiaryQuery == false)
    $error = $error . "<br> Nie można otrzymać danych bestiariusza.";
  foreach($bestiaryQuery as $beast){
    echo "<tr><td>".$beast['id']."</td>";
    echo "<td>";
    if($loggedInAsQuestMaker){
      echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_beast=".$beast['id']."\">".$beast['nazwa']."</a>";
    }else{
      echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_beast=".$beast['id']."\">".$beast['nazwa']."</a>";
    }
    echo "</td>";
    echo "<td>".$beast['nazwa_rasy']."</td>";
    echo "</tr>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}
?>

</table>