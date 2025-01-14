<!DOCTYPE html>
<html lang="de">
<head>
    <title>Einstellungen</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
require "functions.php";
session_start();
$btnResponse = '';

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;
$schoolUrl = $_SESSION['schoolUrl'] ?? null;


$conn = connectToDatabase();


$slackBotToken = getDataWithOneArgFromDatabase($username, "slack_bot_token", "SELECT slack_bot_token FROM users WHERE username = ?");
$notificationForDaysInAdvance = getDataWithOneArgFromDatabase($username, "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users WHERE username = ?");



$newSlackBotToken = false;
if(!empty($_POST['slackBotToken'])) {
        $slackBotToken = $_POST['slackBotToken'];
        $newSlackBotToken = true;
    }

$notificationForDaysInAdvance = $_POST['notificationDays'] ?? $notificationForDaysInAdvance;


if($newSlackBotToken){
    if(writeThreeArgToDatabase($slackBotToken, $notificationForDaysInAdvance, $username, "UPDATE users SET slack_bot_token = ?, notification_for_days_in_advance = ? WHERE username = ?")){
        $btnResponse = getBtnText(true);
    } else {
        $btnResponse = getBtnText(false);
    }
    $newSlackBotToken = false;
} elseif ($notificationForDaysInAdvance){
    if(writeTwoArgToDatabase(false, $notificationForDaysInAdvance, $username, "UPDATE users SET notification_for_days_in_advance = ? WHERE username = ?")){
        $btnResponse = getBtnText(true);
    } else {
        $btnResponse = getBtnText(false);
    }
}
$conn->close();




initiateCheck();

if (isset($_POST['action'])) {
    $conn = connectToDatabase();
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            if(!writeOneArgToDatabase($username, "DELETE FROM users WHERE username = ?")){
                $btnResponse = '<p class="successful">Konto erfolgreich gelöscht</p>';
                sleep(2);
                $conn->close();
                logOut();
            } else {
                $btnResponse = '<p class="failed">Fehler beim Löschen des Kontos</p>';
            }
            break;
        case 'testNotification':
            if(sendslackMessage("Testbenachrichtigung", "Wenn du das hier ließt, hast du alles richtig gemacht! Ab sofort, erhälst du Benachrichtitigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 15 Min. wind überprüft, ob Änderungen vorhanden sind.", "")){
                if(writeOneArgToDatabase($username, "UPDATE users SET setup_complete = true WHERE username = ?")){
                    $btnResponse = '<p class="successful">Testbenachrichtigung erfolgreich gesendet</p>';
                }
            } else {
                $btnResponse = '<p class="failed">Fehler beim Senden der Testbenachrichtigung</p>';
            }
            break;
    }
}


?>




<div class="parent">

    <form action="settings.php" method="post">
        <h2>Einstellungen</h2>
        <br>
        <label for="slackBotToken">Slack Bot Token:</label>
        <div class="label-container">
            <input type="text" id="slackBotToken" name="slackBotToken" placeholder="<?php echo $slackBotToken;?>">
            <span class="info-icon" onclick="openExternInfoSite('BotToken')">?</span>
        </div>
        <br><br>


        <label for="notificationDays">Wie viele Tage im Voraus sollen auf Änderungen geprüft werden?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="1" max="30" value="<?php echo $notificationForDaysInAdvance;?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance;?></output>
            <span class="info-icon" onclick="openExternInfoSite('TageInVorraus')">?</span>
        </div>
        <br><br>

        <button class="btn-save-settings" type="submit">Einstellungen speichern</button>
        <br><br>
        <?php echo $btnResponse; ?>
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
