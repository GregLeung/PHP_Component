<?php
class Log extends BaseModel{
    public $header;
    public $server;
    public $parameter;
    public $user;
    public $action;
    public $from;
    public $data;

    public static function getDetailField(){
        return array(
            array("key" => "header", "type"=> BaseTypeEnum::STRING),
            array("key" => "server", "type"=> BaseTypeEnum::STRING),
            array("key" => "parameter", "type"=> BaseTypeEnum::STRING),
            array("key" => "user", "type"=> BaseTypeEnum::STRING),
            array("key" => "action", "type"=> BaseTypeEnum::STRING),
            array("key" => "from", "type"=> BaseTypeEnum::STRING),
            array("key" => "data", "type"=> BaseTypeEnum::STRING),
        );
    }
    public static function getPublicField(){
        return array();
    }
    public static function getFields($mode = BaseModel::PUBLIC){
        return self::initGetFields(self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }
    public function __construct($object, $mode = BaseModel::PUBLIC){
        parent::__construct($object);
        $this->init($object, self::getPublicField(), self::getDetailField(), self::getSystemField(), $mode);
    }
}
