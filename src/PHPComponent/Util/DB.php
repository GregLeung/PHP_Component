<?php
abstract class DB{
    public static $_conn = null;
    static function getInstance($host, $username, $password, $db)
    {
        if (self::$_conn === null) self::$_conn = new MysqliDb(array('host' => $host, 'username' => $username, 'password' => $password, 'db' => $db, 'charset' => 'utf8mb4'));
    }
    static function rawQuery($sql)
    {
        return self::$_conn->rawQuery($sql);
    }
    static function getRaw($class, $cols = null)
    {
        self::$_conn->where($class::getSelfName() . "." .'isDeleted', 0);
        $result = self::$_conn->get($class::getSelfName(), null, $cols);
        if(method_exists($class, "permissionGetHandling"))
            $result = $class::permissionGetHandling($result);
        return $result;
    }
    static function getAll($class, $mode = BaseModel::PUBLIC, $options = null)
    {
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode, $options);
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
        return parseValue(self::getRaw($db::getSelfName(), $db::getSelfName() . ".* " .  $field_query));
    }

    static function getByID($class, $ID, $model = BaseModel::PUBLIC, $options = null)
    {
        self::$_conn->where("ID", $ID);
        $result = self::getRaw($class::getSelfName());
        return (sizeof($result) > 0) ? new $class($result[0], $model, $options) : null;
    }

    static function getByWhereCondition($class, $whereConditionList, $mode = BaseModel::PUBLIC, $options = null)
    {
        self::addWhereConditionList($whereConditionList);
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode, $options);
        return $modelList;
    }

    static function getByColumn($class, $column, $value, $mode = BaseModel::PUBLIC, $options = null)
    {
        $options = isset($options) ? $options : array();
        self::$_conn->where($column, $value);
        $modelList = rawDataListTModelList(self::getRaw($class), $class, $mode, $options);
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
        unset($parameters['isDeleted']);
        if(method_exists($class, "permissionUpdateHandling") && !$class::permissionUpdateHandling($parameters, self::getByID($class, $parameters["ID"], BaseModel::SYSTEM)))
            throw new Exception("Role Permission Denied");
        $parameters = (array) $parameters;
        self::$_conn->where("ID", $parameters["ID"]);
        $now = new DateTime();
        $parameters["modifiedDate"] = $now->format('Y-m-d H:i:s');
        $result = self::$_conn->update($class::getSelfName(), convertParametersToString($parameters));
        if ($result == false) throw new Exception(self::$_conn->getLastError());
    }
    static function update($parameters, $class)
    {
        $parameters = filterParameterByClass($parameters, $class);
        self::updateRaw($parameters, $class);
    }
    static function delete($ID, $class){
        self::update(array("ID"=>$ID, "isDeleted"=>1),$class, BaseModel::SYSTEM);
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
    static function isWhereConditionExisted($class, $whereConditionList){
        return sizeof(DB::getByWhereCondition($class, $whereConditionList, BaseModel::SYSTEM)) > 0;
    }

    private static function insertRaw($parameters, $class){
        unset($parameters['ID']);
        unset($parameters['createdDate']);
        unset($parameters['modifiedDate']);
        unset($parameters['isDeleted']);
        if(method_exists($class, "permissionInsertHandling") && !$class::permissionInsertHandling($parameters))
            throw new Exception("Role Permission Denied");
        $id = self::$_conn->insert($class::getSelfName(), convertParametersToString(addDefaultValue($parameters, $class::getFieldsWithType(BaseModel::SYSTEM))));
        if ($id == false) throw new Exception(self::$_conn->getLastError());
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
            if($value === null){
                self::$_conn->where($key, NULL, '<=>');
            }else{
                self::$_conn->where($key, $value);
            }
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
    $fields = $class::getFields($mode);
    foreach($class::getVirtualField() as $virtualField){
        $fields = filter($fields, function($data, $key)use($virtualField){
            return $data !== $virtualField["key"];
        });
    }
    foreach ($fields as $value) {
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

function rawDataListTModelList($rawDataList, $class, $mode, $options)
{
    $modelList = array();
    foreach ($rawDataList as $data) {
        array_push($modelList,  new $class($data, $mode, $options));
    }
    return $modelList;
}
