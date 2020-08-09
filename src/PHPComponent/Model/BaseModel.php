<?php
abstract class BaseModel
{
    const PUBLIC = 0;
    const DETAIL = 1;
    const SYSTEM = 2;
    public $createdDate;
    public $modifiedDate;
    public $ID;

    abstract static function getFields($mode = BaseModel::PUBLIC);
    static function insertPublicCheck(){}
    static function insertDetailCheck(){}
    static function insertSystemCheck(){}
    static function updatePublicCheck(){}
    static function updateDetailCheck(){}
    static function updateSystemCheck(){}
    static function deleteCheck(){}

    public function __construct($object){
        $this->ID = $object["ID"];
    }
    public static function getSelfName(){
        return static::class;
    }
    public function getClassName(){
        return static::class;
    }
}
