<?php
abstract class LoginUser extends BaseModel{
    public $password;
    public $loginName; 
    public $type; 

    public static function getDetailField(){
        return array(
            array("key" => "password", "type"=> BaseTypeEnum::STRING),
        );
    }
    public static function getPublicField(){
        return array(
            array("key" => "loginName", "type"=> BaseTypeEnum::STRING),
            array("key" => "type", "type"=> BaseTypeEnum::ARRAY),
        );
    }
    public static function getFields($mode = BaseModel::PUBLIC){
        return self::initGetFields(self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }
    public function __construct($object, $mode = BaseModel::PUBLIC){
        parent::__construct($object);
        $this->init($object, self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }
}
