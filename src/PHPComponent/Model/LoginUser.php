<?php
abstract class LoginUser extends BaseModel{
    public $password;
    public $loginName; 
    public $username; 
    public $type; 
    public static $tokenClass = Token::class; 
    public static $tokenHeader = "Token"; 

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
            array("key" => "username", "type"=> BaseTypeEnum::STRING),
        );
    }

    static function getLoginFieldList()
    {
        return ["loginName"];
    }

    public function getName(){
        return $this->loginName;
    }
}