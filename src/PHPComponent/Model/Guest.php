<?php
require_once "BaseModel.php";
class Guest extends BaseModel{
    public $ID;
    public $name;
    public $chineseName;
    public $email;
    public $isInvited;
    public $isConfirmed;
    public $isResponded;
    public $isSentInvitationCMAB;
    public $isSentConfirmationCMAB;
    public $willAttend;
    public $title;
    public $position;
    public $organization;
    public $QRCode;
    public $isAttended;
    public $attendTime;

    public function __construct($object){
        $this->ID = $object["ID"];
        $this->name = $object["name"];
        $this->chineseName = $object["chineseName"];
        $this->email = $object["email"];
        $this->isInvited = $object["isInvited"];
        $this->isConfirmed = $object["isConfirmed"];
        $this->isResponded = $object["isResponded"];
        $this->isSentInvitationCMAB = $object["isSentInvitationCMAB"];
        $this->isSentConfirmationCMAB = $object["isSentConfirmationCMAB"];
        $this->willAttend = $object["willAttend"];
        $this->title = $object["title"];
        $this->position = $object["position"];
        $this->organization = $object["organization"];
        $this->QRCode = $object["QRCode"];
        $this->isAttended = $object["isAttended"];
        $this->attendTime = $object["attendTime"];
    }

}
