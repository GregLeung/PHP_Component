<?php
abstract class BaseModel
{
    const PUBLIC = 0; //To be deprecated
    const DETAIL = 1; //To be deprecated
    const SYSTEM = 2; //To be deprecated
    
    public $createdDate;
    public $modifiedDate;
    public $ID;
    public $isDeleted;

    public static function isExisted($id){
        return (DB::getByID(static::class, $id) != null);
    }

    static function getSystemFields(){
        return array(
            array("key" => "createdDate", "type"=> BaseTypeEnum::STRING),
            array("key" => "modifiedDate", "type"=> BaseTypeEnum::STRING),
            array("key" => "isDeleted", "type"=> BaseTypeEnum::STRING),
            array("key" => "ID", "type"=> BaseTypeEnum::NUMBER)
        );
    }

    static function getFields(){
        return array();
    }

    static function getRealFields(){
        return filter(static::getFields(), function($data, $key){
            return $data["type"] !== BaseTypeEnum::TO_MULTI && $data["type"] !== BaseTypeEnum::TO_SINGLE && $data["type"] !== BaseTypeEnum::ARRAY_OF_ID;
        });
    }

    static function getFieldsWithType(){
        $result = array(array("key" => "ID", "type"=> BaseTypeEnum::NUMBER),);
        $result = array_merge(array_map(function ($data) { return $data; }, static::getSystemFields()), $result);
        $result = array_merge(array_map(function ($data) { return $data; }, static::getFields()), $result);
        return $result;
    }

    public function __construct($object,  $options = array()){
        $this->ID = $object["ID"];
        $this->assignField($object, self::getSystemFields(), $options);
        $this->assignField($object, static::getFields(), $options);
    }

    public function assignVirtualField($cachedList){
        foreach(static::getFields() as $data) {
            $key = $data['key'];
            switch($data['type']){
                case BaseTypeEnum::TO_MULTI:
                    $result = array();
                    if(isset($cachedList[$data["class"]])){
                        foreach($cachedList[$data["class"]] as $each){
                            if($this->{$data["field"]} === $each->ID){
                                $each->assignVirtualField($cachedList);
                                array_push($result, $each);
                            }
                        }
                        $this->$key = $result;
                    }
                    break;
                case BaseTypeEnum::TO_SINGLE:
                    if(isset($cachedList[$data["class"]])){
                        $result = null;
                        foreach($cachedList[$data["class"]] as $each){
                            if($this->{$data["field"]} === $each->ID){
                                $each->assignVirtualField($cachedList);
                                $result = $each;
                                break;
                            }
                        }
                        $this->$key = $result;
                    }
                    break;
                case BaseTypeEnum::ARRAY_OF_ID:
                    if(isset($cachedList[$data["class"]])){
                        $result = array();
                        foreach($this->{$data["field"]} as $id){
                            foreach($cachedList[$data["class"]] as $each){
                                if($id == $each->ID){
                                    $each->assignVirtualField($cachedList);
                                    array_push($result, $each);
                                }
                            }
                        }
                        $this->$key = $result;
                    }
                    break;
            }
        }
    }

    protected function assignField($object, $fieldArray, $options = array()){
        foreach($fieldArray as $data) {
            $key = $data['key'];
            switch($data['type']){
                case BaseTypeEnum::STRING:
                    $this->$key = $object[$data['key']];
                break;
                case BaseTypeEnum::NUMBER:
                    if(($object[$data['key']] === null)) $this->$key = null;
                    else $this->$key = ($object[$data['key']] == (int) $object[$data['key']]) ? (int) $object[$data['key']] : (float) $object[$data['key']];
                break;
                case BaseTypeEnum::ARRAY:
                    $this->$key = json_decode($object[$data['key']]);
                break;
                case BaseTypeEnum::OBJECT:
                    $this->$key = (isJSONString($object[$data['key']]))?json_decode($object[$data['key']]):array();
                break;
                case BaseTypeEnum::TO_MULTI:
                    if(isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])){
                        $options["joinClass"] = filter($options["joinClass"], function($value) use($data){
                            return $value !== $data["class"];
                        });
                        $this->$key = DB::getByColumn($data["class"], $data["field"], $this->ID,  $options);
                    }
                break;
                case BaseTypeEnum::TO_SINGLE:
                    if(isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"]))
                        $this->$key = DB::getByID($data["class"], $this->{$data["field"]},  $options);
                break;
                case BaseTypeEnum::Boolean:
                    $this->$key = ($object[$data['key']] === 1) ? true : false ;
                break;
                case BaseTypeEnum::INT_ARRAY:
                    $this->$key = filter(json_decode($object[$data['key']]), function($data, $key){
                        return intval($data);
                    });
                break;
                case BaseTypeEnum::ARRAY_OF_ID:
                    if(isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])){
                        $options["joinClass"] = filter($options["joinClass"], function($value) use($data){
                            return $value !== $data["class"];
                        });
                        $this->$key = array();
                        foreach($this->{$data["field"]} as $id){
                            array_push($this->$key, DB::getByID($data["class"], $id,  $options));
                        }
                    }
                break;
            }
        }
    }

    public static function getSelfName(){
        return static::class;
    }
    public function getClassName(){
        return static::class;
    }
}

class BaseTypeEnum{
    const STRING = 0;
    const ARRAY = 1;
    const OBJECT = 2;
    const TO_MULTI = 3;
    const TO_SINGLE = 4;
    const NUMBER = 5;
    const Boolean = 6;
    const INT_ARRAY = 7;
    const ARRAY_OF_ID = 8;
}
