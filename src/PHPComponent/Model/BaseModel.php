<?php
class BaseModel{
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
  public static function reportFilter($data, $parameter){
    if(!array_key_exists('status', $parameter) || $parameter["status"] == null) return $data; 
    else
        return array_filter($data, function($v, $k) use($parameter) {
            return $v->status == $parameter["status"];
        }, ARRAY_FILTER_USE_BOTH);
    }
}
?>