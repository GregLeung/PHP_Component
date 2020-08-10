<?php
abstract class LoginUser extends BaseModel{
    public $password;
    public $loginName; 
    public $type; 
    public function __construct($object){
        parent::__construct($object);
        $this->password = $object["password"];
        $this->loginName = $object["loginName"];
        $this->type = json_decode($object["type"]);
    }
}
