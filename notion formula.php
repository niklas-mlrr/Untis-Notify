<?php

session_start();

require_once "functions.php";

$username = $_SESSION['username'] ?? null;
$password = $_SESSION['password'] ?? null;

if (!$username || !$password) {
    logOut();
}


$notionFormula = "";


try {
    $differences = [];
    $conn = connectToDatabase();


    $login = loginToWebUntis($username, $password, "");
    $students = getStudents($login, $username);
    $userId = getStudentIdByName($students, $username);

    // get the startOfWeek input
    if (isset($_GET['startOfWeek'])) {
        $startOfWeek = $_GET['startOfWeek'];
    
        // Check if the selected date is a Monday (1 = Monday in PHP's date function)
        $dayOfWeek = date('N', strtotime($startOfWeek));
        $isMonday = ($dayOfWeek == 1);
    
        if (!$isMonday) {
            $notionFormula = "Das eingegebene Datum ist kein Montag. Bitte wähle einen Montag.";
        } else {
            // Continue with your existing code for when it is a Monday
            $replacements = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username], $username);

            $timetableWeek = getTimetableWeek($startOfWeek, $login, $userId, $username, $replacements);
            list($subjectsThatAppearMultipleTimes, $day, $subject) = getSubjectsThatAppearMultipleTimes($timetableWeek);

            $notionFormula = getNotionFormula($subjectsThatAppearMultipleTimes);
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}



?>

<!DOCTYPE html>
<html lang="de">
<head>
    <title>Notion Formel</title>
    <meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="Favicon/apple-touch-icon-57x57.png" />
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="Favicon/apple-touch-icon-72x72.png" />
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="Favicon/apple-touch-icon-144x144.png" />
    <link rel="apple-touch-icon-precomposed" sizes="120x120" href="Favicon/apple-touch-icon-120x120.png" />
    <link rel="apple-touch-icon-precomposed" sizes="152x152" href="Favicon/apple-touch-icon-152x152.png" />
    <link rel="icon" type="image/png" href="Favicon/favicon-196x196.png" sizes="196x196" />
    <link rel="icon" type="image/png" href="Favicon/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="Favicon/favicon-16x16.png" sizes="16x16" />
    <meta name="application-name" content="Untis Notify"/>
    <meta name="msapplication-TileColor" content="#" />
    <meta name="msapplication-TileImage" content="Favicon/mstile-144x144.png" />

    <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="parent">

    <span class="loader" id="loading-animation" ></span>

    <form>
        <button id="toggle-theme" class="dark-mode-switch-btn">
            <img src="https://img.icons8.com/?size=100&id=648&format=png&color=0000009C" alt="Dark-mode-switch" class="dark-mode-switch-icon">
        </button>

        <button id="navigate-back-btn" class="navigate-back-btn">
            <img src="https://img.icons8.com/?size=100&id=26194&format=png&color=0000009C" alt="navigate-back-icon" class="navigate-back-icon">
        </button>

        <br>
        <h3>Deine Notion Hausaufgaben Due-Date Formel für dieses Halbjahr</h3><br>


        <p class="important-text">Damit die Formel richtig berechnet werden kann, muss in der Settings-Seite das Dictionary korrekt <br> (so wie die Fachnamen in Notion) gesetzt werden.</p><br>

        <ol class="left-aligned">
            <li>Kopiere die Formel unten vollständig</li>
            <li>Öffne Notion und gehe zu deiner Hausaufgabenseite</li>
            <li>Klicke bei einer beliebigen Ha auf "Öffnen", sodass diese in groß geöffnet ist</li>
            <li>Klappe die weiteren Eigenschaften aus</li>
            <li>Klicke auf die Eigenschaft "Due in Days from Created"</li>
            <li>Klicke auf "Eigenschaft bearbeiten" <br>➜  "Formel bearbeiten"</li>
            <li>(Lösche die alte Formel)</li>
            <li>Füge die neue Formel dort ein</li>
        </ol>
        <br><br>

        <h4>Wähle zunächst eine Woche im aktuellen Halbjahr, in welcher es keine Veranstalltungen und keine Feiertage gibt. <br> Wähle hier das Datum des Montags dieser Woche:</h4>
        <div class="notion-formula-input-container">
            <input type="date" id="startOfWeek" name="startOfWeek" class="date-input" placeholder="Datum" required>
            <button type="submit" class="btn-save-settings btn">Notion Formel berechnen</button>
        </div>


        <?php if (!empty($notionFormula)): ?>
        <p class="left-aligned" id="notionFormula"><?php echo $notionFormula; ?></p>

            <button type="button" id="copy-notion-formula-btn" class="btn-save-settings btn" onclick="copyNotionFormula()">Formel kopieren</button>
        <?php endif; ?>

    </form>
</div>
</body>
<script src="script.js"></script>
</html>
