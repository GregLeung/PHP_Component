<?php
abstract class DB_mssql{
    public static $_conn = null;
    public static $database = null;
    public static $stmt = null;
    public static $whereConditionString = "";
    static function getInstance($config) //DONE
    {
        $serverName = $config->host;   
        $database = $config->database_object;
        try {
            if (self::$_conn === null){
                self::$_conn = new PDO( "sqlsrv:server=$serverName;Database = $database", NULL, NULL);
                self::$_conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );   
                self::$database = $config->database_name;
            }
        }  
        catch( PDOException $e ) {
            throw $e;
        }  
        // if (self::$_conn === null) self::$_conn = new MysqliDb(array('host' => $host, 'username' => $username, 'password' => $password, 'db' => $db, 'charset' => 'utf8mb4'));
    }

    static function rawQuery($sql)
    {
        return self::$_conn->rawQuery($sql);
    }
    static function insertLog($action, $value = "")
    {
        $value = json_encode($value);
        if(strlen( $value) > 60000)
            $value = substr($value, 0,60000) . '...';
        $data = array("action" => $action, 'user' => (isset($GLOBALS['currentUser'])) ? stdClassToArray($GLOBALS['currentUser']) : "",  "header" => getallheaders(), "server" => $_SERVER, "parameter" => getParameter($_POST, $_GET), 'data' => $value);
        unset($data['ID']);
        $id = self::$_conn->insert('Log', self::convertParametersToString($data, Log::getFieldsWithType()));
        if ($id == false) throw new Exception(self::$_conn->getLastError());
    }
    static function getRaw($class, $options = array())
    {        
        $result = array();
        $query = "SELECT * FROM [" . self::$database . "].[" . self::$database ."].[" .$class::getSelfName() ."] WHERE isDeleted = 0 " ;
        $query .= self::$whereConditionString;
        self::$stmt = self::$_conn->query($query); 
        while ( $row = self::$stmt->fetch( PDO::FETCH_ASSOC ) ){   
            array_push($result, $row);
        }
        self::clearWhere();
        if(method_exists($class, "permissionGetHandling") && !self::isFullRight($options))
            $result = $class::permissionGetHandling($result);
        return $result;
    }
    static function getAll($class, $options = null){ // TO Be Deprecated
        $modelList = self::rawDataListTModelList(self::getRaw($class), $class,  $options);
        return $modelList;
    }

    static function getAll_new($class, $options = null){
        return getAllApi($options, $class);
    }

    static function getAllMap($class, $options = null){
        $modelList = self::rawDataListTModelMap(self::getRaw($class), $class,  $options);
        return $modelList;
    }

    static function getCount($class, $whereConditionList)
    {
        self::addWhereConditionList($whereConditionList);
        $count = sizeof(self::getRaw($class::getSelfName()));
        return $count;
    }

    static function getByID($class, $ID, $options = null)
    {
        try{
            // self::$_conn->where("ID", $ID);
            self::setWhere("ID", $ID);
            $result = self::getRaw($class::getSelfName(), $options);
            return (sizeof($result) > 0) ? new $class($result[0], $options) : null;
        }catch(Exception $e){
            writeCustomLog("Error");
            return null;
        }
    }

    static function getByWhereCondition($class, $whereConditionList,  $options = null)
    {
        self::addWhereConditionList($whereConditionList);
        $modelList = self::rawDataListTModelList(self::getRaw($class), $class, $options);
        return $modelList;
    }

    static function getByColumn($class, $column, $value,  $options = null)
    {
        $options = isset($options) ? $options : array();
        self::setWhere($column, $value);
        $modelList = self::rawDataListTModelList(self::getRaw($class), $class, $options);
        return $modelList;
    }

    static function deleteByWhereCondition($class, $whereConditionList)
    {
        self::addWhereConditionList($whereConditionList);
        self::$_conn->delete($class::getSelfName());

    }

    static function deleteRealByWhereCondition($class, $whereConditionList)
    {
        self::addWhereConditionList($whereConditionList);
        $sql = "DELETE FROM [" . self::$database . "].[" . self::$database ."].[" .$class::getSelfName() ."] ";
        self::$whereConditionString = substr(self::$whereConditionString, 4);
        $sql .= "WHERE " . self::$whereConditionString;
        self::$stmt= self::$_conn->prepare($sql)->execute();
        self::clearWhere();
    }
    private static function updateRaw($parameters, $class){
        unset($parameters['createdDate']);
        unset($parameters['modifiedDate']);
        if(method_exists($class, "permissionUpdateHandling") && !$class::permissionUpdateHandling($parameters, self::getByID($class, $parameters["ID"])))
            throw new Exception("Role Permission Denied");
        $parameters = (array) $parameters;       
        $parameters = self::convertParametersToString($parameters, $class::getFieldsWithType());
        $sql = "UPDATE [" . self::$database . "].[" . self::$database ."].[" .$class::getSelfName() ."] SET ";
        foreach ($parameters as $key => $value) {
            if($key == "ID")
                continue;
            $sql .= $key . " = :" . $key . " ,";
        }
        $sql = substr($sql, 0, -1);
        $sql .= "WHERE ID = " . $parameters["ID"];
        unset($parameters["ID"]);
        self::$stmt= self::$_conn->prepare($sql)->execute($parameters);
        self::clearWhere();
    } 
    static function update($parameters, $class)
    {
        $parameters = filterParameterByClass($parameters, $class);
        self::updateRaw($parameters, $class);
    }
    static function delete($ID, $class){
        self::updateRaw(array("ID"=>$ID, "isDeleted"=>1),$class);
    }

    static function realDelete($ID, $class){
        // self::$_conn->where('ID', $ID);
        self::setWhere("ID", $ID);
        if(!self::$_conn->delete($class::getSelfName()))
            throw new Exception("Delete Error");
    }

    static function insert($parameters, $class){
        $parameters = filterParameterByClass($parameters, $class);
        return self::insertRaw($parameters, $class);
    }
    static function isWhereConditionExisted($class, $whereConditionList){
        return sizeof(DB::getByWhereCondition($class, $whereConditionList)) > 0;
    }

    private static function insertRaw($parameters, $class){
        unset($parameters['ID']);
        unset($parameters['createdDate']);
        unset($parameters['modifiedDate']);
        unset($parameters['isDeleted']);
        if(method_exists($class, "permissionInsertHandling") && !$class::permissionInsertHandling($parameters))
            throw new Exception("Role Permission Denied");
        $typeList =  $class::getFieldsWithType();
        $parameters = self::convertParametersToString(self::addDefaultValue($parameters, $typeList), $typeList);
        $sql = "INSERT INTO [" . self::$database . "].[" . self::$database ."].[" .$class::getSelfName() ."] (";
        foreach ($parameters as $key => $value) {
            $sql .= " " . $key . " ,";
        }
        $sql = substr($sql, 0, -1);
        $sql .= ") VALUES ( ";
                foreach ($parameters as $key => $value) {
            $sql .= " :" . $key . " ,";
        }
        $sql = substr($sql, 0, -1);
        $sql .= ")";
        self::$stmt= self::$_conn->prepare($sql)->execute($parameters);
        return self::$_conn->lastInsertId();
    }

    static function startTransaction() //DONE
    {
        self::$_conn->beginTransaction();
    }

    static function rollback()
    {
        self::$stmt = null;
        self::$_conn->rollback();
        self::$_conn = null;
    }

    static function commit() //DONE
    {
        self::$stmt = null;
        self::$_conn->commit();
        self::$_conn = null;
    }

    private static function addWhereConditionList($whereConditionList){
        foreach ($whereConditionList as $key => $value) {
            self::setWhere($key, $value);
        }
    }
    private static function setWhere($column, $value){
        self::$whereConditionString .= "AND " . $column . " = '" . $value . "'";
    }
    private static function clearWhere(){
        self::$whereConditionString = "";
    }
    
    private static function addDefaultValue($parameters, $fieldTypeList){
        foreach($fieldTypeList as $field){
            if(!array_key_exists($field["key"],$parameters) || $parameters[$field["key"]] === null){
                switch($field["type"]){
                    case BaseTypeEnum::ARRAY:
                        $parameters[$field["key"]] = "[]";
                    break;
                    case BaseTypeEnum::OBJECT:
                        $parameters[$field["key"]] = "{}";
                    break;
                }
            }
        }
        return $parameters;
    }

    private static function rawDataListTModelMap($rawDataList, $class, $options){
        $modelMap = array();
        foreach ($rawDataList as $data) {
            $modelMap[$data["ID"]] = new $class($data, $options);
        }
        return $modelMap;
    }

    private static function rawDataListTModelList($rawDataList, $class, $options){
        $modelList = array();
        foreach ($rawDataList as $data) {
            array_push($modelList,  new $class($data, $options));
        }
        return $modelList;
    }

    private static function convertParametersToString($parameters, $typeList)
    {
        $result = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value) && find($typeList, function($data)use($key){return $data["key"] === $key;})["type"] === BaseTypeEnum::INT_ARRAY){
                $arrayValue = map($value, function($data){return intval($data);});
                sort($arrayValue);
                $result[$key] = json_encode($arrayValue);
            }
            else if (is_array($value))
                $result[$key] = json_encode($value);
            else
                $result[$key] = $value;
        }
        return $result;
    }

    private static function isFullRight(){
        return (isset($options["fullRight"]) && $options["fullRight"] == true);
    }
}