<?php

require_once "Logger.php";
require_once "config.php";
require_once "Exceptions/AuthenticationException.php";
require_once "Exceptions/DatabaseException.php";
require_once "Exceptions/UserException.php";
require_once "Exceptions/APIException.php";

use Exceptions\AuthenticationException;
use Exceptions\DatabaseException;
use Exceptions\UserException;
use Exceptions\APIException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';



const DATABASE_EXCEPTION_PREFIX = 'DatabaseException: ';
const CURL_ERROR_PREFIX = 'Curl error: ';
const PREPARE_FAILED_PREFIX = 'Prepare failed: ';


/**
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @return void
 */
function initiateCheck(mysqli $conn, string $username, string $password): void {
    $differences = [];

    try {
        $pwLoggingMode = getValueFromDatabase($conn, "settings", "pw_logging_mode", ["id" => 1], "Admin");
        $login = loginToWebUntis($username, $password, $pwLoggingMode);
        $students = getStudents($login, $username);
        $userId = getStudentIdByName($students, $username);
        $notificationForDaysInAdvance = getValueFromDatabase($conn, "users", "notification_for_days_in_advance", ["username" => $username], $username);
    } catch (AuthenticationException|DatabaseException|UserException) {
        return;
    } catch (APIException $e) {
        Logger::log("APIException: Students konnten nicht abgerufen werden, API Response: $e", $username);
        return;
    }

    $currentDate = date("Ymd");
    $maxDateToCheck = date("Ymd", strtotime("+$notificationForDaysInAdvance days"));

    try {
        deleteFromDatabase($conn, "timetables", ["for_date < ?"], [$currentDate], $username);
        deleteFromDatabase($conn, "timetables", ["for_date > ?", "user = ?"], [$maxDateToCheck, $username], $username);
    } catch (DatabaseException $e) {
        Logger::log("DATABASE_EXCEPTION_PREFIX; Alte Stundenplandaten nicht erfolgreich gelöscht; " . $e->getMessage(), $username);
    }

    for ($i = 0; $i < $notificationForDaysInAdvance; $i++) {
        $date = date("Ymd", strtotime("+$i days"));
        $differences = array_merge($differences, checkCompareAndUpdateTimetable($date, $conn, $login, $userId, $username));
    }


    sendEmails($differences, $username, $conn);
}

/**
 * @param mysqli $conn
 * @param string $date
 * @param string $login
 * @param int $userId
 * @param string $username
 * @param bool|null $secondRunOfFunction
 * @return array
 */
function checkCompareAndUpdateTimetable(string $date, mysqli $conn, string $login, int $userId, string $username, bool $secondRunOfFunction = false): array {
    try {
        $timetable = getTimetable($login, $userId, $date, $username);
        $replacements = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username], $username);
        $formatedTimetable = getFormatedTimetable($timetable, $replacements);
        $lastRetrieval = getValueFromDatabase($conn, "timetables", "timetable_data", ["for_date" => $date, "user" => $username], $username);

        $lastRetrieval = $lastRetrieval ? json_decode($lastRetrieval, true) : null;

        if (!$lastRetrieval) {
            if ($formatedTimetable != null) {
                insertIntoDatabase($conn, "timetables", ["timetable_data", "for_date", "user"], [$formatedTimetable, $date, $username], $username);
            }
            return [];
        }

    } catch (DatabaseException) {
        return [];
    } catch (APIException $e) {
        if($secondRunOfFunction) {
            Logger::log("APIException: Auch beim 2. Durchlauf konnten die Stundenplandaten nicht erfolgreich abgerufen werden; API Response: $e", $username);
            return [];
        } else {
            Logger::log("APIException: Stundenplandaten nicht erfolgreich abgerufen; API Response: $e", $username);
            return checkCompareAndUpdateTimetable($date, $conn, $login, $userId, $username, true);
        }
    }


    $compResult = compareArrays($lastRetrieval, $formatedTimetable, $date, $timetable);

    if ($compResult != null) {
        try {
            updateDatabase($conn, "timetables", ["timetable_data"], ["for_date = ?", "user = ?"], [$formatedTimetable, $date, $username], $username);
        } catch (DatabaseException) {
            Logger::log(DATABASE_EXCEPTION_PREFIX . "Stundenplandaten nicht erfolgreich aktualisiert", $username);
        }
    }
    return $compResult;
}


/**
 * @param string $username
 * @param string $affectedLessons
 * @param string $message
 * @param string $oldValue
 * @param string $miscellaneous
 * @param $date
 * @param mysqli $conn
 * @return bool
 * @throws DatabaseException
 * @throws UserException
 */
function sendEmail(string $username, string $affectedLessons, string $message, string $oldValue, string $miscellaneous, $date, mysqli $conn): bool {
    global $config;


    try {
        $recipientEmail = getValueFromDatabase($conn, "users", "email_adress", ["username" => $username], $username);
    } catch (DatabaseException $e) {
        throw new DatabaseException(DATABASE_EXCEPTION_PREFIX . $e->getMessage());
    }

    $weekday = match ((int)date('w', strtotime($date))) {
        0 => "So",
        1 => "Mo",
        2 => "Di",
        3 => "Mi",
        4 => "Do",
        5 => "Fr",
        6 => "Sa",
        default => " ",
    };

    $forDate = match ($date) {
        date("Ymd") => "Heute: ",
        date("Ymd", strtotime("+1 days")) => "Morgen: ",
        date("Ymd", strtotime("+2 days")) => "Übermorgen: ",
        default => $date ? $weekday . ", " . date("d.m", strtotime($date)) . ": " : " ",
    };


    $mail = new PHPMailer(true); // true enables exceptions


    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable verbose debug output
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['emailUsername'];
        $mail->Password = $config['emailPassword'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['emailUsername'], 'Untis Notify');
        $mail->addAddress($recipientEmail, $username);

        

        $mail->Subject = $forDate . $affectedLessons;
        $mail->Body = getEmailBody($message, $oldValue, $miscellaneous);

        $mail->AltBody = "$message; Vorher: $oldValue";

        $mail->send();

        $date = date("d.m.Y", strtotime($date));
        $exactDate = date("d.m.Y H:i:s");
        logNotificationToFile($exactDate, $date, $username, $affectedLessons, $message, $oldValue, $miscellaneous);
        return true;
    } catch (Exception) {
        Logger::log("Email could not be sent. Mailer Error: $mail->ErrorInfo", $username);
        throw new UserException("Email could not be sent. Mailer Error: $mail->ErrorInfo", 0);
    }
}



