<!DOCTYPE html>
<html lang="de">
<head>
    <title>Untis Notify</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.svg" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php
session_start();


$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$schoolUrl = $_POST['schoolUrl'] ?? null;

if ($username && $password && $schoolUrl) {
    require_once "functions.php";


    $login = loginToWebUntis($username, $password, $schoolUrl);
    if ($login) {

        try {
            $conn = connectToDatabase();
        } catch (Exception $e) {
            echo $e->getMessage();
            exit();
        }

        try {
            $isUserInDatabase = !empty(getRowsFromDatabase($conn, "users", ["username" => $username, "password" => $password]));
        } catch (Exception $e) {
            echo $e->getMessage();
            exit();
        }

        if (!$isUserInDatabase) {
            try {
                writeToDatabase($conn, [$username, $password, $schoolUrl], "INSERT INTO users (username, password, school_url) VALUES (?, ?, ?)");
            } catch (Exception $e) {
                echo $e->getMessage();
                exit();
            }
        }

        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['conn'] = $conn;
        $_SESSION['schoolUrl'] = $schoolUrl;


        date_default_timezone_set('Europe/Berlin');
        $currentTimestamp = date('Y-m-d h:i:s', time());
        try {
            writeToDatabase($conn, [$currentTimestamp, $username], "UPDATE users SET last_login = ? WHERE username = ?");
        } catch (Exception $e) {
            echo $e->getMessage();
            exit();
        }

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
<br><br>
<p class="notification-text">↑ Beispiel einer Benachrichtigung ↑</p>


<div class="parent">

    <button id="toggle-theme" class="dark-mode-switch-btn">
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
            <span class="info-icon" onclick="toggleInfo('info-schoolUrl')" onKeyDown="toggleInfo('info-schoolUrl')">?</span>
        </div>
        <div class="info-field" id="info-schoolUrl">
            <p>Dies ist eine schulspezifische URL. <br> Wenn du auf dem TRG bist, musst du hier nichts eingeben.</p>
        </div>
        <br>

        <label for="username">Untis Benutzername:</label>
        <div class="label-container">
            <input type="text" id="username" name="username" required>
            <span class="info-icon" onclick="openExternInfoSite('username')" onKeyDown="openExternInfoSite('username')">?</span>
        </div>
        <br>

        <label for="password">Untis Passwort:</label>
        <div class="label-container">
            <input type="password" id="password" name="password" required>
            <span class="info-icon" onclick="openExternInfoSite('password')" onKeyDown="openExternInfoSite('password')">?</span>
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
