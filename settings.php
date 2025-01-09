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

$conn = connectToDatabase();

$pushoverApiKey = $_POST['pushoverApiKey'] ?? null;
$pushoverUserKey = $_POST['pushoverUserKey'] ?? null;
$schoolUrl = $_POST['schoolUrl'] ?? null;
$notificationForDaysInAdvance = $_POST['notificationDays'] ?? null;

if ($pushoverApiKey && $pushoverUserKey && $schoolUrl && $notificationForDaysInAdvance) {
    writeFourArgToDatabase($pushoverApiKey, $pushoverUserKey, $schoolUrl, $notificationForDaysInAdvance, "
        UPDATE users SET pushover_api_key = ?, pushover_user_key = ?, school_url = ?, notification_for_days_in_advance = ? WHERE username = 'MüllerNik'");
}



if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'logout':
            //logout();
            echo "logout.";
            break;
        case 'deleteAccount':
            //deleteAccount();
            echo "delete acc.";
            break;
        case 'testNotification':
            sendPushoverNotification("Testbenachrichtigung", "Wenn du das hier ließt, hast du alles richtig gemacht! Ab sofort, erhälst du Benachrichtitigungen, wenn es Änderungen in deinem Stundenplan gibt. Alle 15 Min. wind überprüft, ob Änderungen vorhanden sind.", "");
            break;
    }

}

function logout() {
    // Deine PHP-Funktion für das Abmelden
    echo "Abgemeldet.";
}

function deleteAccount() {
    // Deine PHP-Funktion für das Löschen des Kontos
    echo "Konto gelöscht.";
}
?>





<div class="parent">

    <form action="settings.php" method="post">
        <h2>Einstellungen</h2>
        <br>
        <label for="pushoverApiKey">Pushover API Key:</label>
        <div class="label-container">
            <input type="text" id="pushoverApiKey" name="pushoverApiKey" required>
            <span class="info-icon" onclick="openExternInfoSite('ApiKey')">?</span>
        </div>
        <br><br>

        <label for="pushoverUserKey">Pushover User Key:</label>
        <div class="label-container">
            <input type="text" id="pushoverUserKey" name="pushoverUserKey" required>
            <span class="info-icon" onclick="openExternInfoSite('UserKey')">?</span>
        </div>
        <br><br>

        <label for="schoolUrl">Schul-URL:</label>
        <div class="label-container">
            <input type="text" id="schoolUrl" name="schoolUrl" placeholder="https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode">
            <span class="info-icon" onclick="toggleInfo('info-schoolUrl')">?</span>
        </div>
        <div class="info-field" id="info-schoolUrl">Hier muss nichts eingegeben werden, wenn du auf dem TRG bist.</div>
        <br><br>

        <label for="notificationDays">Für wie viele Tage im Voraus möchtest du benachrichtigt werden, wenn Änderungen auftreten?</label>
        <div class="label-container">
            <input type="range" id="notificationDays" name="notificationDays" min="1" max="30" value="14" oninput="this.nextElementSibling.value = this.value">
            <output>14</output>
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
<script>
    function toggleInfo(id) {
        var infoField = document.getElementById(id);
        infoField.style.display = (infoField.style.display === "block") ? "none" : "block";
    }

    document.addEventListener('click', function(event) {
        var infoFields = document.querySelectorAll('.info-field');
        infoFields.forEach(function(infoField) {
            if (!infoField.contains(event.target) && !event.target.classList.contains('info-icon')) {
                infoField.style.display = 'none';
            }
        });
    });

    function openExternInfoSite(info) {
        var url = "";
        switch(info) {
            case "ApiKey":
                url = "https://pushover.net/apps/build";
                break;
            case "UserKey":
                url = "https://pushover.net/apps/build";
                break;
            case "TageInVorraus":
                url = "https://niklas.craft.me/Untis-Benachrichtigungen-Anleitung/b/594ADF73-6DE4-40AB-9B9A-A668C4A53721/F%C3%BCr-wie-viele-Tage-im-Voraus-m%C3%B6chtes";
                break;


        }
        window.open(url, '_blank');
    }

    document.querySelector('form').addEventListener('submit', function(event) {
        var schoolUrlInput = document.getElementById('schoolUrl');
        if (schoolUrlInput.value.trim() === '') {
            schoolUrlInput.value = schoolUrlInput.placeholder;
        }
    });


</script>
</body>
</html>