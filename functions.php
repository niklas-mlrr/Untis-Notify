<?php

require_once "Logger.php";
require_once __DIR__ . "/Exceptions/AuthenticationException.php";
require_once __DIR__ . "/Exceptions/DatabaseException.php";
require_once __DIR__ . "/Exceptions/UserException.php";

use Exceptions\AuthenticationException;
use Exceptions\DatabaseException;
use Exceptions\UserException;


const DATABASE_EXCEPTION_PREFIX = 'DatabaseException: ';
const CURL_ERROR_PREFIX = 'Curl error: ';
const PREPARE_FAILED_PREFIX = 'Prepare failed: ';
const SLACK_API_ERROR = 'Slack API error: ';


/**
 * @param mysqli $conn
 * @param string $username
 * @param string $password
 * @return void
 */
function initiateCheck(mysqli $conn, string $username, string $password): void {
    $differences = [];
    $date = "";
    try {
        $schoolUrl = getValueFromDatabase($conn, "users", "school_url", ["username" => $username], $username);
        $login = loginToWebUntis($username, $password, $schoolUrl);

        $students = getStudents($login, $username);
        $userId = getStudentIdByName($students, $username);
        $notificationForDaysInAdvance = getValueFromDatabase($conn, "users", "notification_for_days_in_advance", ["username" => $username], $username);
    } catch (AuthenticationException|DatabaseException|UserException) {
        return;
    }

    $currentDate = date("Ymd");
    $maxDateToCheck = date("Ymd", strtotime("+$notificationForDaysInAdvance days"));

    try {
        deleteFromDatabase($conn, "timetables", ["for_date < ?"], [$currentDate], $username);
        deleteFromDatabase($conn, "timetables", ["for_Date >= ?", "user = ?"], [$maxDateToCheck, $username], $username);
    } catch (DatabaseException $e) {
        Logger::log(DATABASE_EXCEPTION_PREFIX . "; Alte Stundenplandaten nicht erfolgreich gelöscht; " . $e->getMessage(), $username);
    }

    for ($i = 0; $i < $notificationForDaysInAdvance; $i++) {
        $date = date("Ymd", strtotime("+$i days"));
        $differences = array_merge($differences, checkCompareAndUpdateTimetable($date, $conn, $login, $userId, $username));
    }


    sendSlackMessages($differences, $username, $conn);
}

/**
 * @param mysqli $conn
 * @param string $date
 * @param string $login
 * @param int $userId
 * @param string $username
 * @return array
 */
function checkCompareAndUpdateTimetable(string $date, mysqli $conn, string $login, int $userId, string $username): array {
    try {
        $timetable = getTimetable($login, $userId, $date, $username);
        $replacements = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username], $username);
        $formatedTimetable = getFormatedTimetable($timetable, $replacements);
        $lastRetrieval = getValueFromDatabase($conn, "timetables", "timetable_data", ["for_Date" => $date, "user" => $username], $username);

        $lastRetrieval = $lastRetrieval ? json_decode($lastRetrieval, true) : null;

        if (!$lastRetrieval) {
            if ($formatedTimetable != null) {
                insertIntoDatabase($conn, "timetables", ["timetable_data", "for_date", "user"], [$formatedTimetable, $date, $username], $username);
            }
            return [];
        }

    } catch (DatabaseException) {
        return [];
    }


    $compResult = compareArrays($lastRetrieval, $formatedTimetable, $date);

    if ($compResult != null) {
        try {
            updateDatabase($conn, "timetables", ["timetable_data"], ["for_Date = ?", "user = ?"], [$formatedTimetable, $date, $username], $username);
        } catch (DatabaseException) {
            return [];
        }
    }
    return $compResult;
}


/**
 * @param string $username
 * @param string $channel
 * @param string $title
 * @param string $message
 * @param $date
 * @param mysqli $conn
 * @return bool
 * @throws DatabaseException
 * @throws Exception
 */
