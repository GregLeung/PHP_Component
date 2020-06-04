<?php
class Response{
    public $code;
    public $message;
    public $data;

    public function __construct($code, $message, $data){
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
        return $this;
    }
    function send_response(){
        return json_encode($this, JSON_UNESCAPED_UNICODE);
    }

}
?>  