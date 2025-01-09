<!DOCTYPE html>
<html lang="de">
<head>
    <title>WebUntis API</title>
    <link rel="stylesheet" href="style.css">

</head>
<body>

<?php



$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;


if ($username && $password) {
    require "functions.php";

    $conn = connectToDatabase();

    $isUserInDatabase =  writeOneArgToDatabase($username, "SELECT * FROM users WHERE username = ?");
    if (!$isUserInDatabase) {
        writeTwoArgToDatabase($username, $password, "INSERT INTO users (username, password) VALUES (?, ?)");
    }

    $loginMessage = '<p class="loginSucessful">Erfolgreich regestriert</p>';


    $conn->close();
    header("Location: settings.php");


    } else {
        $loginMessage = '';
    }



?>

    <form action="index.php" method="post">
        <h2>WebUntis API</h2>
        <h4>- Benachrichtigungen für Untis -</h4>

        <label for="username">Benutzername:</label>
        <input type="text" name="username" id="username" required>
        <br>
        <label for="password">Passwort:</label>
        <input type="password" name="password" id="password" required>
        <br>
        <input type="submit" value="Einloggen">
        <br><br>
        <?php echo $loginMessage; ?>

    </form>
</body>
</html>