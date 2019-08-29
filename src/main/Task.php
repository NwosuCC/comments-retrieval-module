<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 17, 2018
 */

require_once(__DIR__ . '/../utility/DB_101.php');
require_once(__DIR__ . '/../main/CsvFile.php');
require_once(__DIR__ . '/../main/CommError.php');

class Task {
    private $file, $id, $params, $csv, $db;
    private static $props = [
        'category', 'product_id', 'product_name', 'delivery', 'email', 'name'
    ];

    public function __construct($params, $stage){
        $this->db = new DB_101();
        $this->params = $params;

        if(!empty($this->params['task_id'])){ return; }

        if(!empty($this->params['url'])){ $this->setCategory(); }

        $this->csv = new CsvFile($this->params, $stage);
        if(in_array($stage, [1,2])){ $this->getFiles(); }
    }

    public function getParams($assoc = true){
        $params = ($assoc !== false) ? $this->params : [];
        if($assoc === false){
            foreach (static::$props as $prop){ $params[] = $this->params[$prop]; }
        }
        return $params;
    }

    public function getFiles($params = []){
        return $this->file = $this->csv->getFiles($params);
    }

    private function throwError($error_code = '', $argv = []) {
        CommError::setError('task', $error_code, $argv);
    }

    private function setCategory(){
        $parsedUrl = $this->params['url'];

        $site = str_replace('.' . $parsedUrl['tld'], '', $parsedUrl['host']);
        $domain = explode('.', $site);
        $this->params['category'] = $category = end($domain);
//        print_r($parsedUrl); exit;

        switch($category){
            case 'youtube' : {
                parse_str($parsedUrl['query'], $vars);
                if(empty($vars['v'])){ $error = true;  break; }
                $this->params['product_id'] = $this->params['product_name'] = $vars['v'];
            } break;

            case 'apple' : {
                $path_components = explode('/', $parsedUrl['path']);
                $id_filter = array_filter($path_components, function($var){
                    return (stristr($var, 'id'));
                });

                $id_component = reset($id_filter);
                $id_component_index = array_search($id_component, $path_components);

                if(count($path_components) < 2 or !$id_component or $id_component_index === false){
                    $error = true;  break;
                }

                $this->params['product_id'] = explode('id', $id_component)[1];
                $this->params['product_name'] = $path_components[$id_component_index - 1];
            } break;

            case 'amazon' : {
                $urlPath = explode('/', $parsedUrl['path']);
                if(count($urlPath) < 4){ $error = true;  break; }
                $this->params['product_id'] = $urlPath[3];
                $this->params['product_name'] = $urlPath[1];
            } break;
        }

        if(!empty($error)){ $this->throwError('05'); }
        return true;
    }

    private function generateID(){
        $tempFile = $this->file['temp'];
        $salt1 = substr(sha1($tempFile), 1, 22);
        $crypt = substr(crypt($tempFile, '$2a$09$i'.$salt1.'$'), 33, 21);
        return $this->id = preg_replace('/[^A-z0-9]*/i', '', $crypt);
    }

    public function saveTaskInfo(){
        $category = $product_id = $product_name = $delivery = $email = $name = '';
        extract($this->params);
        $this->generateID();
        $filename = $this->file['name'];

        // DB store
        $table = 'tasks';
        $columns = [
            'id', 'email', 'product_id', 'category', 'delivery', 'product_name', 'name', 'file'
        ];
        $values = [
            [$this->id, $email, $product_id, $category, $delivery, $product_name, $name, $filename]
        ];

        return $this->db->insert($table, $columns, $values)
            ? [$this->id, $category] : false;
    }

    private function getAccessLink($timestamp){
        $salt1 = substr(sha1($this->id), 1, 22);
        $linkLength = 21 + (3 * substr("{$timestamp}", -1, 1));
        $crypt = substr(crypt($this->id, '$2a$09$l'.$salt1.'$'), 28, $linkLength);
        $newLink = preg_replace('/[^A-z0-9]*/i', '', $crypt);

        $table = 'links';
        $columns = ['id', 'link'];
        $values = [ [$this->id, $newLink] ];
        $where = ["id = ?1 AND status = 1", [$this->id]];

        $saved = $this->db->selectElseInsert($table, $columns, $values, $where);
        $link = $saved ? (is_array($saved) ? $saved['link'] : $newLink) : false;
        if($link){ $this->params['dt'] = $link; }

        return $link ? HOME . "/?dt={$link}" : '';
    }

    public function cancelTask($track_id){

    }

    public function getTaskInfo(){
        $retrievalData = !empty($this->params['id']);
        $track_id = $retrievalData ? $this->params['id'] : $this->params['tp'];
        $info = null;

        $table = 'tasks';
        $where = ["id = ?1", [$track_id]];

        if($track_id and $task = $this->db->select($table, [], $where)->first()){
            $this->id = $track_id;

            if($retrievalData){
                $info = $task;

            }else{
                $total = $task['p_total'];      $current = $task['p_current'];
                $status = $task['status'];      $count = $task['count'];
                $delivery = $task['delivery'];  $taskStartTime = $task['created_at'];

                $complete = ($status == '2');
                $progress = ($total and $current and $current <= $total)
                          ? number_format($current / $total * 100, 1)
                          : ($current > 0 ? 1 : 0);

                if($progress){
                    $link = ($complete) ? $this->getAccessLink($taskStartTime) : '';
                    $info = [$this->id, $progress, $link, $count, $delivery];
                }
            }
        }
        return $info;
    }

    public function updateProgress($figures){
        list($current, $total, $reviewsCount) = $figures;
        $task_id = $this->params['task_id'];

        $table = 'tasks';
        $delivery = "IF( MINUTE( TIMEDIFF(now(), created_at) ) BETWEEN 1 AND 2, 1, delivery)";
        $columnsValues = [
            'p_total' => $total, 'p_current' => $current, 'hits' => 'q|hits + 1',
            'count' => $reviewsCount, 'delivery' => "q|$delivery"
        ];
        $where = [ "id = ?1", [$task_id] ];
        return $this->db->update($table, $columnsValues, $where);
    }

    private function markAsFinished($task_id){
        $table = 'tasks';
        $columnsValues = [ 'status' => 2 ];
        $where = [ "id = ?1", [$task_id] ];
        $aa = $this->db->update($table, $columnsValues, $where);
        echo json_encode(['$aa', $aa, '$where', $where, time()]) . "\n\n";
        return $aa;
    }

    public function roundOff($task){
        // make a final permanent copy
        $this->csv->createFinalCsvFile();

        // update db before file can be accessed
        $task_id = $task['id'];
        $task_startTime = $task['created_at'];
        if($this->markAsFinished($task_id)){
            $this->getAccessLink($task_startTime);
        }

        // remove temp file
        $this->csv->removeTempFiles();
    }

    public function getDeliveryDetails($code){
        $linkID = !empty($this->params['dt']) ? $this->params['dt'] : null;
        if(empty($linkID)){ return false; }

        $table = 'tasks t JOIN links l ON l.id = t.id';
        $columns = ['t.category', 't.delivery', 't.email', 't.name', 't.file', 'l.link'];
        $where = ["l.link = ?1 AND l.status = 1 AND t.status = 2", [$linkID]];

        if($task = $this->db->select($table, $columns, $where, 22)->first()){
            $category = $task['category'];   $file = $task['file'];
            $this->getFiles([$category, $file]);
        }
        echo json_encode(['$task', $task, '$code', $code, '$linkID', $linkID, time()]) . "\n\n";
        return $task;
    }

}
