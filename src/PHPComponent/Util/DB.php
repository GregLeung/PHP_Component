<?php
class DB
{
    private static $_conn = null;
    static function getInstance($host, $username, $password, $db)
    {
        if (self::$_conn === null) self::$_conn = new MysqliDb(array('host' => $host, 'username' => $username, 'password' => $password, 'db' => $db, 'charset' => 'utf8mb4'));
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
        $id = self::$_conn->insert('Log', convertParametersToString($data));
        if ($id == false) throw new Exception(self::$_conn->getLastError());
    }
    static function getRaw($class, $cols = null)
    {
        self::$_conn->where($class::getSelfName() . "." .'isDeleted', 0);
        $result = self::$_conn->get($class::getSelfName(), null, $cols);
        self::insertLog("GET", $result);
        return $result;
    }
    static function getAll($class, $mode = BaseModel::PUBLIC)
    {
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode);
        return $modelList;
    }

    static function getCount($class, $whereConditionList)
    {
        self::addWhereConditionList($whereConditionList);
        $count = self::getRaw($class::getSelfName(), "count(*)")[0]['count(*)'];
        return $count;
    }
    static function join($db, $dbObjectList, $whereConditionList = array()){
        self::addWhereConditionList($whereConditionList);
        $field_query = "";
        foreach ($dbObjectList as $dbObject) {
            $field_query .= ", " . fieldQueryForSelect($dbObject["db"]::getSelfName(), $dbObject["mode"] || BaseModel::PUBLIC);
            self::$_conn->join($dbObject["db"]::getSelfName() . " " . $dbObject["db"]::getSelfName(), $dbObject["joinQuery"], "LEFT");
        }
        return self::getRaw($db::getSelfName(), $db::getSelfName() . ".* " .  $field_query);
    }

    static function getByID($class, $ID, $model = BaseModel::PUBLIC)
    {
        self::$_conn->where("ID", $ID);
        $result = self::getRaw($class::getSelfName());
        return (sizeof($result) > 0) ? new $class($result[0], $model) : null;
    }

    static function getByWhereCondition($class, $whereConditionList, $mode = BaseModel::PUBLIC)
    {
        self::addWhereConditionList($whereConditionList);
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode);
        return $modelList;
    }

    static function getByColumn($class, $column, $value, $mode = BaseModel::PUBLIC)
    {
        self::$_conn->where($column, $value);
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode);
        return $modelList;
    }

    static function deleteByWhereCondition($class, $whereConditionList)
    {
        self::addWhereConditionList($whereConditionList);
        self::$_conn->delete($class::getSelfName());
    }
    private static function updateRaw($parameters, $class){
        $parameters = (array) $parameters;
        self::$_conn->where("ID", $parameters["ID"]);
        $now = new DateTime();
        $parameters["modifiedDate"] = $now->format('Y-m-d H:i:s');
        $result = self::$_conn->update($class::getSelfName(), convertParametersToString(addDefaultValue($parameters, $class::getFieldsWithType(BaseModel::SYSTEM))));
        if ($result == false) throw new Exception(self::$_conn->getLastError());
        self::insertLog("UPDATE", stdClassToArray(self::getByID($class::getSelfName(), $parameters["ID"], BaseModel::SYSTEM)));
    }
    static function update($parameters, $class,$mode = BaseModel::PUBLIC)
    {
        $parameters = filterParameterByClass($parameters, $class, $mode);
        self::updateRaw($parameters, $class);
    }
    static function delete($ID, $class){
        self::update(array("ID"=>$ID, "isDeleted"=>1),$class, BaseModel::SYSTEM);
    }

    static function insert($parameters, $class, $mode = BaseModel::PUBLIC){
        $parameters = filterParameterByClass($parameters, $class, $mode);
        return self::insertRaw($parameters, $class);
    }
    static function isWhereConditionExisted($class, $whereConditionList){
        return sizeof(DB::getByWhereCondition($class, $whereConditionList, BaseModel::SYSTEM)) > 0;
    }

    private static function insertRaw($parameters, $class){
        unset($parameters['ID']);
        $id = self::$_conn->insert($class::getSelfName(), convertParametersToString(addDefaultValue($parameters, $class::getFieldsWithType(BaseModel::SYSTEM))));
        $parameters['ID'] = $id;
        if ($id == false) throw new Exception(self::$_conn->getLastError());
        self::insertLog("INSERT", $parameters);
        return $id;
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
            self::$_conn->where($key, $value);
        }
    }
}

function addDefaultValue($parameters, $fieldTypeList){
    foreach($fieldTypeList as $field){
        if(!array_key_exists($field["key"],$parameters) || $parameters[$field["key"]] == null){
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

function fieldQueryForSelect($class, $mode = BaseModel::PUBLIC)
{
    $sql = "";
    foreach ($class::getFields($mode) as $value) {
        $sql .=  $class::getSelfName() . "." . $value . " as '" . $class::getSelfName() . "." . $value . "', ";
    }
    return substr_replace($sql, " ", -2);
}

function convertParametersToString($parameters)
{
    $result = array();
    foreach ($parameters as $key => $value) {
        if (is_array($value))
            $result[$key] = json_encode($value);
        else
            $result[$key] = $value;
    }
    return $result;
}

function rawDataListTModelList($rawDataList, $class, $mode)
{
    $modelList = array();
    foreach ($rawDataList as $data) {
        array_push($modelList,  new $class($data, $mode));
    }
    return $modelList;
}
