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
    //$username = "test";

    $conn = connectToDatabase();
    $baseUrl = getDataFromDatabase($username, "school_url", "SELECT school_url FROM users where username  = ?");


    $login = loginToWebUntis($username, $password, $baseUrl);
    if ($login) {


        $loginMessage = '<p class="loginSucessful">Erfolgreich eingeloggt</p>';



        $isUserInDatabase =  writeOneArgToDatabase($username, "SELECT * FROM users WHERE username = ?");

        if (!$isUserInDatabase) {
            writeTwoArgToDatabase($username, $password, "INSERT INTO users (username, password) VALUES (?, ?)");
        }




        $students = getStudents($login);
        $userId = getStudentIdByName($students, $username);

        $notificationForDaysInAdvance = 7;
        deleteDataFromDatabase("DELETE FROM timetables WHERE for_Date < ?");

        for($i = 0; $i < $notificationForDaysInAdvance; $i++) {
            $date = date("Ymd", strtotime("+$i days"));

            $timetable = getTimetable($login, $userId, $date);
            $formatedTimetable = getFormatedTimetable($timetable);

            $lastRetrieval = getDataFromDatabase($date, "timetableData", "SELECT * FROM timetables where for_Date  = ?");
            if ($lastRetrieval->num_rows > 0) {
                while ($row = $lastRetrieval->fetch_assoc()) {
                    $lastRetrieval =  json_decode($row["timetableData"], true);
                }
            } else {
                $lastRetrieval = "0 results";
            }

            if($lastRetrieval == "0 results") {
                writeTwoArgToDatabase($formatedTimetable, $date, "INSERT INTO timetables (timetableData, for_Date) VALUES (?, ?)");
                continue;
            }


            $compResult = compareArrays($lastRetrieval, $formatedTimetable);
            print_r($compResult);
            $result = interpreteResultDataAndSendNotification($compResult, $date);


            if ($result) {
                writeTwoArgToDatabase($formatedTimetable, $date, "UPDATE timetables SET timetableData = ?, for_Date = ? WHERE for_Date = $date");
            }
        }

        $conn->close();
        //header("Location: settings.php");

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