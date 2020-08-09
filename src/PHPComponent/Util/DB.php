<?php
class DB{
    private static $_conn = null;
    static function getInstance($host, $username, $password, $db){        
        if(self::$_conn === null) self::$_conn = new MysqliDb (array ('host' => $host,'username' => $username, 'password' => $password,'db'=> $db, 'charset' => 'utf8mb4'));
    }
    static function rawQuery($sql){
        return self::$_conn->rawQuery($sql);
    }
    static function get($class, $model = BaseModel::PUBLIC){
        $modelList = array();
        $rawDataList = self::$_conn->get($class::getSelfName());
            foreach($rawDataList as $data){
                array_push($modelList,  new $class($data, $model));
            }
            return $modelList;
    }
    static function getCount($class, $whereConditionList){
        foreach($whereConditionList as $key => $value){
            self::$_conn->where($key, $value);
        }
        return self::$_conn->getValue($class::getSelfName(), "count(*)");
    }
    static function join($db, $dbObjectList, $whereClause = ''){
        $field_query = "";
        $join_query = "";
        foreach ($dbObjectList as $dbObject) {
            $field_query .= ", ". fieldQueryForSelect($dbObject["db"]::getSelfName());
            $join_query .= " LEFT JOIN ". $dbObject["db"]::getSelfName() . " ON " . $dbObject["joinQuery"] . " ";
        }
        $sql = "SELECT " . $db::getSelfName() .".* " .  $field_query . "FROM " . $db::getSelfName() . " " . $join_query . $whereClause;
        $data = self::$_conn->rawQuery($sql);
        return $data;
    }

    static function getByID($class, $ID, $model = BaseModel::PUBLIC){
        self::$_conn->where("ID", $ID);
        return new $class(self::$_conn->get($class::getSelfName())[0], $model);
    }

    static function deleteByWhereCondition($class,$whereConditionList){
        foreach($whereConditionList as $key => $value){
            self::$_conn->where($key, $value);
        }
        self::$_conn->delete($class::getSelfName());
    }

    static function getByWhereCondition($class, $whereConditionList, $model = BaseModel::PUBLIC){
        foreach($whereConditionList as $key => $value){
            self::$_conn->where($key, $value);
        }
        $modelList = array();
        $rawDataList = self::$_conn->get($class::getSelfName());
        foreach($rawDataList as $data){
            array_push($modelList,  new $class($data, $model));
        }
        return $modelList;
    }

    static function getByColumn($class, $column, $value, $model = BaseModel::PUBLIC){
        self::$_conn->where ($column, $value);
        $modelList = array();
        $rawDataList = self::$_conn->get($class::getSelfName());
        foreach($rawDataList as $data){
            array_push($modelList,  new $class($data, $model));
        }
        return $modelList;
    }

    static function update($parameters, $class){ 
        $parameters = (array) $parameters;
        self::$_conn->where("ID", $parameters["ID"]);
        $now = new DateTime();
        $parameters["modifiedDate"] = $now->format('Y-m-d H:i:s');
        $result = self::$_conn->update($class::getSelfName(), convertParametersToString($parameters));
        if($result == false) throw new Exception(self::$_conn->getLastError());
    }

    static function delete($ID, $class){
        self::$_conn->where("ID", $ID);
        $result = self::$_conn->delete($class::getSelfName());
        if($result == false) throw new Exception(self::$_conn->getLastError());
    }

    static function deleteAll($class){
        $result = self::$_conn->delete($class::getSelfName());
        if($result == false) throw new Exception(self::$_conn->getLastError());
    }

    static function insert($parameters, $class){ 
        unset($parameters['ID']);
        $result = array();
        foreach($parameters as $key => $value){
            if(is_array($value)){
                $result[$key] = json_encode($value);
            }else{
                $result[$key] = $value;
            }
        }
        $id = self::$_conn->insert($class::getSelfName(), $result);
        if($id == false) throw new Exception(self::$_conn->getLastError());
        return $id;
    }

    static function insertWithID($parameters, $class){ 
        $id = self::$_conn->insert($class::getSelfName(), $parameters);
        if($id == false) throw new Exception(self::$_conn->getLastError());
        return $id;
    }
    
    static function startTransaction(){
        self::$_conn->startTransaction();
    }

    static function rollback(){
        self::$_conn->rollback();
    }

    static function commit(){
        self::$_conn->commit();
    }
}

function fieldQueryForSelect($class, $mode = BaseModel::PUBLIC){
    $sql = "";
    foreach($class::getFields($mode) as $value){
        $sql .=  $class::getSelfName() . "." . $value . " as '". $class::getSelfName() . "." . $value . "', ";
    }
    return substr_replace($sql ," ",-2);
}

function convertParametersToString($parameters){
    $result = array();
    foreach($parameters as $key => $value){
        if(is_array($value)) 
            $result[$key] = json_encode($value);
        else
            $result[$key] = $value;
    }
    return $result;
}
?>