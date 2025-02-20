<?php

class ErrorLogger {
    public static function log($message, $username = null, $password = null): void {

        $logDir = __DIR__ . '/Logs';
        $logFile = $logDir . '/' . date('Y-m-d') . '-log.log';
        $logMessage = '[' . date('d.m.Y H:i:s') . '] ';
        if ($username && $password) {
            $logMessage .= 'Username: (' . $username . '); Password: (' . $password . '); ';
        }
        if($username && !$password) {
            $logMessage .= 'Username: (' . $username . '); ';
        }
        $logMessage .= $message . PHP_EOL;

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
