<?php
/*
 * Very basic DB library; secure still.
 */

class DB_101 {
    private static $connection;
    private $sql_string, $result, $rows = [];

    public function __construct(){
        $host = $user = $password = $db = '';
        extract($_ENV['database']);
        static::$connection = new MySQLi($host, $user, $password, $db);
    }

    private function escape(Array $values){
        $escaped_values = [];
        foreach($values as $key => $value){
            if(is_array($value)){
                $escaped_values[$key] = $this->escape($value);
            }else{
                $value = stripslashes(htmlspecialchars(trim($value)));
                $escaped_values[$key] = static::$connection->real_escape_string($value);
            }
        }
        return $escaped_values;
    }

    private function throwError($code){
        $codes = [
            '01' => 'DB operation failed!',
            '02' => static::$connection->error."; \nProblem with Query \"{$this->sql_string}\"\n\n",
        ];

        if(empty($codes[$code])){ $code = '01'; }
        die(json_encode(['code' => $code, 'error' => $codes[$code]]));
    }

    private function validateVars($queryType, $vars){
        switch ($queryType) {
            case 'insert' : {
                list($columns, $values) = $vars;
                if(empty($columns) or !is_array($columns) or empty($values) or !is_array($values)
                    or !is_array($values[0]) or count($columns) !== count($values[0])){
                    $error = 'DB::insert() requires valid Array $columns and Array $values.';
                    $syntax1 = 'Array $columns: ["fruit","tally","isFavourite"]';
                    $syntax2 = 'Array $values: [ ["pear",23,false], ["apple",57,true], ["orange",30,true] ]';
                }
            } break;

            case 'update' : {

            }

            default : {}
        }
    }

    private function run_sql(){
        if ($this->result = static::$connection->query($this->sql_string)){
            return $this;
        } else {
            $this->throwError('02');
        }
    }

    private function add_single_quotes(Array $values){
        foreach($values as $key => $value){
            $quote_index = strpos($value,"'");
            $slash_index = strpos($value,"\\");
            if($quote_index !== false and ($quote_index - 1) !== $slash_index){
                $values = false;  break;
            }
            $vars = explode('|', $value);
            $value = array_pop($vars);
            $values[$key] = !in_array('q', $vars) ? "'{$value}'" : $value;
        }
        return $values;
    }

    private function where($where){
        list($string, $vars) = $where;
        $vars = $this->escape($vars);
        $where = '';
        foreach ($vars as $index => $var){
            list($var) = $this->add_single_quotes([$var]);
            $where .= str_replace('?'.($index + 1), $var, $string);
        }
        return "WHERE {$where}";
    }

    private function fetch(){
        if(!$this->result){ return; }
        $this->rows = [];
        while($row = $this->result->fetch_assoc()){
            $this->rows[] = $row;
        }
    }

    public function all(){
        return $this->rows;
    }

    public function first(){
        return reset($this->rows);
    }

    public function last(){
        return end($this->rows);
    }

    public function select($table, Array $columns, Array $where = [], $code = ''){
        $columns = $columns ? $this->escape($columns) : ['*'];
        $columns = implode(',', $columns);

        $where = $where ? $this->where($where) : '';

        $this->sql_string = "SELECT {$columns} FROM {$table} {$where}";
        if($code){ echo $this->sql_string; } // todo : review this
        $this->run_sql()->fetch();

        return $this;
    }

    public function selectElseInsert($table, Array $columns, Array $values, Array $where = []){
        $exists = $this->select($table, $columns, $where)->first();
        return $exists ?: $this->insert($table, $columns, $values);
    }

    public function insert($table, Array $columns, Array $values){
        $this->validateVars('insert', [$columns, $values]);

        $columns = implode(',', $this->escape($columns));
        $values = $this->escape($values);

        $allValues = [];
        foreach ($values as $value){
            $value = $this->add_single_quotes($this->escape($value));
            $allValues[] = '(' . implode(',', $value) . ')';
        }
        $allValues = implode(',', $allValues);

        $this->sql_string = "INSERT INTO {$table} ({$columns}) VALUES {$allValues}";
        $this->run_sql();

        return $this->result;
    }

    public function update($table, Array $columnsValues, Array $where){
        $this->validateVars('update', [$columnsValues]);

        $allValues = [];   $result = 0;

        foreach ($columnsValues as $column => $value){
            $value = $this->add_single_quotes($this->escape([$value]));
            $allValues[] = "$column = " . array_shift($value);
        }
        $allValues = implode(',', $allValues);

        if($where = $where ? $this->where($where) : ''){
            $this->sql_string = "UPDATE {$table} SET {$allValues} {$where}";
            $this->run_sql();
            $result = static::$connection->affected_rows;
        }
        return $result;
    }

}

?>