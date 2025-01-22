<?php


function initiateCheck($conn, $username, $password) {
    $schoolUrl = getValueFromDatabase($conn, "users", "school_url", ["username" => $username]);

    if (!$username){
        header("Location: index.php");
        exit();
    }

    $login = loginToWebUntis($username, $password, $schoolUrl);
    if (!$login) {
        return;
    }

    $students = getStudents($login);
    
    $userId = getStudentIdByName($students, $username);
    $notificationForDaysInAdvance = getValueFromDatabase($conn, "users", "notification_for_days_in_advance", ["username" => $username]);

    $currentDate = date("Ymd");
    $maxDateToCheck = date("Ymd", strtotime("+$notificationForDaysInAdvance days"));
    deleteFromDatabase($conn, "timetables", ["for_date < ?"], [$currentDate]);    // Delete old timetables
    deleteFromDatabase($conn, "timetables", ["for_Date >= ?", "user = ?"], [$maxDateToCheck, $username]); // Delete timetables that are outside the notification range


    for ($i = 0; $i < $notificationForDaysInAdvance; $i++) {
        $date = date("Ymd", strtotime("+$i days"));

        $timetable = getTimetable($login, $userId, $date);
        $replacements = getValueFromDatabase($conn, "users", "dictionary", ["username" => $username]);
        $formatedTimetable = getFormatedTimetable($timetable, $replacements);


        $lastRetrieval = getValueFromDatabase($conn, "timetables", "timetable_data", ["for_Date" => $date, "user" => $username]);
        if ($lastRetrieval){
            $lastRetrieval = json_decode($lastRetrieval, true);
        }


        if (!$lastRetrieval && $formatedTimetable != null) {
            insertIntoDatabase($conn, "timetables", ["timetable_data", "for_date", "user"], [$formatedTimetable, $date, $username]);
            continue;
        } elseif (!$lastRetrieval && $formatedTimetable == null) {
            continue;
        }
        
        $compResult = compareArrays($lastRetrieval, $formatedTimetable, $date, $username);

        
        // Update the database with the new timetables
        if ($compResult) {
            updateDatabase($conn, "timetables", ["timetable_data"], ["for_Date = ?", "user = ?"], [$formatedTimetable, $date, $username]);
        }
    }
}






/**
 * Sends a message to a Slack channel using the slack Web API
 * @param string $channel The channel to send the message to (e.g. "#general")
 * @param string $title The title of the message
 * @param string $message The message to send
 * @param string $date The date to which the message refers
 * @return bool Whether the message was sent successfully
 */
function sendslackMessage($username, $channel, $title, $message, $date): bool {
    global $conn;
    $url = 'https://slack.com/api/chat.postMessage';


    $botToken = getValueFromDatabase($conn, "users", "slack_bot_token", ["username" => $username]);

    
    switch (date('w', strtotime($date))) {
        case 0:
            $weekday = "So";
            break;
        case 1:
            $weekday = "Mo";
            break;
        case 2:
            $weekday = "Di";
            break;
        case 3:
            $weekday = "Mi";
            break;
        case 4:
            $weekday = "Do";
            break;
        case 5:
            $weekday = "Fr";
            break;
        case 6:
            $weekday = "Sa";
            break;
        default:
            $weekday = " ";
            break;
    }


    $forDate = match ($date) {
        date("Ymd") => "Heute: ",
        date("Ymd", strtotime("+1 days")) => "Morgen: ",
        date("Ymd", strtotime("+2 days")) => "Übermorgen: ",
        default => $date ? $weekday . ", " . date("d.m", strtotime($date)) . ": " : " ",
    };


    // Prepare the payload
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

    if ($error) {
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $response_data = json_decode($response, true);

    $date = date("d.m.Y", strtotime($date));
    if ($http_code !== 200 || !$response_data['ok']) {
        logNotificationToFile(date('d-m-Y H:i:s'), $date, $username, $channel, $title, $message, $response_data);
        return false;
    }


    logNotificationToFile(date('d-m-Y H:i:s'), $date, $username, $channel, $title, $message, $response_data);
    return true;
}




function logNotificationToFile($dateSent, $forDate, $username, $channel, $title, $message, $error) {
    if (is_array($error)){
        $error = json_encode($error);
    }
    $logFile = 'notifications.log';
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
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['result']['sessionId'])) {
        return $result['result']['sessionId'];
    }
    return "";

}

/**
 * @param $sessionId
 * @param $payload
 * @return mixed
 */
function sendApiRequest($sessionId, $payload): mixed {
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
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['result'] ?? "Error: " . $result['error'];
}



/**
 * @param $sessionId
 * @param $userId
 * @param $date
 * @return mixed
 */
function getTimetable($sessionId, $userId, $date) {
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
    return sendApiRequest($sessionId, $payload);
}


/**
 * @param $sessionId
 * @return mixed
 */
function getStudents($sessionId) {
    $payload = [
        "id" => "getStudents",
        "method" => "getStudents",
        "params" => [],
        "jsonrpc" => "2.0"
    ];
    return sendApiRequest($sessionId, $payload);
}


/**
 * @param $studentArray
 * @param $name
 * @return mixed|null
 */
