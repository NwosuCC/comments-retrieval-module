<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 17, 2018
 */

DEFINE('HOME', $_ENV['server_home']);

require_once(__DIR__ . '/../main/Request.php');
require_once(__DIR__ . '/../main/Task.php');
require_once(__DIR__ . '/../main/CommError.php');
require_once(__DIR__ . '/../utility/Dates.php');
require_once(__DIR__ . '/../utility/Logger.php');

class TaskManager {
    private $script, $output, $task;
    private $task_id, $result;

    protected static $filename, $directories = [
        'amazon' => 'aB6v5CD7u', 'apple' => 'vAX8ycNoI',
        'google' => 'bNg8Ak0q4', 'Youtube' => 'VktiOm42z'
    ];

    public function __construct($request){
        Dates::setTimezone($_ENV['timezone']);
        list($stage, $function, $key) = $this->getEntryPoint($request);

        $vars = $stage ? (new Request($request, $stage))->getVars() : null;
        if(!$vars){
            $this->throwError('08');
            $this->abort();
        };

        if(!empty($vars['task_id'])){
            $vars['id'] = $task_id = $vars['task_id'];
            if(!$vars = (new Task($vars, $stage))->getTaskInfo()){
                $this->throwError('x07', [$task_id]);
            }
            $vars['id'] = $task_id;
        }
        $this->task = new Task($vars, $stage);

        $this->task_id = ($key != 'task_id') ? $vars[$key] : $vars['id'];
        $this->result = $this->$function(11);
        return $this;
    }

    public function getEntryPoint($request){
        $indices = [
            'url' => [1, 'startTask'],    'task_id' => [2, 'runRetrieval'],
            'tp' => [3, 'checkProgress'], 'dt' => [4, 'deliverFile'],
        ];
        $stage = 0; $function = 'abort';  $key = '';

        foreach ($indices as $key => $components){
            if(!empty($request[$key])){
                list($stage, $function) = $components; break;
            }
        }
        return [$stage, $function, $key];
    }

    public function getResult(){
        $result = $this->result;   $this->result = null;
        return $result;
    }

    private function throwError($error_code = '', $argv = []){
        CommError::setError('task', $error_code, $argv);
    }

    private function startTask(){
        if(!$task = $this->task->saveTaskInfo()){
            $this->abort();
        }

        list($task_id, $category) = $task;
        $this->runProcess($task_id, $category);
        return [
            'message' => ['id' => $task_id]
        ];
    }

    private function runProcess ($task_id, $category) {
        $this->script = "misc/process.php";
        $this->output = "misc/output";

        $arguments = implode('00100101', [$category, $task_id]);
        $command = "php $this->script $arguments > $this->output";

        (substr(php_uname(), 0, 7) == "Windows")
            ? pclose(popen("start /B ". $command, "r"))
            : exec($command);
    }

    private function runRetrieval(){
        $taskInfo = $this->task->getTaskInfo();
        $category = ucfirst($taskInfo['category']);

        include_once(__DIR__ . "/../categories/$category.php");
        if((new $category($taskInfo))->retrieveData()){
            $taskInfo = $this->task->getTaskInfo();
            $delivery = $taskInfo['delivery'];

            $this->task->roundOff($taskInfo);
            sleep(1);
            echo json_encode(['sleep', $taskInfo['status'], time()]) . "\n\n";
            $result = ($delivery == 1) ? $this->deliverFile(22) : true;
        }
        return (!empty($result)) ? $result : null;
    }

    private function checkProgress(){
        $taskInfo = $this->task->getTaskInfo();
        list($task_id, $status, $link, $count, $delivery) = $taskInfo;
        $mailed = ($delivery == 1);
        $result = [
            'message' => [
                'id' => $task_id, 'status' => $status, 'link' => $link,
                'count' => $count, 'mailed' => $mailed
            ]
        ];
        return (!empty($result)) ? $result : ['end' => true];
    }

    private function deliverFile($code){
        if($task = $this->task->getDeliveryDetails($code)){
            $delivery = $task['delivery'];
            $email = $task['email'];   $name = $task['name'];

            $file = $this->task->getFiles()['final'];

            if ($delivery == 1) {
                require_once(__DIR__ . '/../utility/Mailer.php');
                $date = Dates::formatDateTime(time(),'df');
                $mailer = new Mailer('com', $date);

                if(!$sent = $mailer->sendMail($email, $name, $file)){
                    $error = $mailer->getError();
                }

            }else if($delivery == 2){
                require_once(__DIR__ . '/../utility/Download.php');
                $download = new download();

                if(!$download->download_file($file)){
                    $error = $download->getMessage();
                }
            }

            if(empty($error)){
                Logger::log('tasks', [$file, $delivery, $email, $name]);
                $this->cleanUp(0);
            }else{
                $this->throwError('x06', [$error]);
            }

        }else{
            $this->throwError('x07', [$this->task_id]);
        }

        return empty($error);
    }

    private function cleanUp($code){
        return $code == 0;
    }

    private function abort(){
        $this->cleanUp(1);
        CommError::alert();
    }

}
