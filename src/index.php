<?php
/**
 * Comments and Reviews Retrieval Module.
 * The module accepts URL from the user and downloads comments and reviews data from the URL.
 * Then, parses the data, retrieves the comments, creates and stores them in a CSV file.
 * When retrieval completed, the CSV file is output for instant download by the user.
 * If the data retrieval takes longer than approximately 2 minutes, the module notifies the user and
 * switches to background process after which the CSV file is delivered to the user's provided email address.
 *
 * You need to include and update 'env.php' file to run the App. See 'env_sample.php'
 *
 * PHP Version: 5.6
 *
 * @author: Nwosu Cyprian C
 * Date: March 13, 2018
 */

 require_once(__DIR__ . '/env.php');
 require_once(__DIR__ . '/main/TaskManager.php');

//print_r([$_GET, $_POST]); exit;

// ToDo: Log all hits to this site whether task is completed or not - hts-log.txt

if(isset($_GET['dt']) or isset($_GET['tp']) or isset($_POST['url'])){
    $result = (new TaskManager($_REQUEST))->getResult();
    die(json_encode($result));

}else{
    header("location: index.html");
}
