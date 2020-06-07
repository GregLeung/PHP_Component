<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    class BaseEmail{
        public $mail;
        public function __construct(){
            $this->mail = new PHPMailer(true);
            $this->mail->SMTPDebug = 0;
            $this->mail->isSMTP();    
            $this->mail->CharSet = 'UTF-8';
            $this->mail->isHTML(true);
        }
    }