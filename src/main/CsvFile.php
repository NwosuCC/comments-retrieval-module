<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 17, 2018
 */

class CsvFile {
    protected static $file, $csvDir = 'csv';
    protected static $directories = [
        'amazon' => 'aB6v5CD7u', 'apple' => 'vAX8ycNoI',
        'google' => 'bNg8Ak0q4', 'youtube' => 'VktiOm42z'
    ];
    private $fileHandle;

    public function __construct($params, $stage = 0){
        if(!empty($params['file'])){
            $category = $params['category'];   $filename = $params['file'];
        }else if($stage == 1){
            list($category, $filename) = $this->generateFilename($params);
        }

        if(!empty($category) and !empty($filename)){
            $this->setFileComponents($category, $filename);
        }
    }

    public function getFiles($params = []){
        if($params and is_array($params)){
            list($category, $filename) = $params;
            $this->setFileComponents($category, $filename);
        }
        return static::$file;
    }

    private function setFileComponents($category, $filename){
        $fileParts = explode('.', $filename);
        $validFile = strtolower(end($fileParts)) === 'csv';

        empty(static::$directories[$category])
            ? $this->throwError('x31', [$category])
            : !$validFile ? $this->throwError('x32', [$filename]) : null;

        $directory = static::$directories[$category];

        $tempFile = join('/', [static::$csvDir, $filename]);
        $permFile = join('/', [static::$csvDir, $directory, $filename]);

        static::$file = [
            'name' => $filename, 'temp' => $tempFile, 'final' => $permFile
        ];
    }

    private function throwError($error_code = '', $argv = []) {
        CommError::setError('file', $error_code, $argv);
    }

    private function generateFilename($params){
        if(!array_key_exists('category', $params)){ return null; };
        $category = $params['category'];
        $product_name = $params['product_name'];
        $file_id  = strtolower($product_name .'-'.time());
        $filename = $file_id . '.csv';
        return [$category, $filename];
    }

    public function openCsvFile($isNewFile){
        if(!static::$file){ return; }

        $mode = ($isNewFile) ? "w" : "a";
        $tempFile = static::$file['temp'];

        $this->fileHandle = fopen($tempFile, $mode);
        chgrp($tempFile, 'www-data');
        chmod($tempFile, 0777);
    }

    public function storeCsvLine($oneLine) {
        $delimiter = ',';     $enclosed_by = '"';
        fputcsv($this->fileHandle, $oneLine, $delimiter, $enclosed_by);
    }

    public function closeCsvFile() {
        fclose($this->fileHandle);
    }

    public function createFinalCsvFile() {
        $file = static::$file;
        $tempFile = $file['temp'];   $finalFile = $file['final'];
        return copy($tempFile, $finalFile);
    }

    public function removeTempFiles(){
        if(static::$file){
            $tempFile = static::$file['temp'];
            if(file_exists($tempFile)){ unlink($tempFile); }
        }
    }

}
