<?php
class Token extends BaseModel{
    public $userID;
    public $expiredDate;
    public static function getFields($mode = BaseModel::PUBLIC){
        $result = array();
        array_push($result, "ID");
        array_push($result,'userID');
        array_push($result,'expiredDate');
        array_push($result,'createdDate');
        array_push($result,'modifiedDate');
        return $result;
    }
    
    
    public function __construct($object){
        parent::__construct($object);
        $this->userID = $object["userID"];
        $this->expiredDate = $object["expiredDate"];
        $this->createdDate = $object["createdDate"];
        $this->modifiedDate = $object["modifiedDate"];
    }
}
