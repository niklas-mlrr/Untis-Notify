<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Einstellungen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
require "functions.php";
session_start();

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;
$schoolUrl = $_SESSION['schoolUrl'] ?? null;


$conn = connectToDatabase();


$pushoverApiKey = getDataWithOneArgFromDatabase($username, "pushover_api_key", "SELECT pushover_api_key FROM users WHERE username = ?");
$pushoverUserKey = getDataWithOneArgFromDatabase($username, "pushover_user_key", "SELECT pushover_user_key FROM users WHERE username = ?");
$notificationForDaysInAdvance = getDataWithOneArgFromDatabase($username, "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users WHERE username = ?");



$newPushoverApiKey = false;
$newPushoverUserKey = false;
if(isset($_POST['pushoverApiKey'])) {
    if ($_POST['pushoverApiKey'] != "") {
        $pushoverApiKey = $_POST['pushoverApiKey'];
        $newPushoverApiKey = true;
    }
}
if(isset($_POST['pushoverUserKey'])) {
    if ($_POST['pushoverUserKey'] != "") {
        $pushoverUserKey = $_POST['pushoverUserKey'];
        $newPushoverUserKey = true;
    }
    $notificationForDaysInAdvance = $_POST['notificationDays'];
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if($newPushoverApiKey && $newPushoverUserKey){
        writeFourArgToDatabase($pushoverApiKey, $pushoverUserKey, $notificationForDaysInAdvance, $username, "UPDATE users SET pushover_api_key = ?, pushover_user_key = ?, notification_for_days_in_advance = ? WHERE username = ?");
        $newPushoverApiKey = false;
        $newPushoverUserKey = false;
    } elseif ($newPushoverApiKey){
        writeThreeArgToDatabase($pushoverApiKey, $notificationForDaysInAdvance, $username, "UPDATE users SET pushover_api_key = ?, notification_for_days_in_advance = ? WHERE username = ?");
        $newPushoverApiKey = false;
    } elseif ($newPushoverUserKey){
        writeThreeArgToDatabase($pushoverUserKey, $notificationForDaysInAdvance, $username, "UPDATE users SET pushover_user_key = ?, notification_for_days_in_advance = ? WHERE username = ?");
        $newPushoverUserKey = false;
    } elseif ($notificationForDaysInAdvance){
        echo "dzjzezj";
        writeTwoArgToDatabase($notificationForDaysInAdvance, $username, "UPDATE users SET notification_for_days_in_advance = ? WHERE username = ?");
    }
$conn->close();
}



initiateCheck();

if (isset($_POST['action'])) {
    $conn = connectToDatabase();
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            writeOneArgToDatabase($username, "DELETE FROM users WHERE username = ?");
            echo "Konto gelöscht.";
            $conn->close();
            logOut();
            break;
        case 'testNotification':
            sendPushoverNotification("Testbenachrichtigung", "Wenn du das hier ließt, hast du alles richtig gemacht! Ab sofort, erhälst du Benachrichtitigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 15 Min. wind überprüft, ob Änderungen vorhanden sind.", "");
            break;
    }
}

function logOut(){
    setcookie(session_name(), '', 100);
    session_unset();
    session_destroy();
    $_SESSION = array();
    header("Location: startpage.php");
}

?>






<div class="parent">

    <form action="settings.php" method="post">
        <h2>Einstellungen</h2>
        <br>
        <label for="pushoverApiKey">Pushover API Key:</label>
        <div class="label-container">
            <input type="text" id="pushoverApiKey" name="pushoverApiKey" placeholder="<?php echo $pushoverApiKey;?>">
            <span class="info-icon" onclick="openExternInfoSite('ApiKey')">?</span>
        </div>
        <br><br>

        <label for="pushoverUserKey">Pushover User Key:</label>
        <div class="label-container">
            <input type="text" id="pushoverUserKey" name="pushoverUserKey" placeholder="<?php echo $pushoverUserKey;?>">
            <span class="info-icon" onclick="openExternInfoSite('UserKey')">?</span>
        </div>
        <br><br>


        <label for="notificationDays">Für wie viele Tage im Voraus möchtest du benachrichtigt werden, wenn Änderungen auftreten?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="1" max="30" value="<?php echo $notificationForDaysInAdvance;?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance;?></output>
            <span class="info-icon" onclick="openExternInfoSite('TageInVorraus')">?</span>
        </div>
        <br><br>

        <button class="btn-save-settings" type="submit">Einstellungen speichern</button>
    </form>
    <form action="settings.php" method="post">
        <button class="btn-testbenachrichtigung" type="submit" name="action" value="testNotification">Testbenachrichtigung senden</button><br>
        <nobr>
            <button class="btn-log-out" type="submit" name="action" value="logout">Abmelden</button>
            <button class="btn-delete-acc" type="submit" name="action" value="deleteAccount">Konto löschen</button>
        </nobr>
    </form>

</div>
</body>
<script src="script.js"></script>
</html>