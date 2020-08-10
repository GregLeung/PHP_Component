<?php
class Log extends BaseModel{
    public $header;
    public $server;
    public $parameter;
    public $user;
    public $action;
    public $from;
    public $data;

    public static function getFields($mode = BaseModel::PUBLIC){}
    public function __construct($object){
        parent::__construct($object);
        $this->header = $object["header"];
        $this->server = $object["server"];
        $this->parameter = $object["parameter"];
        $this->user = $object["user"];
        $this->action = $object["action"];
        $this->from = $object["from"];
        $this->data = $object["data"];
        $this->createdDate = $object["createdDate"];
    }
}
