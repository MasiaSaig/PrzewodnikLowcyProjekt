<?php 
/**
 * @file profileQuestMaker.php
 * @brief This file is a profile view of a Quest Maker.
 *
 * Most of the stuff Quest Maker may wan to access is here. He can create new quests, look at his both active and finished quests. 
 * He may cancel quests and finish them. 
 */

session_start(); 
require "database.php";

$loggedIn = false;
try{
  $loggedIn = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

if($loggedIn == false){
  header("Location: http://pascal.fis.agh.edu.pl/~2mueller/index.php");
  die();
}

$sqlError = $error = "";
try{
  $getQuestMakerData = $pdo->prepare("SELECT id, imię FROM prj.zleceniodawca WHERE token_autoryzacji=:authLoginCookie");
  $getQuestMakerData->execute(['authLoginCookie' => $_COOKIE['authLoginQuestMakerToken']]);
  $questMakerData = $getQuestMakerData->fetch();
  $id_questMaker = $questMakerData['id'];
  $username = $questMakerData['imię'];
  if($getQuestMakerData == false){
    $error = $error . "Nie można otrzymać danych zleceniodawcy.<br>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . $e->getMessage() . "<br>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['createNewQuest'])){
  // creating new Quest
    $money = round(validateInput($_POST['newPrize']), 2);
    $description = validateInput($_POST['newDescription']);
    $beastsArr = $_POST['newBeasts'];
    if($money >= 0){
       $error = createNewQuest($id_questMaker, $money, $description, $beastsArr);
    }else{
      $error = $error . "Nagroda musi być większa od zera.<br>";
    }
  }else if(isset($_POST['deleteQuest']) && is_numeric($_POST['deleteQuest'])){
  // deleting quest
    $error = deleteQuest($_POST['deleteQuest']);
  }else if(isset($_POST['finishQuest']) && is_numeric($_POST['finishQuest'])){
  // marking quest, as finished
    $error = markQuestAsFinished($_POST['finishQuest']);
  }
}

/**
 * Function that marks given quest as finished and distributes its prize.
 * 
 * Function first checks if quest with provided id exists, then checks if quests status is 'w trakcie' meaning if someone has already assigned it.
 * Next if everything is correct, prize for quest is distributed to every hunter which was assigned to that quest. If a group was assigned to quest, prize is distributed to only members ('członke') of a group.
 * 
 * @param[in] @id_quest ID of a quest, to mark as finished.
 * 
 * @return Returns empty string on success, else returns error message;
 */
function markQuestAsFinished($id_quest){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $checkQuestId = $pdo->prepare("SELECT (id=?) FROM prj.zlecenie WHERE id=?");
      $checkQuestId->bindParam(1, $id_quest, PDO::PARAM_INT);
      $checkQuestId->bindParam(2, $id_quest, PDO::PARAM_INT);
      $checkQuestId->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się otrzymać danych zlecenia.");
    }
    if($checkQuestId->fetchColumn() == 0){
      throw new Exception("Zlecenie o podanym ID, nie istnieje.");
    }

    try{
      $checkQuestStatus = $pdo->prepare("SELECT (status='w trakcie') as status_correct FROM prj.zlecenie WHERE id=?");
      $checkQuestStatus->bindParam(1, $id_quest, PDO::PARAM_INT);
      $checkQuestStatus->execute(); 
    }catch(PDOException $e){
      throw new Exception("Nie udało się sprawdzić statusu zadania.");
    }
    if($checkQuestStatus->fetchColumn() == 0){
      throw new Exception("Nie można zakończyć zadania które nie jest w trakcie wykonywania.");
    }

    try{
      $finishQuest = $pdo->prepare("UPDATE prj.zlecenie SET status='zakończone' WHERE id=?");
      $finishQuest->bindParam(1, $id_quest, PDO::PARAM_INT);
      $finishQuest->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się zakończyć zlecenia.");
    }

    try{
      $getGroupCount = $pdo->prepare("SELECT COUNT(id_łowca) FROM prj.przypisane_zlecenie pz JOIN prj.łowca l ON l.id=pz.id_łowca WHERE pz.id_zlecenie=? AND l.grupa_status='członek'");
      $getGroupCount->bindParam(1, $id_quest, PDO::PARAM_INT);
      $getGroupCount->execute();
      $groupCount = (float)$getGroupCount->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Nie udało się otrzymać ilości łowców w grupie.");
    }
    if($groupCount <= 0){
      throw new Exception("Nie można otrzymać ilości członków grupy wykonywanego zlecenia.");
    }

    try{
      $getQuestMoney = $pdo->prepare("SELECT nagroda FROM prj.zlecenie WHERE id=?");
      $getQuestMoney->bindParam(1, $id_quest, PDO::PARAM_INT);
      $getQuestMoney->execute();
      $questMoneyStr = $getQuestMoney->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Nie udało się otrzymać ilości nagrody za zlecenie.");
    }
    $questMoney = (float)trim($questMoneyStr, '$');  
    if($questMoney < 0){
      throw new Exception("Nagroda za ukończenie zlecenia nie może być mniejsza niż zero.");
    }

    try{
      $questHunters = $pdo->prepare("SELECT pz.id_łowca, l.pieniądze FROM prj.przypisane_zlecenie pz JOIN prj.łowca l ON l.id=pz.id_łowca WHERE pz.id_zlecenie=? AND l.grupa_status='członek'");
      $questHunters->bindParam(1, $_POST['finishQuest'], PDO::PARAM_INT);
      $questHunters->execute();
    }catch(PDOException $e){
      throw new Exception("Nie można otrzymać danych łowców, którzy wykonali zlecenie.");
    }

    try{
      $money = floor($questMoney / $groupCount);
      foreach($questHunters as $hunter){
        $changeHunterMoney = $pdo->prepare("UPDATE prj.łowca SET pieniądze=? WHERE id=?");
        $changeHunterMoney->execute([(int)trim($hunter['pieniądze'], '$')+$money, $hunter['id_łowca']]);
      }
    }catch(PDOException $e){
      throw new Exception("Nie udało się przypisać nagrody łowcom.");
    }
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}


