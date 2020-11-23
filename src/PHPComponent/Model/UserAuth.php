<?php
class UserAuth extends BaseModel{
    public $name;
    public $role;

    public static function getPublicField(){
        return array(
            array("key" => "page", "type"=> BaseTypeEnum::STRING),
            array("key" => "createAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "readAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "updateAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "deleteAuth", "type"=> BaseTypeEnum::ARRAY),
        );
    }
    public static function getDetailField(){
        return array();
    }

    public static function getFields($mode = BaseModel::PUBLIC){
        return self::initGetFields(self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }
    public function __construct($object, $mode = BaseModel::PUBLIC){
        parent::__construct($object, $mode);
        $this->init($object, self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }

    public static function getFieldsWithType($mode = BaseModel::PUBLIC){
        return self::initGetFieldsWithType(self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }

}