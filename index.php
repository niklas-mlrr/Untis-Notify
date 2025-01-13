<!DOCTYPE html>
<html lang="de">
<head>
    <title>Untis Notify</title>
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
        $isUserInDatabase = writeOneArgToDatabase($username, "SELECT * FROM users WHERE username = ?");
        if (!$isUserInDatabase) {
            writeThreeArgToDatabase($username, $password, $schoolUrl, "INSERT INTO users (username, password, school_url) VALUES (?, ?, ?)");
        }

        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        $_SESSION['conn'] = $conn;
        $_SESSION['schoolUrl'] = $schoolUrl;


        $loginMessage = '<p class="sucessful">Erfolgreich regestriert</p>';
        $conn->close();
        header("Location: settings.php");
        exit();
    } else {
        $loginMessage = '<p class="failed">Fehler beim Einloggen</p>';
    }
} else {
    $loginMessage = '';
}
?>

<div class="img-div">
    <img class="notification-img" src="notification example.png" alt="Notification Example">
    <p class="notification-text">↑ Beispiel einer Benachrichtigung ↑</p>
</div>

<br>
<div class="parent">

    <form action="index.php" method="post">
        <h2>Untis Notify</h2>
        <h4>- Benachrichtigungen für Untis -</h4>


        <label for="schoolUrl">Schul-URL:</label>
        <div class="label-container">
            <input type="text" id="schoolUrl" name="schoolUrl" placeholder="https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode">
            <span class="info-icon" onclick="toggleInfo('info-schoolUrl')">?</span>
        </div>
        <div class="info-field" id="info-schoolUrl">Hier muss nichts eingegeben werden, wenn du auf dem TRG bist.</div>
        <br>

        <label for="pushoverUserKey">Untis Benutzername:</label>
        <div class="label-container">
            <input type="text" id="username" name="username" required>
            <span class="info-icon" onclick="openExternInfoSite('username')">?</span>
        </div>
        <br>

        <label for="pushoverUserKey">Untis Passwort:</label>
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