/**
 * Function, that deletes quest of given ID.
 *
 *
 *
 * @return Returns empty string on success, else return error message.
 */
function deleteQuest($id_quest){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $checkQuestId = $pdo->prepare("SELECT (id=?) FROM prj.zlecenie WHERE id=?");
      $checkQuestId->bindParam(1, $id_quest, PDO::PARAM_INT);
      $checkQuestId->bindParam(2, $id_quest, PDO::PARAM_INT);
      $checkQuestId->execute();
    }catch(PDOException $e){
      throw new Exception("Próba znalezienia zlecenia, nie powiodła się.");
    }
    if($checkQuestId->fetchColumn() == 0){
      throw new Exception("Zlecenie o podanym ID nie istnieje.");
    }
    
    try{
      $checkQuestStatus = $pdo->prepare("SELECT (status='oczekuje') FROM prj.zlecenie WHERE id=?");
      $checkQuestStatus->bindParam(1, $id_quest, PDO::PARAM_INT);
      $checkQuestStatus->execute();
      $status_waiting = $checkQuestStatus->fetchColumn();
    }catch(PDOException $e){
      throw new Exception("Próba sprawdzenia statusu zlecenie, niepowiodła się.");
    }
    if($status_waiting == 0){
      throw new Exception("Nie można usunąć zlecenia, które nie jest w stanie oczekiwania na przyjęcie.");
    }
    
    try{
      $deleteBeastsFromQuest = $pdo->prepare("DELETE FROM prj.zlecenie_zwierzę WHERE id_zlecenie=?");
      $deleteBeastsFromQuest->bindParam(1, $id_quest, PDO::PARAM_INT);
      $deleteBeastsFromQuest->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się usunąć zwierząt przypisanych do zlecenia.");
    }

    try{
      $deleteQuest = $pdo->prepare("DELETE FROM prj.zlecenie WHERE id=?");
      $deleteQuest->bindParam(1, $id_quest, PDO::PARAM_INT);
      $deleteQuest->execute();
    }catch(PDOException $e){
      throw new Exception("Nie udało się usunąć zlecenia.");
    }
    
    $pdo->commit();
  }catch(Exception $e){
    $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

/**
 * Creates a new quest, assigned to provided Quest Maker, with apropriate data.
 *
 * @param[in] $id_questMaker ID of a Quest Maker, to which this quest has to be assigned.
 * @param[in] $money Value of prize after completing quest.
 * @param[in] $description, description of a quest.
 * @param[in] $beastsArray Array of beasts' IDs.
 *
 * @return Returns empty string on success, else return error message.
 */
function createNewQuest($id_questMaker, $money, $description, $beastsArray){
  global $pdo;
  try{
    $pdo->beginTransaction();
    try{
      $addNewQuest = $pdo->prepare("INSERT INTO prj.zlecenie (id, id_zleceniodawca, status, nagroda, opis) VALUES (DEFAULT, ?, 'oczekuje', ?, ?) RETURNING id");
      $addNewQuest->bindParam(1, $id_questMaker, PDO::PARAM_INT);
      $addNewQuest->bindParam(2, $money, PDO::PARAM_INT);
      $addNewQuest->bindParam(3, $description, PDO::PARAM_STR);
      $addNewQuest->execute();
      $last_id = $addNewQuest->fetch(PDO::FETCH_ASSOC)['id'];
    }catch(Exception $e){
      throw new Exception("Nie udało się dodać zlecenia.");
    }
    try{
      foreach($beastsArray as $beastId){
        $assignBeastToQuest = $pdo->prepare("INSERT INTO prj.zlecenie_zwierzę (id_zlecenie, id_zwierzę) VALUES(?, ?)");
        $assignBeastToQuest->bindParam(1, $last_id, PDO::PARAM_INT);
        $assignBeastToQuest->bindParam(2, $beastId, PDO::PARAM_INT);
        $assignBeastToQuest->execute();
      }
    }catch(PDOException $e){
      throw new Exception("Nie udało się przypisać zwierząt do zlecenia.");
    }
    $pdo->commit();
  }catch(Exception $e){
  $pdo->rollBack();
    return $e->getMessage() . "<br>";
  }
  return "";
}

?>

<!DOCTYPE html>
<html lang="pl-PL">
<head>
	<!-- get rid of favicon.ico request -->
	<link rel="icon" href="data:image/png;base64,iVBORw0KGgo="> 
	<meta http-equiv="Content-Language" content="pl">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="author" content="Maciej Muller">

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<link rel="Stylesheet" href="styles.css">
	<link rel="Stylesheet" href="styles_tablet.css">
	<link rel="Stylesheet" href="styles_mobile.css">
	<title>Projekt</title>
</head>
<body class="grain-background">

<header>
	<a href="http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php">
		<img id="headerTitle" src="assets/przewodnik_lowcy_header.png" alt="Przewodnik Łowcy"> 
	</a>
</header>

<section id="mainWindowWrapper">
<section id="mainWindow">
	<nav id="navbar">
		<a class="logo" href="questMaker.php">Ł</a>
		<a href="questMaker.php?page=bestiary">Bestiariusz</a>
    <a href="questMaker.php?page=classes">Klasy</a>
    <a href="questMaker.php?page=races">Rasy</a>
   
		<div id="navbar-right">
      <a href="http://pascal.fis.agh.edu.pl/~2mueller/loging/logoutQuestMaker.php">Wyloguj</a>
		</div>
	</nav>

	<div id="content">
    <h1><?php echo $username; ?></h1>
    <br>
    <h2>Dodaj nowe zlecenie</h2>
    <form class="centered-form" method="POST" name="addNewQuest" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <p>Nagroda<sup>*</sup></p>
      <input onkeypress="return isNumberKey(event)" type="number" min="0" pattern="[0-9]" name="newPrize" <?php if(isset($_POST['newPrize'])) echo "value=\"".$_POST['newPrize']."\""; ?>><br>
      <p>Opis zlecenia</p>
      <textarea name="newDescription"><?php if(isset($_POST['newDescription'])) echo $_POST['newDescription']; ?></textarea><br>
      <div id="newBeastsForm">
      <?php 
        try{
          $beastsQuery = $pdo->query("SELECT id, nazwa FROM zwierze_rasa;");
          $beastsQuery->execute();
          echo "<select name=\"newBeasts[]\">";
          foreach($beastsQuery as $beast){
            echo "<option value=\"".$beast['id']."\">".$beast['nazwa']."</option>";
          }
          echo "</select><br>";
        }catch(PDOException $e){}
    ?>
      </div>
      <div style="margin: 0 auto; display: flex;">
        <button style="display: inline-block; max-width: none;" id="addNewBeast" class="form-button" style="margin-top: 2px;" type="button">Dodaj kolejne zwierzę</button>
        <button style="display: inline-block; max-width: none;" id="removeNewBeast" class="form-button" style="margin-top: 2px;" type="button">Usuń zwierzę</button>
      </div>
      <input id="createNewQuestButton" class="form-button" style="margin-top: 8px;" type="submit" name="createNewQuest" value="Stworz nowe zlecenie">
    </form>

    <br>
    <h2>Aktualne zlecenia:</h2>
    <form method="POST" name="questStatusSelect" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <select id="userType" name="questStatus" onchange="document.questStatusSelect.submit()">
        <option value="oczekuje" <?php if($_POST['questStatus'] == 'oczekuje') echo "selected=\"selected\""; ?> >Oczekujące</option>
        <option value="w trakcie" <?php if($_POST['questStatus'] == 'w trakcie') echo "selected=\"selected\""; ?> >W trakcie wykonywania</option>
        <option value="zakończone" <?php if($_POST['questStatus'] == 'zakończone') echo "selected=\"selected\""; ?> >Zakończone</option>
      </select>
    </form>

    <table>
      <tr>
        <th>ID</th>
        <th>Status</th>
        <th>Nagroda</th>
        <th>Zwierzęta do upolowania</th>
      </tr>
    <?php
      try{
        if(!isset($_POST['questStatus']))
          $_POST['questStatus'] = "oczekuje";
        if(isset($_POST['questStatus']) && ($_POST['questStatus'] == 'oczekuje')){
          $getQuests = $pdo->prepare("SELECT z.id, z.status, z.nagroda FROM prj.zlecenie z JOIN prj.zleceniodawca zl ON zl.id=z.id_zleceniodawca WHERE z.status=? AND z.id_zleceniodawca=?");
          $getQuests->bindParam(1, $_POST['questStatus'], PDO::PARAM_STR);
          $getQuests->bindParam(2, $id_questMaker, PDO::PARAM_INT);
          $getQuests->execute();
          if($getQuests == true){
            foreach($getQuests as $quest){
              echo "<tr>";
              echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_quest=".$quest['id']."\">".$quest['id']."</a></td>";
              echo "<td>".$quest['status']."</td>";
              echo "<td>".$quest['nagroda']."</td>";
              $beastsQuestQuery = $pdo->prepare("SELECT zw.nazwa, COUNT(zw.id) as ilosc, zw.id FROM prj.zlecenie z JOIN prj.zlecenie_zwierzę zz ON z.id=zz.id_zlecenie JOIN prj.zwierzę zw ON zz.id_zwierzę=zw.id group by zw.nazwa, z.id, zw.id having z.id=? AND z.id_zleceniodawca=? AND z.status=?");
              $beastsQuestQuery->bindParam(1, $quest['id'], PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(2, $id_questMaker, PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(3, $_POST['questStatus'], PDO::PARAM_INT);
              $beastsQuestQuery->execute();
              echo "<td>";
              if($beastsQuestQuery == true){
                foreach($beastsQuestQuery as $beast){
                  echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_beast=".$beast['id']."\">".$beast['nazwa']."(".$beast['ilosc'].")</a>";
                }
              }else{
                echo "Brak danych zwierzęcia.";
              }
              echo "</td>";  
            ?>
            <td><form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
              <input type="hidden" name="deleteQuest" value="<?php echo $quest['id']; ?>">
              <input id="deletedQuestButton" class="form-button-fill" type="submit" name="deletedQuest" value="Usuń zadanie">
            </form></td>
            <?php
            }
          }else{
            $error = $error . "Nie można otrzymać danych, zleceń.<br>";
          }
        }else if($_POST['questStatus'] == 'w trakcie'){
          $getQuests = $pdo->prepare("SELECT z.id, z.status, z.nagroda FROM prj.zlecenie z JOIN prj.zleceniodawca zl ON zl.id=z.id_zleceniodawca WHERE z.status=? AND z.id_zleceniodawca=?");
          $getQuests->bindParam(1, $_POST['questStatus'], PDO::PARAM_STR);
          $getQuests->bindParam(2, $id_questMaker, PDO::PARAM_INT);
          $getQuests->execute();
          if($getQuests == true){
            foreach($getQuests as $quest){
              echo "<tr>";
              echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_quest=".$quest['id']."\">".$quest['id']."</a></td>";
              echo "<td>".$quest['status']."</td>";
              echo "<td>".$quest['nagroda']."</td>";
              $beastsQuestQuery = $pdo->prepare("SELECT zw.nazwa, COUNT(zw.id) as ilosc, zw.id FROM prj.zlecenie z JOIN prj.zlecenie_zwierzę zz ON z.id=zz.id_zlecenie JOIN prj.zwierzę zw ON zz.id_zwierzę=zw.id group by zw.nazwa, z.id, zw.id having z.id=? AND z.id_zleceniodawca=? AND z.status=?");
              $beastsQuestQuery->bindParam(1, $quest['id'], PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(2, $id_questMaker, PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(3, $_POST['questStatus'], PDO::PARAM_INT);
              $beastsQuestQuery->execute();
              echo "<td>";
              if($beastsQuestQuery == true){
                foreach($beastsQuestQuery as $beast){
                  echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_beast=".$beast['id']."\">".$beast['nazwa']."(".$beast['ilosc'].")</a>";
                }
              }else{
                echo "Brak danych zwierzęcia.";
              }
              echo "</td>";
              ?>
            <td><form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
              <input type="hidden" name="finishQuest" value="<?php echo $quest['id']; ?>">
              <input id="finishedQuestButton" class="form-button-fill" type="submit" name="finishedQuest" value="Zakończ zadanie">
            </form></td>
              <?php
            }
          }
        }else if($_POST['questStatus'] == 'zakończone'){
          $getQuests = $pdo->prepare("SELECT z.id, z.status, z.nagroda FROM prj.zlecenie z JOIN prj.zleceniodawca zl ON zl.id=z.id_zleceniodawca WHERE z.status=? AND z.id_zleceniodawca=?");
          $getQuests->bindParam(1, $_POST['questStatus'], PDO::PARAM_STR);
          $getQuests->bindParam(2, $id_questMaker, PDO::PARAM_INT);
          $getQuests->execute();
          if($getQuests == true){
            foreach($getQuests as $quest){
              echo "<tr>";
              echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_quest=".$quest['id']."\">".$quest['id']."</a></td>";
              echo "<td>".$quest['status']."</td>";
              echo "<td>".$quest['nagroda']."</td>";
              $beastsQuestQuery = $pdo->prepare("SELECT zw.nazwa, COUNT(zw.id) as ilosc, zw.id FROM prj.zlecenie z JOIN prj.zlecenie_zwierzę zz ON z.id=zz.id_zlecenie JOIN prj.zwierzę zw ON zz.id_zwierzę=zw.id group by zw.nazwa, z.id, zw.id having z.id=? AND z.id_zleceniodawca=? AND z.status=?");
              $beastsQuestQuery->bindParam(1, $quest['id'], PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(2, $id_questMaker, PDO::PARAM_INT);
              $beastsQuestQuery->bindParam(3, $_POST['questStatus'], PDO::PARAM_INT);
              $beastsQuestQuery->execute();
              echo "<td>";
              if($beastsQuestQuery == true){
                foreach($beastsQuestQuery as $beast){
                  echo "<a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_beast=".$beast['id']."\">".$beast['nazwa']."(".$beast['ilosc'].")</a>";
                }
              }else{
                echo "Brak danych zwierzęcia.";
              }
              echo "</td>";
            }
          }
        }
      }catch(PDOException $e){
        $sqlError = $sqlError . $e->getMessage() . " | ";
      }
    ?>
    </table>

    <p><?php echo "<p>".$error."</p>"; echo "<p>".$sqlError."</p>"; ?></p>
	</div>
</section>
</section>

<script>
function newRow(id, elements){
  var row = "<select id=\"newQuestBeast"+id+"\" name=\"newBeasts[]\">";
  row += elements;
  row += "</select>";
  return row;
}


$(document).ready(function () {
  var idx = 1;
  var addbeastElement = $("#newBeastsForm");
  //var newRow = "<select name=\"newBeasts[]\">";

  let beastsId = <?php 
    try{
      $beastsQuery = $pdo->query("SELECT id FROM zwierze_rasa;");
      $beastsQuery->execute();
      $beasts = [];
      foreach($beastsQuery as $beast){
        array_push($beasts, $beast['id']);
      }

      echo json_encode($beasts);
    }catch(PDOException $e){}
    ?>;

  let beastsNames = <?php 
    try{
      $beastsQuery = $pdo->query("SELECT nazwa FROM zwierze_rasa;");
      $beastsQuery->execute();
      $beastsN = [];
      foreach($beastsQuery as $beast){
        array_push($beastsN, $beast['nazwa']);
      }
      echo json_encode($beastsN);
    }catch(PDOException $e){}
    ?>;
  
  elements = "";
  for(let i=0; i<beastsId.length; ++i){
    elements += "<option value=\"" + beastsId[i] + "\">" + beastsNames[i] + "</option>";
  }
  
  $(document).on("click", "#addNewBeast", function () {
    $(addbeastElement).append(newRow(idx, elements));
    idx = idx + 1;
  });
  $(document).on("click", "#removeNewBeast", function () {
    if(idx > 1){
      idx = idx - 1;
      $("#newQuestBeast"+idx).remove();
    }
  });
});

function isNumberKey(evt) {
  var charCode = (evt.which) ? evt.which : evt.keyCode
  if (charCode > 31 && (charCode < 48 || charCode > 57))
    return false;
  return true;
}
</script>

</body>
</html>
