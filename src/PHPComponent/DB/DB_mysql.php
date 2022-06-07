<?php
abstract class DB_mysql{
    public static $_conn = null;
    public static $_mysqli_conn = null;
    public static $_config = null;
    static function getInstance($config)
    {
        if (self::$_conn === null) self::$_conn = new MysqliDb(array('host' => property_exists($config, "host") ? $config->host : "localhost", 'username' => $config->database_account, 'password' => $config->database_password, 'db' => $config->database_name, 'charset' => 'utf8mb4'));
        self::$_config = $config;
    }
    static function init_mysqli_conn(){
        self::$_mysqli_conn = new mysqli(property_exists(self::$_config, "host") ? self::$_config->host : "localhost",  self::$_config->database_account, self::$_config->database_password, self::$_config->database_name);
    }
    static function close_mysqli_conn(){
        if (self::$_mysqli_conn !== null) self::$_mysqli_conn->close();
    }
    static function rawQuery($sql)
    {
        if (self::$_mysqli_conn === null) self::init_mysqli_conn();
        $result = self::$_mysqli_conn->query($sql);
        if($result == false)
            throw new Exception(self::$_mysqli_conn->error);
        return $result;
    }

    static function getTableList()
    {
        if (self::$_mysqli_conn === null) self::init_mysqli_conn();
        $tableList = [];
        $result = self::$_mysqli_conn->query("SHOW TABLES FROM `" . self::$_config->database_name . "`");
        while ($row = $result->fetch_row()) {
            if($row[0] == "Log" || $row[0] == "Token" || $row[0] == "UserAuth")
                $tableList[] = array("name" => $row[0], "type" => "SYSTEM");
            else
                $tableList[] = array("name" => $row[0], "type" => "GENERAL");
        }
        return $tableList;
    }

