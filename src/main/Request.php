<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 17, 2018
 */

require_once(__DIR__ . '/../utility/Url.php');

class Request
{
    private $stage = 0;
    protected $fields = [
        '1' => ['url', 'delivery', 'email', 'name'],
        '2' => ['category', 'task_id'],
        '3' => ['tp'],
        '4' => ['dt']
    ];
    private $labels = [
        '1' => ['Product URL', 'Delivery method', 'Valid email address', 'Name'],
        '2' => ['Category', 'Task ID'],
        '3' => ['Tracking poll'],
        '4' => ['Delivery track_id']
    ];
    private $url, $email, $name = 'Client', $delivery;
    private $category, $task_id, $tp, $dt;

    private $error;

    public function __construct($request, $stage){
        $this->stage = $stage;
        $this->validateInputs($request);
    }

    public function getVars(){
        $stage = $this->stage;
        $vars = [
            '1' => [ $this->url, $this->delivery, $this->email, $this->name ],
            '2' => [ $this->category, $this->task_id ],
            '3' => [ $this->tp ],
            '4' => [ $this->dt ]
        ];
        return $this->error ? false : array_combine($this->fields[$stage], $vars[$stage]);
    }

    private function clean($data) {
        return trim(stripslashes(htmlspecialchars($data)));
    }

    private function throwError($errorCode = '', $argv = []) {
        $this->error = true;
        CommError::setError('request', $errorCode, $argv);
    }

    private function validateInputs($request) {
        $stage = $this->stage;  $fields = $this->fields;

        if(!array_key_exists($stage, $fields)){
            $this->throwError();
        }else if(!$request){
            $this->throwError('11', [$fields[$stage][0]]);
        }

        $labels = $this->labels[$stage];

        $rules = [
            // Stage 1: Start Task
            'url' => [
                'errorCode' => 12,
                'function' => function($rules, $post){
                    return $this->url = Url::parseUrl($post['url']);
                }
            ],
            'email' => [
                'errorCode' => 13,
                'function' => function($rules, $post){
                    return (filter_var($post['email'],FILTER_VALIDATE_EMAIL));
                }
            ],
            'name' => [
                'regexp' => "/^[a-zA-Z0-9 ]*$/",
                'errorCode' => 14,
                'function' => function($rules, $post){
                    return (preg_match($rules['name']['regexp'], $post['name']));
                }
            ],
            'delivery' => [
                'regexp' => "/[^12]/",
                'errorCode' => 15,
                'function' => function($rules, $post){
                    return !(preg_match($rules['delivery']['regexp'], $post['delivery']));
                }
            ],
            // Stage 2: Retrieval
            'category' => [
                'regexp' => "/[^A-z0-9]/",
                'errorCode' => 0,
                'function' => function($rules, $post){
                    return !(preg_match($rules['category']['regexp'], $post['category']));
                }
            ],
            'task_id' => [
                'regexp' => "/[^A-z0-9]/",
                'errorCode' => 0,
                'function' => function($rules, $post){
                    return !(preg_match($rules['dt']['regexp'], $post['task_id']));
                }
            ],
            // Stage 3: Track Progress
            'tp' => [
                'regexp' => "/[^A-z0-9]/",
                'errorCode' => 16,
                'function' => function($rules, $post){
                    return !(preg_match($rules['tp']['regexp'], $post['tp']));
                }
            ],
            // Stage 4: File Delivery (Download)
            'dt' => [
                'regexp' => "/[^A-z0-9]/",
                'errorCode' => 17,
                'function' => function($rules, $post){
                    return !(preg_match($rules['dt']['regexp'], $post['dt']));
                }
            ],
        ];

        foreach ($fields[$stage] as $index => $field){
            if(!array_key_exists($field, $request)){
                $this->throwError(11, [$labels[$index]]);
            }
            if(!$valid_value = $rules[$field]['function']($rules, $request)){
                $this->throwError($rules[$field]['errorCode']);
            }
            $this->$field = $field != 'url' ? $this->clean($request[$field]) : $valid_value;
        }
    }

}
