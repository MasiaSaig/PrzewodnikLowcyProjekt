<?php
/**
 * @file quest.php
 * @brief File where user can see data of a certain quest. All creatures to hunt, description, status, etc. 
 */

if(!isset($_GET['id_quest']) || !is_numeric($_GET['id_quest'])){
  $guestErrorID = true;
}

$loggedInAsQuestMaker = false;
try{
  $loggedInAsQuestMaker = loggedInQuestMaker();
}catch(PDOException $e){
  echo "Błąd podczas sprawdzania czy użytkownik jest zalogowany: ".$e->getMessage();
}

try{
  $checkGuild = $pdo->prepare("SELECT (id=?) FROM prj.zlecenie WHERE id=?");
  $checkGuild->bindParam(1, $_GET['id_quest'], PDO::PARAM_INT);
  $checkGuild->bindParam(2, $_GET['id_quest'], PDO::PARAM_INT);
  $checkGuild->execute();
  if($checkGuild->fetchColumn() == 0){
    $guestErrorID = true;
  }
}catch(PDOException $e){
  $guestErrorID = true;
}

if($guestErrorID){
  echo "<p style=\"color: red;\">ZZlecenie z podanym ID nie istnieje</p>";
  return;
}


$questError = "";
try{
  $questDataQuery = $pdo->prepare("SELECT z.id, zl.imię, z.status, z.nagroda, z.opis FROM prj.zlecenie z JOIN prj.zleceniodawca zl ON z.id_zleceniodawca=zl.id WHERE z.id=?");
  $questDataQuery->bindParam(1, $_GET['id_quest'], PDO::PARAM_INT);
  $questDataQuery->execute();
  if($questDataQuery==false){
    $questError = $questError . " Nie mozna otrzymać danych zlecenia.<br>";
  }else{
    $quest = $questDataQuery->fetch();
    echo "<h1>Zlecenie ".$quest['id']."</h1>";
    echo "<p>Zleceniodawca: ".$quest['imię']."</p>";
    echo "<p>Status zlecenia: ".$quest['status']."</p>";
    echo "<p>Nagroda za ukończenie zlecenia: ".$quest['nagroda']."</p>";
    echo "<p>".$quest['opis']."</p>";
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

try{
  $getBeasts = $pdo->prepare("SELECT zr.id, zr.nazwa, zr.nazwa_rasy, COUNT(zz.id_zlecenie) as ilosc FROM prj.zlecenie z JOIN prj.zlecenie_zwierzę zz ON z.id=zz.id_zlecenie JOIN zwierze_rasa zr ON zz.id_zwierzę=zr.id GROUP BY zz.id_zlecenie, zz.id_zwierzę, zr.id, zr.nazwa, zr.nazwa_rasy, z.id HAVING z.id=?");
  $getBeasts->bindParam(1, $_GET['id_quest'], PDO::PARAM_INT);
  $getBeasts->execute();
  if($getBeasts == false)
    $questError = $questError . "Nie mozna otrzymać danych zwierząt.<br>";
  else{
?>
<table>
    <tr>
      <th>ID</th>
      <th>Nazwa zwierzęcia</th>
      <th>Nazwa rasy zwierzęcia</th>
      <th>Ilość zwierząt</th>
    </tr>
<?php
    foreach($getBeasts as $beast){
      echo "<tr>";
      echo "<td>".$beast['id']."</td>";
      if($loggedInAsQuestMaker > 0)
        echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/questMaker.php?id_beast=".$beast['id']."\">".$beast['nazwa']."</a></td>";        
      else
        echo "<td><a href=\"http://pascal.fis.agh.edu.pl/~2mueller/index.php?id_beast=".$beast['id']."\">".$beast['nazwa']."</a></td>";
      echo "<td>".$beast['nazwa_rasy']."</td>";
      echo "<td>".$beast['ilosc']."</td>";
      echo "</tr>";
    }
?>
</table>
<?php
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

try{
  if($quest['status'] == "zakończone"){
    $getHunters = $pdo->prepare("SELECT l.imię FROM prj.łowca l JOIN prj.przypisane_zlecenie pz ON l.id=pz.id_łowca JOIN prj.zlecenie z ON z.id=pz.id_zlecenie WHERE z.id=?");
    $getHunters->bindParam(1, $_GET['id_quest'], PDO::PARAM_INT);
    $getHunters->execute();
    if($getHunters == false){
      $questError = $questError . " Nie mozna otrzymać danych łowców którzy wynkonali to zlecenie.<br>";
    }else{
      echo "<br>";
      echo "<p>Łowcy którzy ukończyli to zlecenie:</p>";
      echo "<ul style=\"padding-left: 15px;\">";
      foreach($getHunters as $hunter){
        echo "<li>".$hunter['imię']."</li>";
      }
      echo "</ul>";
    }
  }
}catch(PDOException $e){
  $sqlError = $sqlError . " | " . $e->getMessage();
}

echo "<p style=\"color: red;\">".$questError."</p>";
?>