function getEmailBody($message, $oldValue, $miscellaneous): string {

    if($oldValue != "" && $message != "Veranstaltung") {
        $oldValue = "Vorher: " . $oldValue;
    }


    // Create a string with zero-width characters to only show certain information in the email preview
    $previewStopper = str_repeat("&#847; ", 100);

    $emailBody = '
        <html lang="de">
        <head>
        <title>Untis Notify</title>
            <style>
                .header {
                    font-size: 20px;
                    font-weight: bold;
                    color: #333;
                }
                .content {
                    font-size: 16px;
                    color: #555;
                }
                .footer {
                    font-size: 12px;
                    color: #999;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>';


    if ($message == "Testbenachrichtigung") {
        $emailBody .= '
            <div class="header">Wenn du das hier liest, hast du alles richtig gemacht!' . $previewStopper . '</div>
            <div class="content">
                <p>Ab sofort erhältst du Benachrichtigungen, wenn es Änderungen in deinem Untis Stundenplan gibt. Alle 10 Min. wird dieser überprüft.</p>
                <p>Forder, Förder und Chor werden hierbei nicht berücksichtigt, da diese nicht im "persönlichen" Stundenplan auf Untis, sondern nur in dem für die gesamte Klasse stehen. <br>In Einzelfällen, wie z.B. an Jokertagen, kann es vorkommen, dass nicht alles richtig verarbeitet werden kann.</p>
                <p>Bei Fehlern oder Fragen, mir gerne schreiben.</p>
            </div>
        ';
    } else {
        $emailBody .= '
            <div class="header">
            <p>' . $message . $previewStopper . '<p/>
            </div>
            <div class="content">
            <p>' . $oldValue . '</p>
            <p>' . $miscellaneous . '</p>
            </div>
            ';
    }

    $emailBody .= '
                <br><br><br><br>
            <hr>
            <div class="footer">
                <p>Kontakt: <a href="mailto:info@untis-notify.de">info@untis-notify.de</a></p>
                <p>Klicke <a href="https://untis-notify.de/login?messageToUser=howToChangeOrDisableNotifications" target="_blank">hier</a>, um diese Benachrichtigungsemails von Untis Notify abzubestellen oder zu ändern</p>
            </div>
        </body>
    </html>';

    return $emailBody;
}







/**
 * @param $dateSent
 * @param $forDate
 * @param string $username
 * @param string $affectedLessons
 * @param string $message
 * @param string $oldValue
 * @param string $miscellaneous
 * @return void
 */
function logNotificationToFile($dateSent, $forDate, string $username, string $affectedLessons, string $message, string $oldValue, string $miscellaneous): void {
    $logEntry = sprintf(
        "[%s] ForDate: %s, Username: %s, AffectedLessons: %s, Message: %s, OldValue: %s, Miscellaneous: %s\n",
        $dateSent,
        $forDate,
        $username,
        $affectedLessons,
        $message,
        $oldValue,
        $miscellaneous,
    );

    $currentYear = date("Y");
    $currentMonth = date("m");

    $logDir = __DIR__ . "/Logs/$currentYear/$currentMonth";
    $logFile = $logDir . '/' . date('Y-m-d') . '-notifications.log';
    if(!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}


/**
 * Logs into the WebUntis API and returns the session ID
 *
 * @param string $username The username to log in with
 * @param string $password The password to log in with
 * @return string The session ID
 * @throws AuthenticationException
 */
function loginToWebUntis(string $username, string $password, $pwLoggingMode): string {
    $loginPayload = [
        "id" => "login",
        "method" => "authenticate",
        "params" => [
            "user" => $username,
            "password" => $password,
        ],
        "jsonrpc" => "2.0"
    ];

    $ch = curl_init("https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        curl_close($ch);
        Logger::log(CURL_ERROR_PREFIX . $error, $username);
        throw new AuthenticationException(CURL_ERROR_PREFIX . $error);
    }

    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result']['sessionId'])) {
        return $result['result']['sessionId'];
    }

    if ($pwLoggingMode) {
        Logger::log("AuthenticationException: Untis Login failed. Response: " . json_encode($result), $username, $password);
    } else {
        Logger::log("AuthenticationException: Untis Login failed. Response: " . json_encode($result), $username);
    }
    throw new AuthenticationException("Untis Login failed. Response: " . json_encode($result));
}

/**
 * @param string $sessionId
 * @param array $payload
 * @param string $username
 * @return array
 * @throws APIException
 */
function sendApiRequest(string $sessionId, array $payload, string $username): array {
    $url = "https://niobe.webuntis.com/WebUntis/jsonrpc.do?school=gym-osterode";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: JSESSIONID=' . $sessionId
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        curl_close($ch);
        Logger::log(CURL_ERROR_PREFIX . $error, $username);
        throw new APIException(CURL_ERROR_PREFIX . $error);
    }

    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result'])) {
        return $result['result'];
    }

    Logger::log(CURL_ERROR_PREFIX . "API request failed. Response: " . json_encode($result), $username);
    throw new APIException("API request failed. Response: " . json_encode($result));
}


/**
 * @param string $sessionId
 * @param int $userId
 * @param string $date
 * @param string $username
 * @return array
 * @throws APIException
 */
