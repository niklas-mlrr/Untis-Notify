<?php

class Logger {
    public static function log($message, $username = null): void {
        $logFile = 'Logs/' . date('Y-m-d') . '-log.log';
        $logMessage = '[' . date('d.m.Y H:i:s') . '] ';
        if ($username) {
            $logMessage .= 'Username: ' . $username . '; ';
        }
        $logMessage .= $message . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
