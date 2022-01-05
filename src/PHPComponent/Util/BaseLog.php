<?php
class BaseLog extends BaseModel{
    public $field;
    public $oldValue;
    public $newValue;
    public $sourceID;
    public $fieldLabel;
    public $oldValueLabel;
    public $newValueLabel;
    public $sourceLabel;
    public $action;

    public static function getFields(){
        return array(
            array("key" => "field", "type"=> BaseTypeEnum::STRING),
            array("key" => "oldValue", "type"=> BaseTypeEnum::STRING),
            array("key" => "newValue", "type"=> BaseTypeEnum::STRING),
            array("key" => "sourceID", "type"=> BaseTypeEnum::NUMBER),
            array("key" => "fieldLabel", "type"=> BaseTypeEnum::STRING),
            array("key" => "oldValueLabel", "type"=> BaseTypeEnum::STRING),
            array("key" => "newValueLabel", "type"=> BaseTypeEnum::STRING),
            array("key" => "updatedFromLabel", "type"=> BaseTypeEnum::STRING),
            array("key" => "action", "type"=> BaseTypeEnum::STRING),
        );
    }

    public static function insert($oldValue, $newValue, $field, $sourceID, $oldValueLabel, $newValueLabel, $fieldLabel,$updatedFromLabel){
        DB::insert(array("oldValue"=> $oldValue, "newValue"=>$newValue, "field" => $field, "sourceID" =>$sourceID, "fieldLabel" => $fieldLabel, "oldValueLabel" => $oldValueLabel, "newValueLabel" => $newValueLabel, "updatedFromLabel" => $updatedFromLabel, "action" => getParameter($_POST, $_GET)["ACTION"]) ,static::class);
    }

    public static function insertLog($instance, $parameters){
        $systemFields = static::getSystemFields();
        $realFields = $instance::getRealFields();
        $fields = $instance::getFields();
        foreach($parameters as $key => $value) {
            // if(property_exists($instance, $key) && $instance->{$key} != $value && !static::isSystemField($key, $systemFields) && static::isRealField($key, $realFields)){
                if(property_exists($instance, $key) && !static::checkIsValueSame($instance->{$key}, $value) && !static::isSystemField($key, $systemFields) && static::isRealField($key, $realFields)){
                $logOption = null;
                $classField = find($fields, function($data, $index) use($key){
                    return $data["key"] == $key;
                });
                if($classField != null && isset($classField["logOption"]))
                    $logOption = $classField["logOption"];
                static::insert($instance->{$key}, $value, $key, $instance->ID, static::getValueLabel($logOption, $instance->{$key}), static::getValueLabel($logOption, $value) ,static::getFieldLabel($logOption, $key), $GLOBALS['currentUser'] == null ? "System": $GLOBALS['currentUser']->getName());
            }
        }
    }

    private static function checkIsValueSame($oldValue, $value){
        if(is_array($oldValue))
            return json_encode($oldValue) == json_encode($value);
        return $oldValue == $value;
    }

    private static function getFieldLabel($logOption, $defaultValue){
        if($logOption != null && isset($logOption["label"])){
            return $logOption["label"];
        }
        return $defaultValue;
    }

    private static function getValueLabel($logOption, $value){
        if($logOption != null && isset($logOption["valueCallback"])){
            return $logOption["valueCallback"]($value);
        }
        return $value;
    }

    private static function isSystemField($field, $systemFields){
        return find($systemFields, function($data, $key) use($field){
            return $data["key"] == $field;
        }) != null;
    }

    private static function isRealField($field, $realFields){
        return find($realFields, function($data, $key) use($field){
            return $data["key"] == $field;
        }) != null;
    }


}