function getTimetable(string $sessionId, int $userId, string $date, string $username): array {
    $payload = [
        "id" => "getTimetable",
        "method" => "getTimetable",
        "params" => [
            "options" => [
                "element" => [
                    "id" => $userId,
                    "type" => 5 // Für Schüler, 2 für Lehrer
                ],
                "startDate" => $date,
                "endDate" => $date,
                "showLsText" => true,
                "showStudentgroup" => true,
                "showLsNumber" => true,
                "showSubstText" => true,
                "showInfo" => true,
                "showBooking" => true,
                "klasseFields" => ['id', 'name', 'longname', 'externalkey'],
                "roomFields" => ['id', 'name', 'longname', 'externalkey'],
                "subjectFields" => ['id', 'name', 'longname', 'externalkey'],
                "teacherFields" => ['id', 'name', 'longname', 'externalkey'],
            ]
        ],
        "jsonrpc" => "2.0"
    ];

    try {
        return sendApiRequest($sessionId, $payload, $username);
    } catch (APIException $e) {
        throw new APIException($e->getMessage());
    }
}

/**
 * @param string $sessionId
 * @param string $username
 * @return array
 * @throws APIException
 */
function getStudents(string $sessionId, string $username): array {
    $payload = [
        "id" => "getStudents",
        "method" => "getStudents",
        "params" => [],
        "jsonrpc" => "2.0"
    ];
    try {
        return sendApiRequest($sessionId, $payload, $username);
    } catch (APIException $e) {
        throw new APIException($e->getMessage());
    }
}


/**
 * @param array $studentArray
 * @param string $name
 * @return string
 * @throws UserException
 */
function getStudentIdByName(array $studentArray, string $name): string {
    foreach ($studentArray as $student) {
        if ($student['name'] === $name) {
            return $student['id'];
        }
    }
    Logger::log("Student not found: $name");
    throw new UserException("Student not found: $name");
}


$startTimes = [
    "745" => 1,
    "835" => 2,
    "940" => 3,
    "1025" => 4,
    "1130" => 5,
    "1215" => 6,
    "1330" => 7,
    "1415" => 8,
    "1510" => 9,
    "1555" => 10
];

$endTimes = [
    "830" => 1,
    "920" => 2,
    "1025" => 3,
    "1110" => 4,
    "1215" => 5,
    "1300" => 6,
    "1415" => 7,
    "1500" => 8,
    "1555" => 9,
    "1645" => 10
];


function cmp($a, $b) {
    global $config;
    $adminUsername = $config['adminUsername'];

    try {
        if (!isset($a['lessonNum']) || !isset($b['lessonNum']) || !is_int($a['lessonNum']) || !is_int($b['lessonNum'])) {
            throw new Exception();
        }
        return $a['lessonNum'] - $b['lessonNum'];
    } catch (Exception) {
        try {
            Logger::log("Error in cmp function");
            $conn = connectToDatabase();
            sendEmail($adminUsername, "Error in cmp function", "Stundenplanzeiten der Schule haben sich wahrscheinlich geändert", "", "", date("Ymd"), $conn);
            exit();
        } catch (DatabaseException|UserException) {
            return 0;
        }

    }
}


/**
 * @param string $replacements
 * @return array
 */
// Der ReplacementArray wird jedes Mal neu generiert,
// da die direkte Benutzereingabe in der Db gespeichert wird,
// da diese jederzeit vom Benutzer änderbar sein muss.
function generateReplacementArray(string $replacements): array {
    if (!$replacements) {
        return [];
    }

    if (str_ends_with($replacements, ";")) {
        $replacements = substr($replacements, 0, -1);
    }
    $replacements = explode(";", $replacements);

    foreach ($replacements as $replacement) {
        $replacement = explode("=", $replacement);
        $replacements[$replacement[0]] = $replacement[1];
    }
    array_splice($replacements, 0, count($replacements) / 2);


    $replacements = array_map(function ($value, $key) {
        return [trim($key) => trim($value)];
    }, $replacements, array_keys($replacements));

    return array_merge(...$replacements);

}


/**
 * Ersetzt vordefinierte Wörter im subject durch andere vordefinierte Wörter
 * @param string $subject Das zu überprüfende Fach
 * @param string $replacements Das Array mit den Ersetzungen
 * @return string Das ersetzte Fach
 */
function replaceSubjectWords(string $subject, string $replacements): string {
    $replacements = generateReplacementArray($replacements);
    if (!$replacements) {
        return $subject;
    }
    return str_replace(array_keys($replacements), array_values($replacements), $subject);
}


/**
 * @param array $timetable
 * @param string $replacements
 * @return array
 */
function getFormatedTimetable(array $timetable, string $replacements): array {
    global $startTimes;
    $timetable = removeDuplicateLessons($timetable);
    $timetable = removeCancelledLessonsWhileIrregular($timetable);

    $numOfLessons = count($timetable);
    $formatedTimetable = [];

    for ($i = 0; $i < $numOfLessons; $i++) {
        $lessonNum = $startTimes[$timetable[$i]["startTime"]] ?? "notSet";


        $lesson = [
            "code" => $timetable[$i]["code"] ?? "",
            "lessonNum" => $lessonNum,
            "endTime" => $timetable[$i]["endTime"] ?? "notSet",
            "subject" => $timetable[$i]["su"][0]["longname"] ?? (($timetable[$i]["code"] == "irregular") ? "Veranstaltung" : "notSet"),
            "teacher" => $timetable[$i]["te"][0]["name"] ?? "notSet",
            "room" => $timetable[$i]["ro"][0]["name"] ?? "notSet",
            "notesForStundets" => $timetable[$i]["info"] ?? "notSet",
            "substituteText" => $timetable[$i]["substText"] ?? "notSet",
        ];


        $lesson["subject"] = replaceSubjectWords($lesson["subject"], $replacements);

        $formatedTimetable[] = $lesson;
    }

    usort($formatedTimetable, "cmp");

    return $formatedTimetable;
}



