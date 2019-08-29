<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 16, 2018
 */

require_once(__DIR__ . '/../utility/Logger.php');

class CommError {
    private static $error, $errors = [
        'task' => [
            '01' => 'Operation failed. An unexpected error occurred!',
            '02' => '{var1}',
            '03' => 'Data Retrieval could not be completed at this time',
            '04' => "URL is invalid",
            '05' => "URL is currently not supported",
            '06' => '{var1}',
            '07' => "Task id: '{var1}' not found",
            '08' => 'You have not provided enough data to process this request'
        ],
        'request' => [
            '11'  => "{var1} is required",
            '12'  => "URL is invalid",
            '13'  => "Email is invalid",
            '14'  => "Name: Only letters, numbers, white spaces are accepted",
            '15'  => "Please, select a valid delivery method",
            '16'  => "Retrieval request ID is not valid",
            '17'  => "File download link is invalid"
        ],
        'retrieval' => [
            '21'  => "",
        ],
        'file' => [
            '31'  => "Unsupported category '{var1}'",
            '32'  => "Invalid filename '{var1}'",
        ],
    ];

    public static function setError($class, $error_code, $argv = []) {
        if($log = stristr($error_code, 'x')){
            $error_code = str_replace('x', '', $error_code);
        }

        if(empty(static::$errors[$class][$error_code])){
            $class = 'task';   $error_code = '01';
        }
        $error_message = static::$errors[$class][$error_code];

        if(is_array($argv)){
            for($n = 1;  $n <= count($argv);  $n++){
                $error_message = str_replace("{var$n}", $argv[$n-1], $error_message);
            }
        }

        if($log){
            $error_message = ucfirst($class) . '::class ' . $error_message;
        }

        static::$error = [
            'code' => $error_code, 'error' => $error_message
        ];

        $log ? static::logError() : static::alert();
    }

    public static function alert(){
        $error = static::$error;
        static::$error = null;
        die(json_encode($error));
    }

    private static function logError(){
        // log $error
        $code = static::$error['code'];   $error = static::$error['error'];
        Logger::log('error', [$code, $error]);

        // alert Default error
        static::setError('task', '01');
        static::alert();
    }

}