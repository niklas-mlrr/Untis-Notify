<!DOCTYPE html>
<html lang="de">
<head>
    <title>Untis Notify</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$schoolUrl = $_POST['schoolUrl'] ?? null;

if ($username && $password && $schoolUrl) {
    require "functions.php";


    $login = loginToWebUntis($username, $password, $schoolUrl);
    if ($login) {

        $conn = connectToDatabase();

        $isUserInDatabase = getRowsFromDatabase($conn, [$username, $password], "SELECT * FROM users WHERE username = ? AND password = ?") > 0;

        if (!$isUserInDatabase) {
            writeDataToDatabase($conn, [$username, $password, $schoolUrl], "INSERT INTO users (username, password, school_url) VALUES (?, ?, ?)");
        }

        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['conn'] = $conn;
        $_SESSION['schoolUrl'] = $schoolUrl;


        $conn->close();
        header("Location: settings.php");
        exit();
    } else {
        $loginMessage = getMessageText("loginFailed");
    }
} else {
    $loginMessage = '';
}
?>




<div class="img-div">
    <img class="notification-img" src="notification example.png" alt="Notification Example">
</div>
<br><br><br><br>
<p class="notification-text">↑ Beispiel einer Benachrichtigung ↑</p>


<div class="parent">

    <button id="toggle-theme">
        <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
    </button>

    <form action="index.php" method="post">
        <h2>Untis Notify</h2>
        <h4>- Benachrichtigungen für Untis -</h4>
        <p class="info-text">Die Einrichtung dauert einmalig ca. 15 Min. und benötigt ein Handy & Pc / Laptop</p>
        <br>

        <label for="schoolUrl">Schul-URL:</label>
        <div class="label-container">
            <input type="text" id="schoolUrl" name="schoolUrl" placeholder="https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode">
            <span class="info-icon" onclick="toggleInfo('info-schoolUrl')">?</span>
        </div>
        <div class="info-field" id="info-schoolUrl">Wenn du auf dem TRG bist, muss hier nichts eingegeben werden. <br> Dies ist eine schulspezifische URL.</div>
        <br>

        <label for="username">Untis Benutzername:</label>
        <div class="label-container">
            <input type="text" id="username" name="username" required>
            <span class="info-icon" onclick="openExternInfoSite('username')">?</span>
        </div>
        <br>

        <label for="password">Untis Passwort:</label>
        <div class="label-container">
            <input type="password" id="password" name="password" required>
            <span class="info-icon" onclick="openExternInfoSite('password')">?</span>
        </div>
        <br>

        <input type="submit" value="Einloggen">
        <br><br>
        <?php echo $loginMessage; ?>

    </form>
</div>

</body>
<script src="script.js"></script>
</html>