function removeDuplicateLessons($timeTable): array {
    $newTimeTable = [];
    $lessonNums = [];

    foreach ($timeTable as $lesson) {
        $lessonNum = $lesson["startTime"];
        if (in_array($lessonNum, $lessonNums)) {
            $indexOfExistingInstance = array_search($lessonNum, array_column($newTimeTable, "startTime"));
            $indexOfFirstInstance = array_search($lessonNum, array_column($timeTable, "startTime"));
            $indexOfSecondInstance = array_search($lessonNum, array_column(array_slice($timeTable, $indexOfFirstInstance + 1), "startTime")) + $indexOfFirstInstance + 1;

            $lesson = chooseNotCanceledLessonForDuplicateLessons($timeTable[$indexOfFirstInstance], $timeTable[$indexOfSecondInstance]);
            unset($newTimeTable[$indexOfExistingInstance]);
        } else {
            $lessonNums[] = $lessonNum;
        }
        $newTimeTable[] = $lesson;
    }

    return array_values($newTimeTable);
}

function chooseNotCanceledLessonForDuplicateLessons(array $lesson1, array $lesson2): ?array {
    // First, check if either lesson has an "irregular" code - prioritize these
    $irregular1 = ($lesson1['code'] == "irregular") ? 1 : 0;
    $irregular2 = ($lesson2['code'] == "irregular") ? 1 : 0;
    
    // If one of them is irregular, return it
    if ($irregular1 && !$irregular2) {
        return $lesson1;
    } elseif (!$irregular1 && $irregular2) {
        return $lesson2;
    }
    
    // If neither or both are irregular, fall back to the canceled check logic
    $canceled1 = ($lesson1['code'] == "cancelled") ? 1 : 0;
    $canceled2 = ($lesson2['code'] == "cancelled") ? 1 : 0;

    if (!$canceled1) {
        return $lesson1;
    } elseif (!$canceled2) {
        return $lesson2;
    } else {
        return $lesson1;
    }
}


function removeCancelledLessonsWhileIrregular(array $timeTable): array {
    $newTimeTable = [];
    $startTimeOfIrregularLesson = "";
    $endTimeOfIrregularLesson = "";
    foreach ($timeTable as $lesson) {
        if (isset($lesson["code"]) && $lesson["code"] == "irregular") {
            $startTimeOfIrregularLesson = $lesson["startTime"];
            $endTimeOfIrregularLesson = $lesson["endTime"];
            break;
        }
    }

    foreach ($timeTable as $lesson) {
        if (isset($lesson["code"]) && $lesson["code"] == "cancelled" && $lesson["endTime"] <= $endTimeOfIrregularLesson && $lesson["startTime"] >= $startTimeOfIrregularLesson) {
            continue;
        }
        $newTimeTable[] = $lesson;
    }
    return $newTimeTable;

}










/**
 * Finds irregular lessons in the timetable and returns their details
 * @param array $array2 Current timetable array
 * @return array Array containing irregular lesson details [startLessonNum, endLessonNum, endTime, differences]
 */
function findIrregularLessons(array $array2, $timetable): array {
    global $startTimes;
    global $endTimes;
    
    $irregularDifferences = [];
    $irregularStartLessonNum = "";
    $irregularEndLessonNum = "";
    $irregularEndTime = null;
    $irregularLessonText = "";

    // Find if there's an irregular lesson and get its end time
    foreach ($array2 as $item) {
        if (isset($item['code']) && $item['code'] == "irregular") {
            $irregularStartLessonNum = $item['lessonNum'];

            $irregularEndTime = $item['endTime'];

            foreach ($timetable as $lesson) {
            if ($startTimes[$lesson["startTime"]] == $irregularStartLessonNum && $lesson["code"] == "irregular" && !empty($lesson["lstext"])) {
                $irregularLessonText = "Informationen zur Stunde: <br>" . $lesson["lstext"];
            }

            }

            // Insert ":" as a seperator from the hour and minutes
            $irregularEndLessonNum = strrev($irregularEndTime);
            $irregularEndLessonNum = $endTimes[$irregularEndTime] ?? substr($irregularEndLessonNum, 0, 2) . ":" . substr($irregularEndLessonNum, 2);
            $irregularEndLessonNum = strrev($irregularEndLessonNum);



            if ($irregularEndLessonNum - $irregularStartLessonNum == 1) {
                $affectedLessons = "$irregularStartLessonNum. & $irregularEndLessonNum. Stunde";
            } elseif (($irregularEndLessonNum - $irregularStartLessonNum) > 2) {
                $affectedLessons = "$irregularStartLessonNum. - $irregularEndLessonNum. Stunde";
            } else {
                $affectedLessons = "$irregularStartLessonNum. Stunde";
            }

            // Die Message muss "Veranstaltung" bleiben. Oder es muss die entsprechende Referenz (if-Statement) in combineNotifications() geändert werden.
            $irregularDifferences[] = createDifference("Veranstaltung", $affectedLessons, "sonstiges", "", $irregularLessonText);
            break;
        }
    }

    return [
        'startLessonNum' => $irregularStartLessonNum,
        'endLessonNum' => $irregularEndLessonNum,
        'endTime' => $irregularEndTime,
        'differences' => $irregularDifferences
    ];
}

/**
 * @param $array1 (= lastRetrieval)
 * @param $array2 (= formatedTimetable)
 * @param $date
 * @param $timetable
 * @return array
 */
