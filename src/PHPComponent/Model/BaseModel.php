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

    static function getPublicField(){
        return array();
    }
    
    static function getDetailField(){
        return array();
    }

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

    public function __construct($object, $mode = BaseModel::PUBLIC, $options = array()){
        $this->ID = $object["ID"];
        if($mode === BaseModel::SYSTEM){
            $this->assignField($object, self::getSystemField(), $options);
        }
    }

    protected function assignField($object, $fieldArray, $options = array()){
        foreach($fieldArray as $data) {
            $key = $data['key'];
            switch($data['type']){
                case BaseTypeEnum::STRING:
                    $this->$key = $object[$data['key']];
                break;
                case BaseTypeEnum::NUMBER:
                    if(($object[$data['key']] == null)) $this->$key = null;
                    else $this->$key = ($object[$data['key']] == (int) $object[$data['key']]) ? (int) $object[$data['key']] : (float) $object[$data['key']];
                break;
                case BaseTypeEnum::ARRAY:
                    $this->$key = json_decode($object[$data['key']]);
                break;
                case BaseTypeEnum::OBJECT:
                    $this->$key = (isJSONString($object[$data['key']]))?json_decode($object[$data['key']]):array();
                break;
                case BaseTypeEnum::TO_MULTI:
                    if(isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])){
                        $options["joinClass"] = filter($options["joinClass"], function($value) use($data){
                            return $value !== $data["class"];
                        });
                        $this->$key = DB::getByColumn($data["class"], $data["field"], $this->ID, BaseModel::SYSTEM, $options);
                    }
                break;
                case BaseTypeEnum::TO_SINGLE:
                    if(isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"]))
                        $this->$key = DB::getByID($data["class"], $this->{$data["field"]}, BaseModel::SYSTEM, $options);
                break;
                case BaseTypeEnum::Boolean:
                    $this->$key = ($object[$data['key']] === 1) ? true : false ;
                break;
            }
        }
    }

    protected function init($object, $publicField, $detailField, $systemField, $mode = BaseModel::PUBLIC, $options = array()){
        switch ($mode) {
            case BaseModel::SYSTEM:
                $this->assignField($object, $systemField, $options); 
            case BaseModel::DETAIL:
                $this->assignField($object, $detailField, $options);
            case BaseModel::PUBLIC:
                $this->assignField($object, $publicField, $options);
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

    public static function getRealFields(){
        return map(filter(array_merge(static::getPublicField(), static::getDetailField(), static::getSystemField()), function($data, $key){
            return !($data["type"] === BaseTypeEnum::TO_MULTI || $data["type"] === BaseTypeEnum::TO_SINGLE);
        }), function($data, $key){
            return $data["key"];
        });
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
    const TO_MULTI = 3;
    const TO_SINGLE = 4;
    const NUMBER = 5;
    const Boolean = 6;
}
