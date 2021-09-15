<?php
abstract class LoginUser extends BaseModel{
    public $password;
    public $loginName; 
    public $type; 

    static function getCurrentType(){
        try{
            if($GLOBALS['currentUser'] == null) return array();
            return $GLOBALS['currentUser']->type;
        }catch(Exception $e){
            throw $e;
        }
    }

    public static function getFields(){
        return array(
            array("key" => "loginName", "type"=> BaseTypeEnum::STRING),
            array("key" => "type", "type"=> BaseTypeEnum::ARRAY),
            array("key" => "password", "type"=> BaseTypeEnum::STRING),
        );
    }

    public function getName(){
        return $this->loginName;
    }
}