function compareArrays(array $array1, array $array2, $date, $timetable): array {



    $differences = [];
    $fachwechselLessons = [];

    // Find irregular lessons
    $irregularInfo = findIrregularLessons($array2, $timetable);
    $irregularDifferencs = $irregularInfo['differences'];
    $irregularStartLessonNum = $irregularInfo['startLessonNum'];
    $irregularEndTime = $irregularInfo['endTime'];


    list($canceledDifferences, $canceledLessons) = findCanceledItems($array1, $array2);

    // Only add cancellation notifications if they aren't before/during an irregular lesson
    if ($irregularEndTime) {
        $filteredCancelDifferences = [];
        foreach ($canceledDifferences as $difference) {
            // Extract lesson number from the affectedLessons string
            preg_match('/(\d+)\./', $difference['affectedLessons'], $matches);
            if (isset($matches[1])) {
                $lessonNum = (int)$matches[1];

                // Get the original lesson to check its end time
                foreach ($array1 as $item) {
                    if (isset($item['lessonNum']) && $item['lessonNum'] == $lessonNum) {
                        // Only add if the lesson's end time is after the irregular lesson's end time
                        if (!isset($item['endTime']) || ($item['endTime'] <= $irregularEndTime && $item['lessonNum'] >= $irregularStartLessonNum)) {
                            $filteredCancelDifferences[] = $difference;
                        }
                        break;
                    }
                }
            }
        }
        $canceledDifferencesWithInRangeOfIrregular = $filteredCancelDifferences;


        // Filtere $canceledDifferences, um nur Einträge hinzuzufügen, die nicht in $canceledDifferencesWithInRangeOfIrregular enthalten sind
        $canceledDifferences = array_filter($canceledDifferences, function ($difference) use ($canceledDifferencesWithInRangeOfIrregular) {
            // Überspringe diesen Eintrag
            if (customArrayAny($canceledDifferencesWithInRangeOfIrregular, fn($irregularDifference) => $difference['message'] === $irregularDifference['message'] &&
                $difference['affectedLessons'] === $irregularDifference['affectedLessons'] &&
                $difference['typeOfNotification'] === $irregularDifference['typeOfNotification'] &&
                $difference['oldValue'] === $irregularDifference['oldValue'])) {
                return false;
            }
            return true; // Behalte diesen Eintrag
        });
    }

    $differences = array_merge($differences, $canceledDifferences);
    $differences = array_merge($differences, $irregularDifferencs);



    $differences = array_merge($differences, findChangedItems($array1, $array2, $canceledLessons, $fachwechselLessons));
    $differences = array_merge($differences, findNewItems($array1, $array2));

    $differences = combineNotifications($differences);

    $differences = removeDifferencesInsideIrregularTimespan($differences, $irregularInfo);

    // Add the for_date to each entry
    foreach ($differences as $key => $difference) {
        $differences[$key]['date'] = $date;
    }

    return $differences;
}
/**
 * @param array $array
 * @param callable $callback
 * @return bool
 */
function customArrayAny(array $array, callable $callback): bool {
    foreach ($array as $item) {
        if ($callback($item)) {
            return true;
        }
    }
    return false;
}


/**
 * @param array $differences
 * @param array $irregularInfo
 * @return array
 */
function removeDifferencesInsideIrregularTimespan(array $differences, array $irregularInfo): array {
    // If there's no irregular timespan, return original differences
    if (empty($irregularInfo['startLessonNum']) || empty($irregularInfo['endLessonNum'])) {
        return $differences;
    }
    
    $filteredDifferences = [];
    
    foreach ($differences as $difference) {
        // Extract the lesson number from the affected lessons string
        preg_match('/^(\d+)\./', $difference['affectedLessons'], $matches);
        
        if (isset($matches[1])) {
            $lessonNum = (int)$matches[1];
            
            // Only keep differences that are outside the irregular timespan or are about the irregular event itself
            if ($lessonNum < $irregularInfo["startLessonNum"] || $lessonNum > $irregularInfo["endLessonNum"] || $difference['message'] == "Veranstaltung") {
                $filteredDifferences[] = $difference;
            }
        } else {
            // If we can't extract a lesson number, keep the difference to be safe
            $filteredDifferences[] = $difference;
        }
    }
    
    return $filteredDifferences;
}



function findCanceledItems($array1, $array2): array {


    $differences = [];
    $canceledLessons = [];

    foreach ($array1 as $key => $item) {
        // Find corresponding item in array2 by lessonNum instead of using array index
        $matchingItem = null;
        foreach ($array2 as $item2) {
            if (isset($item['lessonNum']) && isset($item2['lessonNum']) && $item['lessonNum'] == $item2['lessonNum']) {
                $matchingItem = $item2;
                break;
            }
        }

        if ($matchingItem) {


            foreach ($item as $subKey => $value) {
                if ($subKey == "code" && $matchingItem[$subKey] == "cancelled" && $value != "cancelled") {

                    $itemToUseForText = $array2[$item['lessonNum']] ?? "";

                    $differences[] = createDifference("Ausfall", "{$item['lessonNum']}. Stunde {$item['subject']}", "ausfall", "", decideWhatTextToUse($itemToUseForText));
                    $canceledLessons[] = $item['lessonNum'];
                    break;
                }
            }
        }
    }
    return [$differences, $canceledLessons];
}





function findChangedItems($array1, $array2, $canceledLessons, &$fachwechselLessons): array {
    $differences = [];
    
    // Create a lookup array for array2 items indexed by lessonNum
    $array2ByLessonNum = [];
    foreach ($array2 as $item) {
        if (isset($item['lessonNum'])) {
            $array2ByLessonNum[$item['lessonNum']] = $item;
        }
    }
    
    foreach ($array1 as $item) {
        if (!isset($item['lessonNum'])) {
            continue;
        }
        
        $lessonNum = $item['lessonNum'];
        
        // Skip if this lesson is already marked as canceled or has a subject change
        if (in_array($lessonNum, $canceledLessons) || in_array($lessonNum, $fachwechselLessons)) {
            continue;
        }
        
        // Check if this lesson exists in array2
        if (isset($array2ByLessonNum[$lessonNum])) {
            $item2 = $array2ByLessonNum[$lessonNum];
            
            foreach ($item as $subKey => $value) {
                if ($value != "notSet" && !isset($value)) {
                    $differences[] = createDifference("Sonderfall: Eigenschaft \"$subKey\" fehlt", "{$item['lessonNum']}. Stunde", "sonstiges");
                } elseif (isset($item2[$subKey]) && $item2[$subKey] !== $value) {
                    if(!in_array($item['lessonNum'], $fachwechselLessons)) {
                        $differences[] = handleDifference($subKey, $value, $item2[$subKey], $item, $fachwechselLessons, $array2);
                    }
                }
            }
        }
    }

    return array_filter($differences, function ($difference) {
        return $difference !== null;
    });
}