function getStudentIdByName($studentArray, $name) {
    foreach ($studentArray as $student) {
        if ($student['name'] === $name) {
            return $student['id'];
        }
    }
    return null;
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








function generateReplacementArray($replacements) {
    if (!$replacements) {
        return;
    }

    if (substr($replacements, -1) == ";") {
        $replacements = substr($replacements, 0, -1);
    }
    $replacements = explode(";", $replacements);

    foreach ($replacements as $replacement) {
        $replacement = explode("=", $replacement);
        $replacements[$replacement[0]] = $replacement[1];
    }
    array_splice($replacements, 0, count($replacements) / 2);


    $replacements = array_map(function($value, $key) {
        return [trim($key) => trim($value)];
    }, $replacements, array_keys($replacements));

    return array_merge(...$replacements);

}


/**
 * Ersetzt vordefinierte Wörter im subject durch andere vordefinierte Wörter
 *
 * @param string $subject Das zu überprüfende Fach
 * @param array $replacements Das Array mit den Ersetzungen
 * @return string Das ersetzte Fach
 */
function replaceSubjectWords($subject, $replacements) {
    $replacements = generateReplacementArray($replacements);
    if (!$replacements) {
        return $subject;
    }
    return str_replace(array_keys($replacements), array_values($replacements), $subject);
}










/**
 * @param $timetable
 * @return array
 */
function getFormatedTimetable($timetable, $replacements) {
    global $startTimes;
    $numOfLessons = count($timetable);
    $formatedTimetable = [];
    $seenLessons = [];

    for ($i = 0; $i < $numOfLessons; $i++){
        $lessonNum = $startTimes[$timetable[$i]["startTime"]] ?? "notSet";

        // Hiermit werden "doppelte" Stunden herausgefiltert, da dies sonst zu Problemen bei der compArray-Funktion führen würde.
        // Mir ist bewusst, dass dies nicht die beste Lösung ist
        
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

    // Nach lessonNum sortieren
    usort($formatedTimetable, "cmp");

    return $formatedTimetable;
}










/**
 * @param $array1 (= lastRetrieval)
 * @param $array2 (= formatedTimetable)
 * @return array|string
 */
function compareArrays($array1, $array2, $date, $username) {
    $differences = findDifferences($array1, $array2);
    $differences =  combineNotifications($differences);
    sendSlackMessages($differences, $date, $username);
    return !empty($differences);
}

function findDifferences($array1, $array2) {
    $differences = [];

    foreach ($array1 as $key => $item) {
        if (!isset($array2[$key])) {
            $differences[] = createDifference("sonstiges", "{$item['lessonNum']}. Stunde {$item['subject']} fehlt jetzt komplett", " ");
            continue;
        }

        $canceled = false;
        foreach ($item as $subKey => $value) {
            // Wenn die Stunde ausfällt, sollen keine weiteren Benachrichtigungen gesendet werden
            if ($subKey == "canceled" && $array2[$key][$subKey] == 1 && $value != 1) {
                $differences[] = createDifference("ausfall", "{$item['lessonNum']}. Stunde {$item['subject']}", " ");
                $canceled = true;
                break;
            }
        }

        if ($canceled) {
            continue;
        }

        foreach ($item as $subKey => $value) {
            if ($value != "notSet" && !isset($value)) {
                $differences[] = createDifference("sonstiges", "Eigenschaft \"$subKey\" fehlt in der {$item['lessonNum']}. Stunde", " ");
            } elseif ($array2[$key][$subKey] !== $value) {
                $differences[] = handleDifference($subKey, $value, $array2[$key][$subKey], $item);
            }
        }
    }

    foreach ($array2 as $key => $item) {
        if (!isset($array1[$key])) {
            $differences[] = createDifference("sonstiges", "{$item['lessonNum']}. Stunde {$item['subject']}", "Neues Fach bei {$item['teacher']} in Raum {$item['room']} ist mit dazugekommen");
        }
    }

    return $differences;
}

function createDifference($channel, $title, $message) {
    return ['channel' => $channel, 'title' => $title, 'message' => $message];
}

function handleDifference($subKey, $value, $newValue, $item) {
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

function combineNotifications($differences) {
    if (empty($differences)){
        return;
    }

    foreach ($differences as $difference) {
        $differencesOnlyLessonNum[] = $difference["title"][0];
        $difference["title"] = substr($difference["title"], 1);
        $differencesWithoutLessonNum[] = $difference;
    }

    for ($i = 0; $i < count($differencesWithoutLessonNum)-1; $i++){
        if ($differencesWithoutLessonNum[$i]['title'] == $differencesWithoutLessonNum[$i+1]['title'] && $differencesWithoutLessonNum[$i]['channel'] == $differencesWithoutLessonNum[$i+1]['channel'] && $differencesWithoutLessonNum[$i]['message'] == $differencesWithoutLessonNum[$i+1]['message']){
            $differences[$i]['title'] = $differencesOnlyLessonNum[$i] . ". & " . $differencesOnlyLessonNum[$i+1] . $differencesWithoutLessonNum[$i]['title'];
            unset($differences[$i+1]);
        }
    }
    return $differences;
}


function sendSlackMessages($differences, $date, $username) {
    if (empty($differences)){
        return;
    }
    foreach ($differences as $difference) {
        sendslackMessage($username, $difference['channel'], $difference['title'], $difference['message'], $date);
        //echo $difference['channel'] . ", " . $difference['title'] . ", " . $difference['message'] . ", " . $date . "<br>";
    }
}














/**
 * @param $case
 * @return string
 */
function getMessageText($case) {
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
 * @return mysqli
 */
function connectToDatabase(): mysqli {
    $config = include 'config.php';

    $servername = $config['servername'];
    $username = $config['username'];
    $password = $config['password'];
    $database = $config['database'];

    // Create connection
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);

    }

    return $conn;
}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $query
 * @return array
 */

function getRowsFromDatabase(mysqli $conn, string $table, array $inputsAndConditions): array {
    $conditions = [];
    $inputs = [];

    foreach ($inputsAndConditions as $key => $value) {
        $conditions[] = "$key = ?";
        $inputs[] = $value;
    }

    $whereClause = implode(' AND ', $conditions);
    $query = "SELECT * FROM $table WHERE $whereClause";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $types = str_repeat('s', count($inputs));
    $stmt->bind_param($types, ...$inputs);

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);

}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $dataFromColumn
 * @param string $query
 * @return string|null
 */

function getValueFromDatabase(mysqli $conn, string $table, string $dataFromColumn, array $inputsAndConditions): ?string {
    $conditions = [];
    $inputs = [];

    foreach ($inputsAndConditions as $key => $value) {
        $conditions[] = "$key = ?";
        $inputs[] = $value;
    }

    $whereClause = implode(' AND ', $conditions);
    $query = "SELECT $dataFromColumn FROM $table WHERE $whereClause";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $types = str_repeat('s', count($inputs));
    $stmt->bind_param($types, ...$inputs);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row[$dataFromColumn] ?? null;
    }
    return null;
}


