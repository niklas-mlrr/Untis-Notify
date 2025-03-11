<?php

class Logger {
    public static function log($message, $username = null, $password = null): void {

        $currentYear = date("Y");
        $currentMonth = date("m");

        $logDir = __DIR__ . "/Logs/$currentYear/$currentMonth";
        $logFile = $logDir . '/' . date('Y-m-d') . '-log.log';
        $logMessage = '[' . date('d.m.Y H:i:s') . '] ';
        if ($username && $password) {
            $logMessage .= 'Username: (' . $username . '); Password: (' . $password . '); ';
        }
        if($username && !$password) {
            $logMessage .= 'Username: (' . $username . '); ';
        }
        $logMessage .= $message . PHP_EOL;

        if(!file_exists($logDir)) {
            mkdir($logDir, 0700, true);
        }
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