function findNewItems($array1, $array2): array {
    $differences = [];
    
    // Create a lookup array for array1 items indexed by lessonNum
    $array1ByLessonNum = [];
    foreach ($array1 as $item) {
        if (isset($item['lessonNum'])) {
            $array1ByLessonNum[$item['lessonNum']] = $item;
        }
    }
    
    foreach ($array2 as $item) {
        if (!isset($item['lessonNum'])) {
            continue;
        }
        
        // Check if this lesson doesn't exist in array1
        if (!isset($array1ByLessonNum[$item['lessonNum']])) {
            $differences[] = createDifference("Neues Fach bei {$item['teacher']} in Raum {$item['room']}", "{$item['lessonNum']}. Stunde {$item['subject']}", "sonstiges");
        }
    }
    
    return $differences;
}

function createDifference($message, $affectedLessons, $typeOfNotification, $oldValue = "", $miscellaneous = ""): array {
    return ['message' => $message, 'affectedLessons' => $affectedLessons, 'typeOfNotification' => $typeOfNotification, 'oldValue' => $oldValue, 'miscellaneous' => $miscellaneous];
}

function handleDifference($subKey, $value, $newValue, $item, &$fachwechselLessons, $array2): ?array {
    $lessonNum = $item['lessonNum'];
    $subject = $item['subject'];

    if($subKey == "subject") {
        $fachwechselLessons[] = $lessonNum;
    }

    $itemToUseForText = $array2[$item['lessonNum']] ?? "";

    return match ($subKey) {
        // If "code" previously was ..., then ...
        "code" => match ($value) {
            "cancelled" => createDifference("Jetzt kein Ausfall mehr", "$lessonNum. Stunde $subject", "ausfall"),
            "" => createDifference("Ausfall", "$lessonNum. Stunde $subject", "ausfall", "", decideWhatTextToUse($itemToUseForText)),
            default => null,
        },
        "teacher" => $newValue == "---" ? createDifference("Lehrer ausgetragen", "$lessonNum. Stunde $subject", "sonstiges", $value) : createDifference("Vertretung bei $newValue", "$lessonNum. Stunde $subject", "vertretung", $value, decideWhatTextToUse($itemToUseForText)),
        "room" => $newValue == "---" ? createDifference("Raum ausgetragen", "$lessonNum. Stunde $subject", "sonstiges", $value) : createDifference("Raumänderung zu $newValue", "$lessonNum. Stunde $subject", "raumänderung", $value),
        "subject" => $newValue == "---" ? createDifference("Fach ausgetragen", "$lessonNum. Stunde $subject", "sonstiges", $value) : createDifference("Fachwechsel zu $newValue", "$lessonNum. Stunde", "sonstiges", $value, " <br> Es könnte sein, dass sich auch Lehrer oder Raum geändert haben"),
        default => null,
    };
}

function decideWhatTextToUse($item): string {
    if($item == null) {
        return "";
    }

    $notesforStudents = $item['notesForStundets'];
    $substituteText = $item['substituteText'];

    if($notesforStudents != "notSet" && $substituteText != "notSet") {
        return "Notizen für Schüler: <br>$notesforStudents <br><br>Vertungstext: <br>$substituteText";
    } elseif ($notesforStudents != "notSet") {
        return "Notizen für Schüler: <br>$notesforStudents";
    } elseif ($substituteText != "notSet") {
        return "Vertretungstext: <br>$substituteText";
    } else {
        return "";
    }
}


/**
 * @param $differences
 * @return array
 */
function combineNotifications($differences): array {
    if (empty($differences)) {
        return [];
    }



    usort($differences, function ($a, $b) {
        return strcmp($a['message'], $b['message']);
    });


    $differencesOnlyLessonNum = [];
    $differencesWithoutLessonNum = [];

    foreach ($differences as $difference) {

        $differencesOnlyLessonNum[] = $difference["affectedLessons"][0];
        $difference["affectedLessons"] = substr($difference["affectedLessons"], 1);
        $differencesWithoutLessonNum[] = $difference;
    }

    for ($i = 0; $i < count($differencesWithoutLessonNum) - 1; $i++) {
        if ($differencesWithoutLessonNum[$i]['message'] == $differencesWithoutLessonNum[$i + 1]['message'] && $differencesWithoutLessonNum[$i]['affectedLessons'] == $differencesWithoutLessonNum[$i + 1]['affectedLessons'] && $differencesWithoutLessonNum[$i]['typeOfNotification'] == $differencesWithoutLessonNum[$i + 1]['typeOfNotification'] && $differencesWithoutLessonNum[$i]['oldValue'] == $differencesWithoutLessonNum[$i + 1]['oldValue']) {
            $differences[$i]['affectedLessons'] = $differencesOnlyLessonNum[$i] . ". & " . $differencesOnlyLessonNum[$i + 1] . $differencesWithoutLessonNum[$i]['affectedLessons'];
            unset($differences[$i   + 1]);
        }
    }
    return $differences;
}






function sendEmails($differences, $username, $conn): void {
    if (empty($differences)) {
        return;
    }
    try {
        $receiveNotificationsFor = trim(getValueFromDatabase($conn, "users", "receive_notifications_for", ["username" => $username], $username));
        $receiveNotificationsFor = explode(", ", $receiveNotificationsFor);

        $differences = array_filter($differences, function ($difference) use ($receiveNotificationsFor) {
            return in_array($difference['typeOfNotification'], $receiveNotificationsFor);
        });
    } catch (DatabaseException) {
        return;
    }

    $differencesCount = count($differences);

    if ($differencesCount >= 20) {
        $differences = json_encode($differences);
        Logger::log("Zu viele Benachrichtigungen ($differencesCount): $differences", $username);
        $differences = [];
        $differences[] = createDifference("Das System wollte gerade $differencesCount Benachrichtigungen zu dir senden. Durch einen Sicherheitsmechanismus wurden diese abgefangen. Bitte wende dich an den Admin um zu erfahren, warum dir so viele Benachrichtigungen gesendet werden sollten.", "Zu viele Benachrichtigungen", "ausfall");
        $differences[0]['date'] = date("Ymd"); // Add date for this special case
    }

    foreach ($differences as $difference) {
        try {
            sendEmail($username, $difference['affectedLessons'], $difference['message'], $difference['oldValue'], $difference["miscellaneous"], $difference['date'], $conn);
        } catch (DatabaseException|UserException){
            continue;
        }
    }
}