/**
 * @param mysqli $conn
 * @param array $inputs
 * @param string $query
 * @return bool
 */

function updateDatabase(mysqli $conn, string $table, array $columnsToUpdate, array $whereClauses, array $inputs): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $columnsToUpdate = implode(' = ?, ', $columnsToUpdate) . ' = ?';
    $whereClauses = implode(' AND ', $whereClauses);
    $query = "UPDATE $table SET $columnsToUpdate WHERE $whereClauses";

    return prepareAndExecuteDbRequest($conn, $query, $inputs);
}

function deleteFromDatabase(mysqli $conn, string $table, array $whereClauses, array $inputs): bool {
    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $whereClauses = implode(' AND ', $whereClauses);
    $query = "DELETE FROM $table WHERE $whereClauses";

    return prepareAndExecuteDbRequest($conn, $query, $inputs);
}


function insertIntoDatabase(mysqli $conn, string $table, array $column, array $inputs): bool {

    foreach ($inputs as &$input) {
        if (is_array($input)) {
            $input = json_encode($input);
        }
    }

    $questionmarks = str_repeat('?, ', count($inputs)-1);
    $questionmarks .= '?';

    $column = implode(', ', $column);

    $query = "INSERT INTO $table ($column) VALUES ($questionmarks)";


    return prepareAndExecuteDbRequest($conn, $query, $inputs);
}



function prepareAndExecuteDbRequest($conn, $query, $inputs){
    $types = str_repeat('s', count($inputs));
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$inputs);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}












function encryptString($str) {
    $config = require 'config.php';
    $passwordEncryptionKey = $config['passwordEncryptionKey'];

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($str, "AES-256-CBC", $passwordEncryptionKey, 0, $iv);

    return base64_encode($iv . $encrypted);
}

function decryptCipher($cipher) {
    $config = require 'config.php';
    $passwordEncryptionKey = $config['passwordEncryptionKey'];

    $cipher = base64_decode($cipher);

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($cipher, 0, $iv_length);
    $encrypted = substr($cipher, $iv_length);

    return openssl_decrypt($encrypted, "AES-256-CBC", $passwordEncryptionKey, 0, $iv);
}





function encryptAndHashPassword($password) {
    $encryptedPassword = encryptString($password);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    return [$encryptedPassword, $passwordHash];
}

function authenticateEncryptedPassword($conn, $username, $inputPassword) {

    $encryptedPassword = getValueFromDatabase($conn, "users", "password_cipher", ["username" => $username]);
    $passwordHash = getValueFromDatabase($conn, "users", "password_hash", ["username" => $username]);

    if (!$encryptedPassword || !$passwordHash) {
        return false; // Benutzer nicht gefunden oder Hash nicht vorhanden
    }

    // Passwort-Hash überprüfen
    if (!password_verify($inputPassword, $passwordHash)) {
        return false; // Authentifizierung fehlgeschlagen
    }

    // Passwort entschlüsseln
    $decryptedPassword = decryptCipher($encryptedPassword);

    // Passwort vergleichen
        if ($decryptedPassword === $inputPassword) {
            return $decryptedPassword;
        } else {
            return false;
        }
}

