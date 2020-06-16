<?php
class DB{
    private static $_conn = null;
    static function getInstance($host, $username, $password, $db){
        if(self::$_conn === null) self::$_conn = new MysqliDb (array ('host' => $host,'username' => $username, 'password' => $password,'db'=> $db, 'charset' => 'utf8mb4'));
    }
    static function rawQuery($sql){
        return self::$_conn->rawQuery($sql);
    }
    static function get($class){
        $modelList = array();
        $rawDataList = self::$_conn->get($class::getSelfName());
        foreach($rawDataList as $data){
            array_push($modelList,  new $class($data));
        }
        return $modelList;
    }

    static function getByID($class, $ID){
        self::$_conn->where ("ID", $ID);
        return new $class(self::$_conn->get($class::getSelfName())[0]);
    }

    static function update($parameters, $class){ 
        $parameters = (array) $parameters;
        self::$_conn->where("ID", $parameters["ID"]);
        $result = self::$_conn->update($class::getSelfName(), $parameters);
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
        $id = self::$_conn->insert($class::getSelfName(), $parameters);
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
?>
