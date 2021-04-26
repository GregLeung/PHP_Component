<?php
class Log extends BaseModel{
    public $header;
    public $server;
    public $parameter;
    public $user;
    public $action;
    public $data;

    public static function getFields(){
        return array(
            array("key" => "header", "type"=> BaseTypeEnum::STRING),
            array("key" => "server", "type"=> BaseTypeEnum::STRING),
            array("key" => "parameter", "type"=> BaseTypeEnum::STRING),
            array("key" => "user", "type"=> BaseTypeEnum::STRING),
            array("key" => "action", "type"=> BaseTypeEnum::STRING),
            array("key" => "data", "type"=> BaseTypeEnum::STRING),
        );
    }
}