/**
 * @param $case
 * @return string
 */
function getMessageText($case): string {
    return match ($case) {
        "loginFailed" => '<p class="failed">Fehler beim Einloggen</p>',
        "loginFailedBadCredentials" => '<p class="failed">Fehler beim Einloggen.<br><br>
        Hier sind nicht die IServ, <br>sondern die Untis Login-Daten nötig.<br><br>
        Wenn du nicht weißt, <br>wie du diese erhalten kannst, <br>klicke auf die "?".</p>',
        "settingsSavedSuccessfully" => '<p class="successful">Einstellungen erfolgreich gespeichert</p>',
        "settingsSavedSuccessfullyAndReferToEmail" => '<p class="successful">Einstellungen erfolgreich gespeichert. <br> Du musst jedoch oben noch deine Email-Adresse angeben, zu welcher die Benachrichtigungen kommen sollen.</p>',
        "settingsSavedSuccessfullyAndHowToContinue" => '<p class="successful">Einstellungen erfolgreich gespeichert. <br> Um die Benachrichtigungen zu aktivieren, klicke auf "Testbenachrichtigungen senden".</p>',
        "settingsNotSaved" => '<p class="failed">Fehler beim Speichern der Einstellungen</p>',
        "accountDeletedSuccessfully" => '<p class="successful">Konto erfolgreich gelöscht</p>',
        "accountNotDeleted" => '<p class="failed">Fehler beim Löschen des Kontos</p>',
        "testNotificationSent" => '<p class="successful">Testbenachrichtigungen erfolgreich gesendet. <br> Somit ist das Setup erfolgreich abgeschlossen und ab jetzt wird regelmäßig überprüft, ob es Änderungen für dich gibt.</p>',
        "testNotificationNotSent" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung</p>',
        "testNotificationNotSentInvalidEmail" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung. <br><br> Bitte überprüfe deine Email-Adresse, speichere und versuche es erneut.</p>',
        "dbError" => '<p class="failed">Fehler beim Abrufen der Daten aus der Datenbank</p>',
        "dbConnError" => '<p class="failed">Fehler beim Herstellen der Verbindung zur Datenbank</p>',
        "emptyFields" => '<p class="failed">Bitte fülle alle Felder aus</p>',
        "messageSentSuccessfully" => '<p class="successful">Nachricht erfolgreich gesendet</p>',
        "messageNotSent" => '<p class="failed">Fehler beim Senden der Nachricht</p>',
        "notificationOrDictionaryError" => '<p class="failed">Einstellungen nicht gespeichert. <br> Entweder hast du nicht mindestens eine Benachrichtigungsart ausgewählt oder das Dictionary nicht im korrekten Format angegeben.</p>',
        "receiveNotificationsForError" => '<p class="failed">Fehler beim Setzen der Benachrichtigungsarten</p>',
        "noEmailAdress" => '<p class="failed">Zuerst musst du oben deine Email-Adresse angeben, zu welcher die Benachrichtigungen kommen sollen und speichern.</p>',
        "howToChangeOrDisableNotifications" => '<p class="successful">Um die Benachrichtigungen zu ändern oder abzubestellen, melde dich erneut an und ändere deine Einstellungen oder lösche dein Konto.</p>',
        default => "",
    };
}


/**
 * @param string|null $messageToUser
 * @return void
 */
function logOut(?string $messageToUser = null): void {
    if($messageToUser) {
        $messageToUser = "?messageToUser=$messageToUser";
    }
    setcookie(session_name(), '', 100);
    session_unset();
    session_destroy();
    $_SESSION = array();
    header("Location: login$messageToUser");
}


/**
 * @return mysqli
 * @throws DatabaseException
 */
function connectToDatabase(): mysqli {
    global $config;
    $servername = $config['servername'];
    $username = $config['username'];
    $password = $config['password'];
    $database = $config['database'];

    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        Logger::log("Db Connection failed: $conn->connect_error");
        throw new DatabaseException("Db Connection failed: $conn->connect_error");
    }
    return $conn;
}

/**
 * Holt Zeilen aus der Datenbank basierend auf den angegebenen Bedingungen
 *
 * @param mysqli $conn
 * @param string $table
 * @param array $inputsAndConditions
 * @param string $username
 * @return array
 * @throws DatabaseException
 */
function getRowsFromDatabase(mysqli $conn, string $table, array $inputsAndConditions, string $username): array {
    $conditions = [];
    $inputs = [];

    foreach ($inputsAndConditions as $key => $value) {
        $conditions[] = "$key = ?";
        $inputs[] = $value;
    }

    $whereClause = implode(' AND ', $conditions);
    $query = "SELECT * FROM $table WHERE $whereClause";

    $result = prepareDbRequestAndReturnData($conn, $query, $username, $inputs);
    return $result->fetch_all(MYSQLI_ASSOC);

}


/**
 * Holt einen Wert aus der Datenbank basierend auf den angegebenen Bedingungen
 *
 * @param mysqli $conn
 * @param string $table
 * @param string $dataFromColumn
 * @param array $inputsAndConditions
 * @param string $username
 * @return string|null
 * @throws DatabaseException
 */
