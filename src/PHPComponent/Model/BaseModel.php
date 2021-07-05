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

    public static function isExisted($id)
    {
        return (DB::getByID(static::class, $id) != null);
    }

    static function getSystemFields()
    {
        return array(
            array("key" => "createdDate", "type" => BaseTypeEnum::STRING),
            array("key" => "modifiedDate", "type" => BaseTypeEnum::STRING),
            array("key" => "isDeleted", "type" => BaseTypeEnum::STRING),
            array("key" => "ID", "type" => BaseTypeEnum::NUMBER)
        );
    }

    static function getFields()
    {
        return array();
    }

    static function getRealFields()
    {
        return filter(static::getFields(), function ($data, $key) {
            return $data["type"] !== BaseTypeEnum::TO_MULTI && $data["type"] !== BaseTypeEnum::TO_SINGLE && $data["type"] !== BaseTypeEnum::ARRAY_OF_ID && $data["type"] !== BaseTypeEnum::COMPUTED;
        });
    }

    static function getFieldsWithType()
    {
        $result = array(array("key" => "ID", "type" => BaseTypeEnum::NUMBER),);
        $result = array_merge(array_map(function ($data) {
            return $data;
        }, static::getSystemFields()), $result);
        $result = array_merge(array_map(function ($data) {
            return $data;
        }, static::getFields()), $result);
        return $result;
    }

    public function __construct($object,  $options = array())
    {
        $this->ID = $object["ID"];
        $this->assignField($object, self::getSystemFields(), $options);
        $this->assignField($object, static::getFields(), $options);
    }

    public function customAssignField($cachedList, $options = array())
    {
        foreach (static::getFields() as $data) {
            $key = $data['key'];
            if(static::isMasked($key, $options)){
                $this->$key = null;
                continue;
            };
            switch ($data['type']) {
                case BaseTypeEnum::TO_MULTI:
                    $result = array();
                    if (isset($cachedList[$data["class"]]) && isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        foreach($cachedList[$data["class"]] as $each){
                            if(isset($each->{$data["field"]}) && $this->ID === $each->{$data["field"]}){
                                $each->customAssignField($cachedList, static::mergeOptions($key, $options));
                                array_push($result, $each);
                            }
                        }
                        $this->$key = $result;
                    }
                    break;
                case BaseTypeEnum::TO_SINGLE:
                    if (isset($cachedList[$data["class"]]) && isset($cachedList[$data["class"]][$this->{$data["field"]}]) && isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $each = $cachedList[$data["class"]][$this->{$data["field"]}];
                        $each->customAssignField($cachedList, static::mergeOptions($key, $options));
                        $this->$key = $each;
                    }
                    break;
                case BaseTypeEnum::ARRAY_OF_ID:
                    if (isset($cachedList[$data["class"]]) && isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $result = array();
                        foreach ($this->{$data["field"]} as $id) {
                            if (isset($cachedList[$data["class"]][$id])) {
                                $each = $cachedList[$data["class"]][$id];
                                $each->customAssignField($cachedList, static::mergeOptions($key, $options));
                                array_push($result, $each);
                            }
                        }
                        $this->$key = $result;
                    }
                    break;
                case BaseTypeEnum::COMPUTED:
                    if (isset($data["computed"]) && isset($options["computed"]) && in_array($key, $options["computed"])) {
                        $this->$key = $data["computed"]($this);
                    }
                    break;
            }
        }
    }

    protected function assignField($object, $fieldArray, $options = array())
    {
        foreach ($fieldArray as $data) {
            $key = $data['key'];
            if(static::isMasked($key, $options)) continue;
            switch ($data['type']) {
                case BaseTypeEnum::STRING:
                    if (($object[$data['key']] == null))
                        $this->$key = null;
                    else
                        $this->$key = strval($object[$data['key']]);
                    break;
                case BaseTypeEnum::NUMBER:
                    if (($object[$data['key']] === null)) $this->$key = null;
                    else $this->$key = ($object[$data['key']] == (int) $object[$data['key']]) ? (int) $object[$data['key']] : (float) $object[$data['key']];
                    break;
                case BaseTypeEnum::ARRAY:
                    $this->$key = json_decode($object[$data['key']]);
                    break;
                case BaseTypeEnum::OBJECT:
                    $this->$key = (isJSONString($object[$data['key']])) ? json_decode($object[$data['key']]) : array();
                    break;
                case BaseTypeEnum::TO_MULTI:
                    if (isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $this->$key = DB::getByColumn($data["class"], $data["field"], $this->ID,  static::mergeOptions($key, $options));
                    }
                    break;
                case BaseTypeEnum::TO_SINGLE:
                    if (isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $this->$key = DB::getByID($data["class"], $this->{$data["field"]},  static::mergeOptions($key, $options));
                    }
                    break;
                case BaseTypeEnum::Boolean:
                    $this->$key = ($object[$data['key']] === 1) ? true : false;
                    break;
                case BaseTypeEnum::INT_ARRAY:
                    $this->$key = filter(json_decode($object[$data['key']]), function ($data, $key) {
                        return intval($data);
                    });
                    break;
                case BaseTypeEnum::ARRAY_OF_ID:
                    if (isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $joinKey = array_search($data["class"], $options["joinClass"]);
                        if (false !== $joinKey) {
                            array_splice($options["joinClass"], $joinKey, 1);
                        }
                        $this->$key = array();
                        
                        foreach ($this->{$data["field"]} as $id) {
                            array_push($this->$key, DB::getByID($data["class"], $id,  static::mergeOptions($key, $options)));
                        }
                    }
                    break;
                case BaseTypeEnum::COMPUTED:
                    if (isset($data["computed"]) && isset($options["computed"]) && in_array($key, $options["computed"])) {
                        $this->$key = $data["computed"]($this);
                    }
                    break;
            }
        }
    }

    public static function getSelfName()
    {
        return static::class;
    }
    public function getClassName()
    {
        return static::class;
    }

    private static function mergeOptions($key, $options)
    {
        if (!isset($options["passedProperty"])) $options["passedProperty"] = $key;
        else $options["passedProperty"] .= "." . $key;
        return $options;
    }
    private static function isMasked($key, $options)
    {
        $isMasked = false;
        if (isset($options["mask"])) {
            foreach ($options["mask"] as $mask) {
                if ( (!isset($options["passedProperty"]) && $mask == $key )|| (isset($options["passedProperty"]) && $options["passedProperty"] . "." . $key == $mask)) {
                    $isMasked = true;
                    break;
                }
            }
        }
        return $isMasked;
    }
}

class BaseTypeEnum
{
    const STRING = 0;
    const ARRAY = 1;
    const OBJECT = 2;
    const TO_MULTI = 3;
    const TO_SINGLE = 4;
    const NUMBER = 5;
    const Boolean = 6;
    const INT_ARRAY = 7;
    const ARRAY_OF_ID = 8;
    const COMPUTED = 9;
}
