<!DOCTYPE html>
<html lang="de">
<head>
    <title>WebUntis API</title>
    <link rel="stylesheet" href="style.css">

</head>
<body>

<?php
// $userId = 2995;
// $username = "MüllerNik";
// $password = "RFftJz1n9neBpn,";


$userId = $_POST['userid'] ?? null;
$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
if ($userId && $username && $username) {
    require "functions.php";

    $login = loginToWebUntis($username, $password);
    if ($login) {
        $loginMessage = '<p class="loginSucessful">Erfolgreich eingeloggt</p>';
        $conn = connectToDatabase();

        //echo "<pre>";

        $date = date("Ymd", strtotime("-22 days"));

        $timetable = getTimetable($login, $userId, $date);
        $formatedTimetable = getFormatedTimetable($timetable);

        $lastRetrieval = getDataFromDatabase("SELECT * FROM test_speicherung ORDER BY id DESC LIMIT 1;;");


        $compResult = compareArrays($lastRetrieval, $formatedTimetable);
        print_r($compResult);
        $result = interpreteResultData($compResult);

        if ($result){
            writeDataToDatabase("test_speicherung", $formatedTimetable);
        }


        $conn->close();

    } else {
        $loginMessage = '<p class="loginNotSucessful">Fehler beim Einloggen</p>';
    }
} else {
    $loginMessage = "";
}







?>

    <form action="index.php" method="post">
        <h2>WebUntis API</h2>
        <h4>- Benachrichtigungen für Untis -</h4>
        <label for="userid">User Id:</label>
        <input type="text" name="userid" id="userid" required>
        <br>
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