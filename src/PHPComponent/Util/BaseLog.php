<?php
class BaseLog extends BaseModel{
    public $field;
    public $oldValue;
    public $newValue;
    public $sourceID;
    // public $staff;

    public static function getFields(){
        return array(
            array("key" => "field", "type"=> BaseTypeEnum::STRING),
            array("key" => "oldValue", "type"=> BaseTypeEnum::STRING),
            array("key" => "newValue", "type"=> BaseTypeEnum::STRING),
            array("key" => "sourceID", "type"=> BaseTypeEnum::NUMBER),
            // array("key" => "staff", "type"=> BaseTypeEnum::TO_SINGLE, "class" => Staff::class, "field" => "createdUserID"),
        );
    }

    public static function insert($oldValue, $newValue, $field, $sourceID){
        DB::insert(array("oldValue"=> $oldValue, "newValue"=>$newValue, "field" => $field, "sourceID" =>$sourceID) ,static::class);
    }

    public static function insertLog($instance, $parameters){
        $systemFields = static::getSystemFields();
        foreach($parameters as $key => $value) {
            if(property_exists($instance, $key) && $instance->{$key} != $value && !static::isSystemField($key, $systemFields)){
                static::insert($instance->{$key}, $value, $key, $instance->ID);
            }
        }
    }

    private static function isSystemField($field, $systemFields){
        return find($systemFields, function($data, $key) use($field){
            return $data["key"] == $field;
        }) != null;
    }


}