function sendSlackMessage(string $username, string $channel, string $title, string $message, $date, mysqli $conn): bool {

    $url = 'https://slack.com/api/chat.postMessage';

    try {
        $botToken = getValueFromDatabase($conn, "users", "slack_bot_token", ["username" => $username], $username);
    } catch (DatabaseException $e) {
        throw new DatabaseException(DATABASE_EXCEPTION_PREFIX . $e->getMessage());
    }
    if (!$botToken) {
        Logger::log("No Slack Bot Token found", $username);
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

    $payload = array(
        'channel' => $channel,
        "blocks" => [
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "$forDate"
                ]
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "$title"
                ]
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "$message"
                ]
            ],
            [
                "type" => "divider"
            ]
        ]
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $botToken,
            'Content-Type: application/json; charset=utf-8'
        ),
        CURLOPT_POSTFIELDS => json_encode($payload)
    ));

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $date = date("d.m.Y", strtotime($date));
    $exactDate = date("d.m.Y H:i:s");

    if ($error) {
        logNotificationToFile($exactDate, $date, $username, $channel, $title, $message, $error);
        Logger::log(CURL_ERROR_PREFIX . $error, $username);
        throw new Exception(CURL_ERROR_PREFIX . $error);
    }

    $response_data = json_decode($response, true);

    if ($http_code !== 200 || !$response_data['ok']) {
        logNotificationToFile($exactDate, $date, $username, $channel, $title, $message, $response_data);
        Logger::log(SLACK_API_ERROR . json_encode($response_data), $username);
        throw new Exception(SLACK_API_ERROR . json_encode($response_data));
    }

    logNotificationToFile($exactDate, $date, $username, $channel, $title, $message, $response_data);
    return true;
}


/**
 * @param $dateSent
 * @param $forDate
 * @param string $username
 * @param string $channel
 * @param string $title
 * @param string $message
 * @param $error
 * @return void
 */
function logNotificationToFile($dateSent, $forDate, string $username, string $channel, string $title, string $message, $error): void {
    if (is_array($error)) {
        $error = json_encode($error);
    }
    $logFile = 'Logs/' . date('Y-m-d') . '-notifications.log';
    $logEntry = sprintf(
        "[%s] ForDate: %s, Username: %s, Channel: %s, Title: %s, Message: %s, Slack API Response Data: %s\n",
        $dateSent,
        $forDate,
        $username,
        $channel,
        $title,
        $message,
        $error
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}


/**
 * Logs into the WebUntis API and returns the session ID
 *
 * @param string $username The username to log in with
 * @param string $password The password to log in with
 * @param string $schoolUrl The URL of the school
 * @return string The session ID
 * @throws AuthenticationException
 */
function loginToWebUntis(string $username, string $password, string $schoolUrl): string {
    $loginPayload = [
        "id" => "login",
        "method" => "authenticate",
        "params" => [
            "user" => $username,
            "password" => $password,
        ],
        "jsonrpc" => "2.0"
    ];

    $ch = curl_init($schoolUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        curl_close($ch);
        Logger::log(CURL_ERROR_PREFIX . $error, $username);
        throw new AuthenticationException("Curl error: " . $error);
    }

    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result']['sessionId'])) {
        return $result['result']['sessionId'];
    }

    Logger::log("AuthenticationException: Untis Login failed. Response: " . json_encode($result), $username);
    throw new AuthenticationException("Untis Login failed. Response: " . json_encode($result));
}

/**
 * @param string $sessionId
 * @param array $payload
 * @param string $username
 * @return array
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
    }

    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result'])) {
        return $result['result'];
    }

    Logger::log("QueryException: API request failed. Response: " . json_encode($result), $username);
    return [];
}


/**
 * @param string $sessionId
 * @param int $userId
 * @param string $date
 * @param string $username
 * @return array
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

    return sendApiRequest($sessionId, $payload, $username);
}

/**
 * @param string $sessionId
 * @param string $username
 * @return array
 */
