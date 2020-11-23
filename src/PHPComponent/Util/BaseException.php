<?php
class BaseException extends Exception{
    public $message;
    public $type;
    public $code;

    public function __construct($message = "Unknown Error Message", $code = -1, $type="Failed"){
        $this->message = $message;
        $this->code = $code;
        $this->type = $type;
        parent::__construct($message, $code, null);
    }
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}