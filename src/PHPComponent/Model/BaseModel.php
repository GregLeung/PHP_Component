<?php
abstract class BaseModel
{
    const PUBLIC = 0;
    const DETAIL = 1;
    const SYSTEM = 2;
    public $createdDate;
    public $modifiedDate;
    public $ID;
    public $isDeleted;

    public static function isExisted($id){
        return (DB::getByID(static::class, $id) != null);
    }

    static function getSystemField(){
        return array(
            array("key" => "createdDate", "type"=> BaseTypeEnum::STRING),
            array("key" => "modifiedDate", "type"=> BaseTypeEnum::STRING),
            array("key" => "isDeleted", "type"=> BaseTypeEnum::STRING),
            array("key" => "ID", "type"=> BaseTypeEnum::STRING)
        );
    }

    static function getPublicCheck(){}
    static function getPublicField(){}
    static function getDetailCheck(){}
    static function getDetailField(){}

    public function filterField($fieldList){
        $result = array();
        foreach (stdClassToArray($this) as $classKey => $classValue) {
            foreach($fieldList as $key => $value){
                if($classKey == $value){
                    $result[$classKey] = $classValue;
                    break;
                }
            }
        }
        return $result;
    }

    public function __construct($object, $mode = BaseModel::PUBLIC){
        $this->ID = $object["ID"];
        if($mode == BaseModel::SYSTEM){
            $this->assignField($object, self::getSystemField());
        }
    }

    protected function assignField($object, $fieldArray){
        foreach($fieldArray as $data) {
            $key = $data['key'];
            switch($data['type']){
                case BaseTypeEnum::STRING:
                    $this->$key = $object[$data['key']];
                break;
                case BaseTypeEnum::ARRAY:
                    $this->$key = json_decode($object[$data['key']]);
                break;
                case BaseTypeEnum::OBJECT:
                    $this->$key = (isJSONString($object[$data['key']]))?json_decode($object[$data['key']]):array();
                break;
            }
        }
    }

    protected function init($object, $publicField, $detailField, $systemField, $mode = BaseModel::PUBLIC){
        switch ($mode) {
            case BaseModel::SYSTEM:
                $this->assignField($object, $systemField); 
            case BaseModel::DETAIL:
                $this->assignField($object, $detailField);
            case BaseModel::PUBLIC:
                $this->assignField($object, $publicField);
        }
    }

    protected static function initGetFields($publicField, $detailField, $systemField, $mode = BaseModel::PUBLIC){
        $result = array("ID");
        switch ($mode) {
            case BaseModel::SYSTEM:
                $result = array_merge(array_map(function ($data) { return $data['key']; }, $systemField), $result);
            case BaseModel::DETAIL:
                $result = array_merge(array_map(function ($data) { return $data['key']; }, $detailField), $result);
            case BaseModel::PUBLIC:
                $result = array_merge(array_map(function ($data) { return $data['key']; }, $publicField), $result);
        }
        return $result;
    }

    protected static function initGetFieldsWithType($publicField, $detailField, $systemField, $mode = BaseModel::PUBLIC){
        $result = array(array("key" => "ID", "type"=> BaseTypeEnum::STRING),);
        switch ($mode) {
            case BaseModel::SYSTEM:
                $result = array_merge(array_map(function ($data) { return $data; }, $systemField), $result);
            case BaseModel::DETAIL:
                $result = array_merge(array_map(function ($data) { return $data; }, $detailField), $result);
            case BaseModel::PUBLIC:
                $result = array_merge(array_map(function ($data) { return $data; }, $publicField), $result);
        }
        return $result;
    }

    public static function getSelfName(){
        return static::class;
    }
    public function getClassName(){
        return static::class;
    }
}

class BaseTypeEnum{
    const STRING = 0;
    const ARRAY = 1;
    const OBJECT = 2;
}
