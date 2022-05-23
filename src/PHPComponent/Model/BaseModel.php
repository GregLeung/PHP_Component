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
    public $createdUserID;
    public $modifiedUserID;

    public static function isExisted($id)
    {
        return (DB::getByID(static::class, $id) != null);
    }

    static function getSystemFields()
    {
        return array(
            array("key" => "createdDate", "type" => BaseTypeEnum::STRING),
            array("key" => "modifiedDate", "type" => BaseTypeEnum::STRING),
            array("key" => "isDeleted", "type" => BaseTypeEnum::Boolean),
            array("key" => "ID", "type" => BaseTypeEnum::NUMBER),
            array("key" => "createdUserID", "type" => BaseTypeEnum::NUMBER),
            array("key" => "modifiedUserID", "type" => BaseTypeEnum::NUMBER),
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
                        if(!isset($GLOBALS['cachedMap']))
                            $GLOBALS['cachedMap'] = array();
                        if(isset($GLOBALS['cachedMap']) && !isset($GLOBALS['cachedMap'][static::class . ".".$data["class"]])){
                            $GLOBALS['cachedMap'][static::class . ".".$data["class"]] = array();
                            foreach ($cachedList[$data["class"]] as $each) {
                                $each->customAssignField($cachedList, static::mergeOptions($key, array_search($data["class"], $options["joinClass"]),  $options));
                                if(is_array($each->{$data["field"]})){
                                    foreach($each->{$data["field"]} as $ID){
                                        if(!isset($GLOBALS['cachedMap'][static::class . ".".$data["class"]][$ID])){
                                            $GLOBALS['cachedMap'][static::class . ".".$data["class"]][$ID] = array();
                                        }
                                        array_push($GLOBALS['cachedMap'][static::class . ".".$data["class"]][$ID], $each);
                                    }
                                }else{
                                    if(!isset($GLOBALS['cachedMap'][static::class . ".".$data["class"]][$each->{$data["field"]}])){
                                        $GLOBALS['cachedMap'][static::class . ".".$data["class"]][$each->{$data["field"]}] = array();
                                    }
                                    array_push($GLOBALS['cachedMap'][static::class . ".".$data["class"]][$each->{$data["field"]}], $each);
                                }
                            }
                        }
                        $this->$key = (isset($GLOBALS['cachedMap'][static::class . ".".$data["class"]][$this->ID])) ? $GLOBALS['cachedMap'][static::class . ".".$data["class"]][$this->ID] : array();
                    }
                    break;
                case BaseTypeEnum::TO_SINGLE:
                    if (isset($cachedList[$data["class"]]) && isset($cachedList[$data["class"]][$this->{$data["field"]}]) && isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $each = $cachedList[$data["class"]][$this->{$data["field"]}];
                        $each->customAssignField($cachedList, static::mergeOptions($key, array_search($data["class"], $options["joinClass"]), $options,));
                        $this->$key = $each;
                    }
                    break;
                case BaseTypeEnum::ARRAY_OF_ID:
                    if (isset($cachedList[$data["class"]]) && isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $result = array();
                        foreach ($this->{$data["field"]} as $id) {
                            if (isset($cachedList[$data["class"]][$id])) {
                                $each = $cachedList[$data["class"]][$id];
                                $each->customAssignField($cachedList, static::mergeOptions($key, array_search($data["class"], $options["joinClass"]), $options));
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
                    if (isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])){
                        $field = find($data["class"]::getFields(), function($each, $key) use($data){
                            return $each["key"] == $data["field"];
                        });
                        if($field["type"] == BaseTypeEnum::INT_ARRAY)
                            $this->$key = filter(DB::getAll_new($data["class"], static::mergeOptions($key, array_search($data["class"], $options["joinClass"]), $options,)), function($each, $key) use($data){
                                return in_array($this->ID, $each->{$data["field"]});
                            });
                        else
                            $this->$key = DB::getByColumn($data["class"], $data["field"], $this->ID,  static::mergeOptions($key, array_search($data["class"], $options["joinClass"]), $options,));
                    }
                    break;
                case BaseTypeEnum::TO_SINGLE:
                    if (isset($options["joinClass"]) && in_array($data["class"], $options["joinClass"])) {
                        $this->$key = DB::getByID($data["class"], $this->{$data["field"]},  static::mergeOptions($key,  array_search($data["class"], $options["joinClass"]), $options));
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
                        $this->$key = array();
                        foreach ($this->{$data["field"]} as $id) {
                            array_push($this->$key, DB::getByID($data["class"], $id,  static::mergeOptions($key, array_search($data["class"], $options["joinClass"]), $options)));
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

    private static function mergeOptions($key, $joinKey,$options)
    {
        array_splice($options["joinClass"], $joinKey, 1);
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
    public function getDeepProp($props){
        return getDeepProp($this, $props);
    }
    public function updateChildren($parameters, $childrenClass, $childrenProps, $parentProps){
        // $this->update($parameters);
        foreach($parameters[$childrenProps] as &$child){
            if(isset($child["ID"]))
                DB::getByID($childrenClass, $child["ID"])->update($child);
            else
                $child["ID"] =  $childrenClass::insert(array_merge($child, array($parentProps => $this->ID)));
        }
        $this->$childrenProps = DB::getAll_new($childrenClass, ["whereOperation" => [
            [
                "type" => "EQUAL",
                "key" => $parentProps,
                "value" => $this->ID
            ]
        ]]);
        foreach($this->$childrenProps as $originalChild){
            $shouldDelete = true;
            foreach($parameters[$childrenProps] as $childProp){
                if(isset($childProp["ID"]) && $originalChild->ID == $childProp["ID"]){
                    $shouldDelete = false;
                    break;
                }
            }
            if($shouldDelete)
                $originalChild->delete();
        }
    }

    // public function update($parameters){}
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
