<?php
class Token extends BaseModel{
    public $userID;
    public $expiredDate;

    public static function getDetailField(){
        return array(
            array("key" => "userID", "type"=> BaseTypeEnum::STRING),
            array("key" => "expiredDate", "type"=> BaseTypeEnum::STRING),
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