function getStudents(string $sessionId, string $username): array {
    $payload = [
        "id" => "getStudents",
        "method" => "getStudents",
        "params" => [],
        "jsonrpc" => "2.0"
    ];

    return sendApiRequest($sessionId, $payload, $username);
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
    Logger::log("Student not found: " . $name);
    throw new UserException("Student not found: " . $name);
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


function cmp($a, $b) {
    return $a['lessonNum'] - $b['lessonNum'];
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
    $numOfLessons = count($timetable);
    $formatedTimetable = [];
    $seenLessons = [];

    for ($i = 0; $i < $numOfLessons; $i++) {
        $lessonNum = $startTimes[$timetable[$i]["startTime"]] ?? "notSet";

        if (in_array($lessonNum, $seenLessons)) {
            foreach ($formatedTimetable as $key => $lesson) {
                if ($lesson['lessonNum'] == $lessonNum) {
                    unset($formatedTimetable[$key]);
                    break;
                }
            }
        }

        $canceled = isset($timetable[$i]["code"]) ? 1 : 0;

        $lesson = [
            "canceled" => $canceled,
            "lessonNum" => $lessonNum,
            "subject" => $timetable[$i]["su"][0]["longname"] ?? "notSet",
            "teacher" => $timetable[$i]["te"][0]["name"] ?? "notSet",
            "room" => $timetable[$i]["ro"][0]["name"] ?? "notSet",
        ];

        $lesson["subject"] = replaceSubjectWords($lesson["subject"], $replacements);

        $formatedTimetable[] = $lesson;
        $seenLessons[] = $lessonNum;
    }

    usort($formatedTimetable, "cmp");

    return $formatedTimetable;
}


/**
 * @param $array1 (= lastRetrieval)
 * @param $array2 (= formatedTimetable)
 * @param $date
 * @return array
 */
function compareArrays($array1, $array2, $date): array {
    $differences = [];
    list($canceledDifferences, $canceledLessons) = findCanceledItems($array1, $array2);
    $differences = array_merge($differences, $canceledDifferences);
    $differences = array_merge($differences, findMissingItems($array1, $array2));
    $differences = array_merge($differences, findChangedItems($array1, $array2, $canceledLessons));
    $differences = array_merge($differences, findNewItems($array1, $array2));
    $differences = combineNotifications($differences);

    // Add the for_date to each entry
    foreach ($differences as $key => $difference) {
        $differences[$key]['date'] = $date;
    }

    return $differences;
}


function findMissingItems($array1, $array2): array {
    $differences = [];
    foreach ($array1 as $key => $item) {
        if (!isset($array2[$key])) {
            $differences[] = createDifference("sonstiges", "{$item['lessonNum']}. Stunde {$item['subject']} fehlt jetzt komplett", " ");
        }
    }
    return $differences;
}

function findCanceledItems($array1, $array2): array {
    $differences = [];
    $canceledLessons = [];
    foreach ($array1 as $key => $item) {
        if (isset($array2[$key])) {
            foreach ($item as $subKey => $value) {
                if ($subKey == "canceled" && $array2[$key][$subKey] == 1 && $value != 1) {
                    $differences[] = createDifference("ausfall", "{$item['lessonNum']}. Stunde {$item['subject']}", " ");
                    $canceledLessons[] = $item['lessonNum'];
                    break;
                }
            }
        }
    }
    return [$differences, $canceledLessons];
}

function findChangedItems($array1, $array2, $canceledLessons): array {
    $differences = [];
    foreach ($array1 as $key => $item) {
        if (isset($array2[$key]) && !in_array($item['lessonNum'], $canceledLessons)) {
            foreach ($item as $subKey => $value) {
                if ($value != "notSet" && !isset($value)) {
                    $differences[] = createDifference("sonstiges", "Eigenschaft \"$subKey\" fehlt in der {$item['lessonNum']}. Stunde", " ");
                } elseif ($array2[$key][$subKey] !== $value) {
                    $differences[] = handleDifference($subKey, $value, $array2[$key][$subKey], $item);
                }
            }
        }
    }
    return $differences;
}

function findNewItems($array1, $array2): array {
    $differences = [];
    foreach ($array2 as $key => $item) {
        if (!isset($array1[$key])) {
            $differences[] = createDifference("sonstiges", "{$item['lessonNum']}. Stunde {$item['subject']}", "Neues Fach bei {$item['teacher']} in Raum {$item['room']} ist mit dazugekommen");
        }
    }
    return $differences;
}

function createDifference($channel, $title, $message): array {
    return ['channel' => $channel, 'title' => $title, 'message' => $message];
}

function handleDifference($subKey, $value, $newValue, $item): ?array {
    $lessonNum = $item['lessonNum'];
    $subject = $item['subject'];

    return match ($subKey) {
        "canceled" => $value == 1 ? createDifference("sonstiges", "$lessonNum. Stunde $subject", "Jetzt kein Ausfall mehr") : createDifference("ausfall", "$lessonNum. Stunde $subject", " "),
        "teacher" => $newValue == "---" ? createDifference("sonstiges", "$lessonNum. Stunde $subject", "Lehrer ausgetragen (Vorher: $value)") : createDifference("vertretung", "$lessonNum. Stunde $subject", "Vorher: $value; Jetzt: $newValue"),
        "room" => $newValue == "---" ? createDifference("sonstiges", "$lessonNum. Stunde $subject", "Raum ausgetragen (Vorher: $value)") : createDifference("raumänderung", "$lessonNum. Stunde $subject", "Vorher: $value; Jetzt: $newValue"),
        "subject" => $newValue == "---" ? createDifference("sonstiges", "$lessonNum. Stunde $subject", "Fach ausgetragen (Vorher: $value)") : createDifference("sonstiges", "$lessonNum. Stunde $subject", "Fachwechsel; Vorher: $value; Jetzt: $newValue"),
        default => null,
    };
}

/**
 * @param $differences
 * @return array
 */
function combineNotifications($differences): array {
    if (empty($differences)) {
        return [];
    }
    $differencesOnlyLessonNum = [];
    $differencesWithoutLessonNum = [];

    foreach ($differences as $difference) {
        $differencesOnlyLessonNum[] = $difference["title"][0];
        $difference["title"] = substr($difference["title"], 1);
        $differencesWithoutLessonNum[] = $difference;
    }

    for ($i = 0; $i < count($differencesWithoutLessonNum) - 1; $i++) {
        if ($differencesWithoutLessonNum[$i]['title'] == $differencesWithoutLessonNum[$i + 1]['title'] && $differencesWithoutLessonNum[$i]['channel'] == $differencesWithoutLessonNum[$i + 1]['channel'] && $differencesWithoutLessonNum[$i]['message'] == $differencesWithoutLessonNum[$i + 1]['message']) {
            $differences[$i]['title'] = $differencesOnlyLessonNum[$i] . ". & " . $differencesOnlyLessonNum[$i + 1] . $differencesWithoutLessonNum[$i]['title'];
            unset($differences[$i + 1]);
        }
    }
    return $differences;
}


function sendSlackMessages($differences, $username, $conn): void {
    if (empty($differences)) {
        return;
    }
    $differencesCount = count($differences);

    if ($differencesCount >= 20) {
        $differences = [];
        $differences[] = createDifference("sonstiges", "Zu viele Benachrichtigungen", "Das System wollte gerade " . $differencesCount . " Benachrichtigungen zu dir senden. Durch einen Sicherheitsmechanismus wurden diese abgefangen. Bitte wende dich an den Admin um zu erfahren, warum dir so viele Benachrichtigungen gesendet werden sollten");
    }

    foreach ($differences as $difference) {
        try {
            sendSlackMessage($username, $difference['channel'], $difference['title'], $difference['message'], $difference['date'], $conn);
        } catch (DatabaseException|Exception) {
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
        "settingsSavedSuccessfully" => '<p class="successful">Einstellungen erfolgreich gespeichert</p>',
        "settingsNotSaved" => '<p class="failed">Fehler beim Speichern der Einstellungen</p>',
        "accountDeletedSuccessfully" => '<p class="successful">Konto erfolgreich gelöscht</p>',
        "accountNotDeleted" => '<p class="failed">Fehler beim Löschen des Kontos</p>',
        "testNotificationAllSent" => '<p class="successful">Alle 4 Testbenachrichtigungen erfolgreich gesendet</p>',
        "testNotificationAllNotSent" => '<p class="failed">Fehler beim Senden aller Testbenachrichtigungen</p>',
        "testNotificationAusfallNotSent" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung für den Channel ausfall</p>',
        "testNotificationRaumänderungNotSent" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung für den Channel raumänderung</p>',
        "testNotificationVertretungNotSent" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung für den Channel vertretung</p>',
        "testNotificationSonstigesNotSent" => '<p class="failed">Fehler beim Senden der Testbenachrichtigung für den Channel sonstiges</p>',
        "dbError" => '<p class="failed">Fehler beim Abrufen der Daten aus der Datenbank</p>',
        "dbConnError" => '<p class="failed">Fehler beim Herstellen der Verbindung zur Datenbank</p>',
        default => "",
    };
}


/**
 * @return void
 */
function logOut(): void {
    setcookie(session_name(), '', 100);
    session_unset();
    session_destroy();
    $_SESSION = array();
    header("Location: index.php");
}


/**
 * Stellt eine Verbindung zur Datenbank her
 * @return mysqli
 * @throws DatabaseException
 */
function connectToDatabase(): mysqli {
    $config = require 'config.php';

    echo $servername = $config['servername'];
    echo $username = $config['username'];
    echo $password = $config['password'];
    echo $database = $config['database'];

    echo "connectToDatabase Mitte";
    // Verbindung erstellen
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        Logger::log("Db Connection failed: " . $conn->connect_error);
        throw new DatabaseException("Db Connection failed: " . $conn->connect_error);
    }
    echo "connectToDatabase Ende";
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
        Logger::log("Query execution failed: " . $stmt->error, $username);
        $stmt->close();
        throw new DatabaseException(PREPARE_FAILED_PREFIX . $conn->error);
    }
}


function encryptString($str): string {
    $config = require 'config.php';
    $passwordEncryptionKey = $config['passwordEncryptionKey'];

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($str, "AES-256-CBC", $passwordEncryptionKey, 0, $iv);

    return base64_encode($iv . $encrypted);
}

function decryptCipher($cipher): string {
    $config = require 'config.php';
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
 * Authentifiziert ein verschlüsseltes Passwort
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $inputPassword
 * @return string
 * @throws DatabaseException
 * @throws Exception
 */
function authenticateEncryptedPassword(mysqli $conn, string $username, string $inputPassword): string {
    $encryptedPassword = getValueFromDatabase($conn, "users", "password_cipher", ["username" => $username], $username);
    $passwordHash = getValueFromDatabase($conn, "users", "password_hash", ["username" => $username], $username);

    if (!$encryptedPassword || !$passwordHash) {
        Logger::log("Authentication failed: Encrypted Password or Password Hash not in Db", $username);
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
