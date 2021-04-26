<?php
class Token extends BaseModel{
    public $userID;
    public $expiredDate;
    public $token;

    public static function getFields(){
        return array(
            array("key" => "userID", "type"=> BaseTypeEnum::STRING),
            array("key" => "token", "type"=> BaseTypeEnum::STRING),
            array("key" => "expiredDate", "type"=> BaseTypeEnum::STRING),
        );
    }
}