function getValueFromDatabase(mysqli $conn, string $table, string $dataFromColumn, array $inputsAndConditions, string $username): ?string {
    $conditions = [];
    $inputs = [];

    foreach ($inputsAndConditions as $key => $value) {
        $conditions[] = "$key = ?";
        $inputs[] = $value;
    }

    $whereClause = implode(' AND ', $conditions);
    $query = "SELECT $dataFromColumn FROM $table WHERE $whereClause";

    $result = prepareDbRequestAndReturnData($conn, $query, $username, $inputs);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row[$dataFromColumn] ?? "";
    }

    return null;
}

/**
 * @param mysqli $conn
 * @param string $query
 * @param string $username
 * @param array $inputs
 * @return false|mysqli_result
 * @throws DatabaseException
 */
function prepareDbRequestAndReturnData(mysqli $conn, string $query, string $username, array $inputs): mysqli_result|false {

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        Logger::log(PREPARE_FAILED_PREFIX . $conn->error, $username);
        throw new DatabaseException(PREPARE_FAILED_PREFIX . $conn->error);
    }

    $types = str_repeat('s', count($inputs));
    $stmt->bind_param($types, ...$inputs);

    $stmt->execute();
    return $stmt->get_result();
}


/**
 * Aktualisiert Einträge in der Datenbank basierend auf den angegebenen Bedingungen
 *
 * @param mysqli $conn
 * @param string $table
 * @param array $columnsToUpdate
 * @param array $whereClauses
 * @param array $inputs
 * @param string $username
 * @return bool
 * @throws DatabaseException
 */

function updateDatabase(mysqli $conn, string $table, array $columnsToUpdate, array $whereClauses, array $inputs, string $username): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $columnsToUpdate = implode(' = ?, ', $columnsToUpdate) . ' = ?';
    $whereClauses = implode(' AND ', $whereClauses);
    $query = "UPDATE $table SET $columnsToUpdate WHERE $whereClauses";

    return prepareAndExecuteDbRequest($conn, $query, $inputs, $username);
}

/**
 * Löscht Einträge aus der Datenbank basierend auf den angegebenen Bedingungen
 *
 * @param mysqli $conn
 * @param string $table
 * @param array $whereClauses
 * @param array $inputs
 * @param string $username
 * @return bool
 * @throws DatabaseException
 */
function deleteFromDatabase(mysqli $conn, string $table, array $whereClauses, array $inputs, string $username): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $whereClauses = implode(' AND ', $whereClauses);
    $query = "DELETE FROM $table WHERE $whereClauses";

    return prepareAndExecuteDbRequest($conn, $query, $inputs, $username);
}


/**
 * Fügt Einträge in die Datenbank ein
 *
 * @param mysqli $conn
 * @param string $table
 * @param array $column
 * @param array $inputs
 * @param string $username
 * @return bool
 * @throws DatabaseException
 */
function insertIntoDatabase(mysqli $conn, string $table, array $column, array $inputs, string $username): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $questionmarks = str_repeat('?, ', count($inputs) - 1) . '?';
    $column = implode(', ', $column);
    $query = "INSERT INTO $table ($column) VALUES ($questionmarks)";

    return prepareAndExecuteDbRequest($conn, $query, $inputs, $username);
}


/**
 * Bereitet eine Datenbankanfrage vor und führt sie aus
 *
 * @param mysqli $conn
 * @param string $query
 * @param array $inputs
 * @param string $username
 * @return bool
 * @throws DatabaseException
 */
function prepareAndExecuteDbRequest(mysqli $conn, string $query, array $inputs, string $username): bool {
    $types = str_repeat('s', count($inputs));
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        Logger::log(PREPARE_FAILED_PREFIX . $conn->error, $username);
        throw new DatabaseException(PREPARE_FAILED_PREFIX . $conn->error);
    }

    $stmt->bind_param($types, ...$inputs);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        Logger::log("Query execution failed: $stmt->error, $username");
        $stmt->close();
        throw new DatabaseException(PREPARE_FAILED_PREFIX . $conn->error);
    }
}


function encryptString($str): string {
    global $config;
    $passwordEncryptionKey = $config['passwordEncryptionKey'];

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($str, "AES-256-CBC", $passwordEncryptionKey, 0, $iv);

    return base64_encode($iv . $encrypted);
}

function decryptCipher($cipher): string {
    global $config;
    $passwordEncryptionKey = $config['passwordEncryptionKey'];

    $cipher = base64_decode($cipher);

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($cipher, 0, $iv_length);
    $encrypted = substr($cipher, $iv_length);

    return openssl_decrypt($encrypted, "AES-256-CBC", $passwordEncryptionKey, 0, $iv);
}


function encryptAndHashPassword($password): array {
    $encryptedPassword = encryptString($password);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    return [$encryptedPassword, $passwordHash];
}

/**
 * @param mysqli $conn
 * @param string $username
 * @param string $inputPassword
 * @return string
 */
function authenticateEncryptedPassword(mysqli $conn, string $username, string $inputPassword): string {
    try {
    $encryptedPassword = getValueFromDatabase($conn, "users", "password_cipher", ["username" => $username], $username);
    $passwordHash = getValueFromDatabase($conn, "users", "password_hash", ["username" => $username], $username);
    } catch (DatabaseException) {
        return "";
    }

    if (!$encryptedPassword || !$passwordHash) {
        return "";
    }

    // Passwort-Hash überprüfen
    if (!password_verify($inputPassword, $passwordHash)) {
        Logger::log("Authentication failed: Password hash verification failed", $username);
    }

    // Passwort entschlüsseln
    $decryptedPassword = decryptCipher($encryptedPassword);

    // Passwort vergleichen
    $isAuthenticated = $decryptedPassword === $inputPassword;
    if (!$isAuthenticated) {
        Logger::log("Authentication failed: Decrypted password does not match", $username);
        return "";
    }
    return $decryptedPassword;
}


function checkIfURLExists($url): bool {
    $file_headers = @get_headers($url);
    if (!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
        $exists = false;
    } else {
        $exists = true;
    }
    return $exists;
}


function redirectToSettingsPage($urlParameterMessage): void {
    header("Location: settings?messageToUser=$urlParameterMessage");
}
