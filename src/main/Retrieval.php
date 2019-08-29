<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 16, 2018
 */

require_once(__DIR__ . '/../main/Task.php');
require_once(__DIR__ . '/../main/CommError.php');
require_once(__DIR__ . '/../utility/Dates.php');
require_once(__DIR__ . '/../utility/Url.php');
require_once(__DIR__ . '/../utility/Logger.php');
require_once(__DIR__ . '/../misc/user_agents.php');

Abstract class Retrieval {
    protected $resource, $iterations = 0, $totalReviews = 0, $endProcess = false;
    protected $retrievedPage = 0, $totalPages = 0;
    private $task, $csv, $file;

    public function __construct($taskInfo){
        $vars = [ 'task_id' => $taskInfo['id'] ];
        $this->task = new Task($vars, 4);

        $this->csv = new CsvFile($taskInfo);
        $this->file = $this->csv->getFiles();
    }

    private function throwError($error_code = '', $argv = []) {
        CommError::setError('retrieval', $error_code, $argv);
    }

    public function stripChars($data){
        return trim(str_replace('\n','. ',$data));
    }

    private function formUrl($baseUrl, $parameters){
        foreach ($parameters as $key => $value){
            $parameters[$key] = "$key=$value";
        }
        $url = $baseUrl;
        if(count($parameters)){ $url .= implode('&', $parameters); }
//        if($this->iterations == 1){ echo $this->iterations.': '.$url.' | params: '.count($parameters).'\n'; exit; }
//        echo $this->iterations.': '.$url.' | params: '.count($parameters).PHP_EOL;
//        $url = "test/sample-apple-{$this->iterations}.php";
        $url = "test/sample-amazon-{$this->iterations}.html";
//        $url = "test/sample-youtube-{$this->iterations}.php";
//        if($this->iterations > 2) die(json_encode($url));
        return $url;
    }

    private function getUserAgent(){
        $user_agents = ua();
        return $user_agents[abs(rand(0, count($user_agents)-1))];
    }

    public function makeRequest($baseUrl, $parameters){
        $url = $this->formUrl($baseUrl, $parameters);
        $user_agent = $this->getUserAgent();
        $options = [
            'http' => [
                'method' => "GET",
                'header' =>  "Accept: application/json\r\n" . "User-Agent: $user_agent"
            ]
        ];
        $context = stream_context_create($options);

        try {
            $this->resource = file_get_contents($url,false, $context);
        } catch (Exception $e){
            $e->getMessage();
        }
        return ($this->resource) ? true : false;
    }

    public function parseHtml($base_path, $column_paths){
        if(!$this->resource){ return false; }

        $dom = new DOMDocument();
        @$dom->loadHTML($this->resource);
        $xpath = new DOMXPath($dom);

        if(!$xpath->evaluate($base_path)->length){
            return false;
        }

        $snippets = [];  $href_index = 2;  $n = 0;

        foreach($column_paths as $columnName => $rel_path){
            $rel_path = str_replace('{columnName}', $columnName, $rel_path);
            $elementDom = $xpath->evaluate($base_path . $rel_path);
            if(empty($cl)){ $cl = $elementDom->length; }
            ++$n;

            for ($c = 0; $c < $cl; $c++){
                $element = $elementDom->item($c);
                $snippets[$c][$columnName] = $this->stripChars($element->nodeValue);

                if($columnName == 'title'){
                    $value = $element->attributes[$href_index]->nodeValue;
                    $snippets[$c]['url'] = $this->stripChars($value);
                }
            }
        }

        return $snippets;
    }

    public function beginStorage(){
        $isNewFile = ($this->totalReviews <= 0);
        $this->csv->openCsvFile($isNewFile);
        if($isNewFile){
            $column_names = ['userName', 'date', 'starRating', 'reviewOrComment', 'link' ];
            $this->storeEntry($column_names);
        }
    }

    public function storeEntry($values){
        $this->csv->storeCsvLine($values);
    }

    public function endStorage(){
        $this->csv->closeCsvFile();

        $figures = [$this->retrievedPage, $this->totalPages, $this->totalReviews];
        $this->task->updateProgress($figures);
    }

}
