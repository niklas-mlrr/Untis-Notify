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
if ($username && $username) {
    require "functions.php";

    $login = loginToWebUntis($username, $password);
    if ($login) {
        $loginMessage = '<p class="loginSucessful">Erfolgreich eingeloggt</p>';
        $conn = connectToDatabase();

        $students = getStudents($login);
        $userId = getStudentIdByName($students, $username);

        $notificationForDaysInAdvance = 7;
        deleteDataFromDatabase("DELETE FROM timetables WHERE for_Date < ?");

        for($i = 0; $i < $notificationForDaysInAdvance; $i++) {
            $date = date("Ymd", strtotime("+$i days"));

            $timetable = getTimetable($login, $userId, $date);
            $formatedTimetable = getFormatedTimetable($timetable);

            $lastRetrieval = getDataFromDatabase("SELECT * FROM timetables where for_Date  = ?", $date);


            if($lastRetrieval == "0 results") {
                writeDataToDatabase($formatedTimetable, $date, "INSERT INTO timetables (timetableData, for_Date) VALUES (?, ?)");
                continue;
            }


            $compResult = compareArrays($lastRetrieval, $formatedTimetable);
            print_r($compResult);
            $result = interpreteResultDataAndSendNotification($compResult, $date);


            if ($result) {
                writeDataToDatabase($formatedTimetable, $date, "UPDATE timetables SET timetableData = ?, for_Date = ? WHERE for_Date = $date");
            }
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