    static function getTableStructure($tableName)
    {
        if (self::$_mysqli_conn === null) self::init_mysqli_conn();
        $tableList = [];
        $result = self::$_mysqli_conn->query("select * from INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . self::$_config->database_name . "' AND Table_Name = '" . $tableName . "'");
        while ($row = $result->fetch_row()) {
            $isNullAble = $row[6] == "YES" ? true: false;
            $length = $row[8];
            $type = null;
            $rawType = $row[7];
            $defaultValue = $row[5];
            if($defaultValue == "''" || $defaultValue == '""')
                $defaultValue = "";
            switch($rawType){
                case "varchar":
                    if($length == 1024)
                        $type = DB_ColumnType::VARCHAR;
                    else
                        $type = DB_ColumnType::ARRAY;
                    break;
                case "int":
                    $type = DB_ColumnType::INT;
                    break;
                case "decimal":
                    $type = DB_ColumnType::DECIMAL;
                    break;
                case "tinyint":
                    $type = DB_ColumnType::BOOLEAN;
                    break;
                case "datetime":
                    $type = DB_ColumnType::DATE;
                    break;
            }
            $tableList[] = array("name" => $row[3], "defaultValue" => $defaultValue, "isNullAble" => $isNullAble, "type" => $type);
        }
        return $tableList;
    }

    static function createTable($tableName, $columnList)
    {
        return DB::rawQuery(DB_Controller::createTable($tableName, $columnList));
    }

    static function insertLog($action, $value = "")
    {
        $value = json_encode($value);
        if(strlen( $value) > 60000)
            $value = substr($value, 0,60000) . '...';
        $data = array("action" => $action, 'user' => (isset($GLOBALS['currentUser'])) ? stdClassToArray($GLOBALS['currentUser']) : "",  "header" => getallheaders(), "server" => $_SERVER, "parameter" => getParameter($_POST, $_GET), 'data' => $value);
        unset($data['ID']);
        $id = self::$_conn->insert('Log', self::convertParametersToString($data, Log::getFieldsWithType()));
        // if ($id == false) throw new Exception(self::$_conn->getLastError());
    }
    static function getRawJoin($class, $cols = null) //TO BE Deprecated
    {
        self::$_conn->where($class::getSelfName() . "." .'isDeleted', 0);
        $result = self::$_conn->get($class::getSelfName(), null, $cols);
        if(method_exists($class, "permissionGetHandling"))
            $result = $class::permissionGetHandling($result);
        // self::insertLog("GET", $result);
        return $result;
    }

    static function getRaw($class, $options = array())
    {
        self::$_conn->where($class::getSelfName() . "." .'isDeleted', 0);
        if(method_exists($class, "sqlQueryModification") && !self::isFullRight($options))
            self::$_conn = $class::sqlQueryModification(self::$_conn);
        $result = self::$_conn->get($class::getSelfName(), null, null);
        if(method_exists($class, "permissionGetHandling") && !self::isFullRight($options))
            $result = $class::permissionGetHandling($result);
        // self::insertLog("GET", $result);
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
        return sizeof(self::getRaw($class::getSelfName()));
    }

    static function getByID($class, $ID, $options = null)
    {
        try{
            self::$_conn->where("ID", $ID);
            $item = self::getRaw($class::getSelfName(), $options);
            $item = (sizeof($item) > 0) ? new $class($item[0], $options) : null;
            // if($item != null){
            //     $cachedList = array();
            //     $joinClassList = isset($options["joinClass"]) ? $options["joinClass"] : array();
            //     foreach ($joinClassList as $joinClass) {
            //         $cachedList[$joinClass::getSelfName()] = DB::getAllMap($joinClass);
            //     }
            //     $item->customAssignField($cachedList, array("computed" => isset($options["computed"]) ? $options["computed"] : array(), "joinClass" => isset($options["joinClass"]) ? $options["joinClass"] : array(), "mask" => isset($options["mask"]) ? $options["mask"] : array()));
            // }
            return $item;
            }catch(Exception $e){
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
        self::$_conn->where($column, $value);
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
        self::$_conn->delete($class::getSelfName());
    }
    private static function updateRaw($parameters, $class){
        unset($parameters['createdDate']);
        unset($parameters['modifiedDate']);
        // unset($parameters['isDeleted']);
        if(method_exists($class, "permissionUpdateHandling") && !$class::permissionUpdateHandling($parameters, self::getByID($class, $parameters["ID"])))
            throw new Exception("Role Permission Denied");
        $parameters = (array) $parameters;
        self::$_conn->where("ID", $parameters["ID"]);
        $now = new DateTime();
        $parameters["modifiedDate"] = $now->format('Y-m-d H:i:s');
        $result = self::$_conn->update($class::getSelfName(), array_merge(self::convertParametersToString($parameters, $class::getFieldsWithType()), array("modifiedUserID" => isset($GLOBALS['currentUser'])? $GLOBALS['currentUser']->ID: null )));
        if ($result == false) throw new Exception(self::$_conn->getLastError());
        // self::insertLog("UPDATE", stdClassToArray(self::getByID($class::getSelfName(), $parameters["ID"], BaseModel::SYSTEM)));
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
        self::$_conn->where('ID', $ID);
        if(!self::$_conn->delete($class::getSelfName()))
            throw new Exception("Delete Error");
    }

    static function insert($parameters, $class){
        $parameters = filterParameterByClass($parameters, $class);
        return self::insertRaw($parameters, $class);
    }
    static function insertMulti($parameters, $class){
        if(method_exists($class, "permissionInsertHandling") && !$class::permissionInsertHandling($parameters))
            throw new Exception("Role Permission Denied");
        $typeList =  $class::getFieldsWithType();
        $mappedParameter = [];
        foreach($parameters as $row){
            $row = filterParameterByClass($row, $class);
            unset($row['ID']);
            unset($row['createdDate']);
            unset($row['modifiedDate']);
            unset($row['isDeleted']);
            $row = array_merge(self::convertParametersToString(self::addDefaultValue($row, $typeList), $typeList), array("createdUserID" => isset($GLOBALS['currentUser'])? $GLOBALS['currentUser']->ID: null ));
            $mappedParameter[] = $row;
        }
        self::$_conn->insertMulti($class::getSelfName(), $mappedParameter);
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
        $id = self::$_conn->insert($class::getSelfName(), array_merge(self::convertParametersToString(self::addDefaultValue($parameters, $typeList), $typeList), array("createdUserID" => isset($GLOBALS['currentUser'])? $GLOBALS['currentUser']->ID: null )));
        if ($id == false) throw new Exception(self::$_conn->getLastError());
        // self::insertLog("INSERT", $parameters);
        return $id;
    }
    static function join($db, $dbObjectList, $whereConditionList = array()){
        self::addWhereConditionList($whereConditionList);
        $field_query = "";
        foreach ($dbObjectList as $dbObject) {
            $field_query .= ", " . self::fieldQueryForSelect($dbObject["db"]::getSelfName(), $dbObject["mode"] || BaseModel::SYSTEM);
            self::$_conn->join($dbObject["db"]::getSelfName() . " " . $dbObject["db"]::getSelfName(), $dbObject["joinQuery"], "LEFT");
        }
        return parseValue(self::getRawJoin($db::getSelfName(), $db::getSelfName() . ".* " .  $field_query));
    }

    static function startTransaction()
    {
        self::$_conn->startTransaction();
    }

    static function rollback()
    {
        self::$_conn->rollback();
    }

    static function commit()
    {
        self::$_conn->commit();
    }

    private static function addWhereConditionList($whereConditionList){
        foreach ($whereConditionList as $key => $value) {
            if($value === null){
                self::$_conn->where($key, NULL, '<=>');
            }else{
                self::$_conn->where($key, $value);
            }
        }
    }

    private static function fieldQueryForSelect($class, $mode = BaseModel::PUBLIC){
        $sql = "";
        $fields = filter($class::getFields(), function($field){
            return ($field["type"] !== BaseTypeEnum::TO_MULTI && $field["type"] !== BaseTypeEnum::TO_SINGLE && $field["type"] !== BaseTypeEnum::ARRAY_OF_ID && $field["type"] !== BaseTypeEnum::COMPUTED);
        });
        foreach ($fields as $value) {
            $sql .=  $class::getSelfName() . "." . $value["key"] . " as '" . $class::getSelfName() . "." . $value["key"] . "', ";
        }
        return substr_replace($sql, " ", -2);
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

    private static function isFullRight($options){
        return (isset($options["fullRight"]) && $options["fullRight"] == true);
    }
    
}