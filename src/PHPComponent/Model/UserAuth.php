<?php
class UserAuth extends BaseModel{
    public $name;
    public $role;

    public static function getFields(){
        return array(
            array("key" => "page", "type"=> BaseTypeEnum::STRING),
            array("key" => "createAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "readAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "updateAuth", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "deleteAuth", "type"=> BaseTypeEnum::ARRAY),
        );
    }
}