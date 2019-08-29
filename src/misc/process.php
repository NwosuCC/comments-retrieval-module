<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 16, 2018
 */

require_once(__DIR__ . '/../env.php');
require_once(__DIR__ . '/../main/Retrieval.php');
require_once(__DIR__ . '/../main/TaskManager.php');

if(!empty($argv)){
    // Grab inputs passed through 'exec()' | 'popen()' command.
    $arguments = explode('00100101', $argv[1]);

    // Initiate background process if valid category is set
    if($arguments[0]){
        $request = [
            'category' => $arguments[0], 'task_id' => $arguments[1],
        ];
        new TaskManager($request);

    }else{
        // Error
        echo "0, url";
    }

}
