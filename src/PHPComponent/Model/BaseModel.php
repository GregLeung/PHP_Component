<?php
class BaseModel{
    public $createdDate;
    public $modifiedDate;
    public $ID;
    public function __construct($object){
        $this->ID = $object["ID"];
        $this->createdDate = $object["createdDate"];
        $this->modifiedDate = $object["modifiedDate"];
    }
    public static function getSelfName(){
       return static::class;
    }
    public function getClassName(){
        return static::class;
     }
   public static function getFields(){
      $result = array();
      $fields =  get_class_vars(self::getSelfName());
      foreach($fields as $key => $value){
          array_push($result, $key);
      }
      return $result;
  }
}
?>