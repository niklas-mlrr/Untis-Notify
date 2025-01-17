<!DOCTYPE html>
<html lang="de">
<head>
    <title>Einstellungen</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();

require "functions.php";

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;
$conn = connectToDatabase();

$slackBotToken = getColumnFromDatabase($conn, [$username], "slack_bot_token", "SELECT slack_bot_token FROM users WHERE username = ?");
$dictionary = getColumnFromDatabase($conn, [$username], "dictionary", "SELECT dictionary FROM users WHERE username = ?");
$notificationForDaysInAdvance = getColumnFromDatabase($conn, [$username], "notification_for_days_in_advance", "SELECT notification_for_days_in_advance FROM users WHERE username = ?");


$btnResponse = '';
$newSlackBotToken = false;
$newDictionary = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!empty($_POST['slackBotToken'])) {
        $slackBotToken = $_POST['slackBotToken'];
        $newSlackBotToken = true;
    }
    if(!empty($_POST['dictionary'])) {
        $dictionary = $_POST['dictionary'];
        $newDictionary = true;
    }

    $notificationForDaysInAdvance = $_POST['notificationDays'] ?? $notificationForDaysInAdvance;


    if($newSlackBotToken){
        if(writeDataToDatabase($conn, [$slackBotToken, $notificationForDaysInAdvance, $username], "UPDATE users SET slack_bot_token = ?, notification_for_days_in_advance = ? WHERE username = ?")){
            $btnResponse = getMessageText("settingsSavedSuccessfully");
        } else {
            $btnResponse = getMessageText("settingsNotSaved");
        }
        $newSlackBotToken = false;
    }

    if($newDictionary){
        if(writeDataToDatabase($conn, [$dictionary, $notificationForDaysInAdvance, $username], "UPDATE users SET dictionary = ?, notification_for_days_in_advance = ? WHERE username = ?")){
            $btnResponse = getMessageText("settingsSavedSuccessfully");
        } else {
            $btnResponse = getMessageText("settingsNotSaved");
        }
        $newDictionary = false;
    }

    if ($notificationForDaysInAdvance){
        if(writeDataToDatabase($conn, [$notificationForDaysInAdvance, $username], "UPDATE users SET notification_for_days_in_advance = ? WHERE username = ?")){
            $btnResponse = getMessageText("settingsSavedSuccessfully");
        } else {
            $btnResponse = getMessageText("settingsNotSaved");
        }
    }
    $conn->close();
}
$conn = connectToDatabase();
generateReplacementArray($conn, $username);
//initiateCheck($conn, $username, $password);

if (isset($_POST['action'])) {
    $conn = connectToDatabase();
    switch ($_POST['action']) {
        case 'logout':
            logOut();
            break;
        case 'deleteAccount':
            if (writeDataToDatabase($conn, [$username], "DELETE FROM users WHERE username = ?")) {
                $btnResponse = getMessageText("accountDeletedSuccessfully");
                sleep(2);
                $conn->close();
                logOut();
            } else {
                $btnResponse = getMessageText("accountNotDeleted");
            }
            break;
        case 'testNotification':

            $testNotificationAusfall = sendslackMessage("ausfall", "Testbenachrichtigung für den Channel ausfall", "Test", "");
            $testNotificationRaumänderung = sendslackMessage("raumänderung", "Testbenachrichtigung für den Channel raumänderung", "Test", "");
            $testNotificationVertretung = sendslackMessage("vertretung", "Testbenachrichtigung für den Channel vertretung", "Test", "");
            $testNotificationSonstiges = sendslackMessage("sonstiges", "Testbenachrichtigung für den Channel sonstiges", "Wenn du das hier liest, hast du alles richtig gemacht! (Solange auf der Website \"Alle 4 Testbenachrichtigungen erfolgreich gesendet\" stand.) Ab sofort erhältst du Benachrichtigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 10 Min. wird überprüft, ob Änderungen vorhanden sind. Nun kannst du die Slack App überall dort installieren und dich anmelden, wo du benachrichtigt werden möchtest (Handy, iPad, usw.). In Einzelfällen (z.B. wenn bei Untis durch eine spezielle Veranstalltung aufeinmal 2 \"Fächer\" für eine Stunde eingetragen sind und die Stunde somit vertikal in der Mitte geteilt ist) kann es sein, dass nicht alles richtig verarbeitet werden kann. Bei Fehlern oder Fragen mir gerne schreiben.", "");

            if ($testNotificationAusfall && $testNotificationRaumänderung && $testNotificationVertretung && $testNotificationSonstiges) {
                if (writeDataToDatabase($conn, [$username], "UPDATE users SET setup_complete = true WHERE username = ?")) {
                    $btnResponse = getMessageText("testNotificationAllSent");
                }
            } elseif(!$testNotificationSonstiges && !$testNotificationVertretung && !$testNotificationRaumänderung && !$testNotificationAusfall) {
                $btnResponse = getMessageText("testNotificationAllNotSent");
            } elseif (!$testNotificationAusfall) {
                $btnResponse = getMessageText("testNotificationAusfallNotSent");
            } elseif (!$testNotificationRaumänderung) {
                $btnResponse = getMessageText("testNotificationRaumänderungNotSent");
            } elseif (!$testNotificationVertretung) {
                $btnResponse = getMessageText("testNotificationVertretungNotSent");
            } elseif (!$testNotificationSonstiges) {
                $btnResponse = getMessageText("testNotificationSonstigesNotSent");
            }
            break;
    }
    $conn->close();
}
?>

<div class="parent">


    <button id="toggle-theme" class="dark-mode-switch-btn">
        <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
    </button>

    <form action="settings.php" method="post">
        <h2>Einstellungen</h2>
        <br>

        <label for="slackBotToken">Slack Bot Token:</label>
        <div class="label-container">
            <input type="text" id="slackBotToken" name="slackBotToken" placeholder="<?php echo $slackBotToken; ?>">
            <span class="info-icon" onclick="openExternInfoSite('BotToken')">?</span>
        </div>
        <br><br>

        <label for="dictionary">Dictionary (optional):</label>
        <div class="label-container">
            <input type="text" id="dictionary" name="dictionary" placeholder="<?php echo $dictionary; ?>">
            <span class="info-icon" onclick="openExternInfoSite('dictionary')">?</span>
        </div>
        <br><br>

        <label for="notificationDays">Wie viele Tage im Voraus sollen auf Änderungen geprüft werden?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="1" max="30" value="<?php echo $notificationForDaysInAdvance; ?>" oninput="this.nextElementSibling.value = this.value">
            <output><?php echo $notificationForDaysInAdvance; ?></output>
            <span class="info-icon" onclick="openExternInfoSite('TageInVorraus')">?</span>
        </div>
        <br><br>
        <button class="btn-save-settings btn" type="submit">Einstellungen speichern</button>
        <br><br>
        <?php echo $btnResponse; ?>
    </form>
    <form action="settings.php" method="post">
        <button class="btn-testbenachrichtigung btn" type="submit" name="action" value="testNotification">Testbenachrichtigungen senden</button><br>
        <nobr>
            <button class="btn-log-out btn" type="submit" name="action" value="logout">Abmelden</button>
            <button class="btn-delete-acc btn" type="submit" name="action" value="deleteAccount">Konto löschen</button>
        </nobr>
    </form>
</div>
</body>
<script src="script.js"></script>
</html>
