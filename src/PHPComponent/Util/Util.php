<?php
initLog();
function initLog()
{
    if (defined("SITE_ROOT")) {
        date_default_timezone_set("Asia/Hong_Kong");
        umask(0);
        if (!file_exists(SITE_ROOT . "/log"))
            mkdir(SITE_ROOT . "/log", 0777);
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error != null)
                writeLog($error["file"], $error["message"], $error["line"]);
        });
        set_error_handler(function ($severity, $message, $filename, $lineno) {
            writeLog($filename, $message, $lineno);
            if (error_reporting() == 0) {
                return;
            }
            if (error_reporting() & $severity) {
                throw new ErrorException($message, 0, $severity, $filename, $lineno);
            }
        });
    }
}
function init()
{
    if (!function_exists('getallheaders')) {
        function getallheaders()
        {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
    }
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Max-Age: 1728000');
    if (($_SERVER['REQUEST_METHOD'] == 'OPTIONS')) {
        $response =  new Response(200, "Success", "");
        echo $response->send_response();
        die();
    }
}

function writeLog($filename, $message, $line)
{
    $fileLocation = SITE_ROOT . "/log/log_" . date("Y-m-d") . ".txt";
    if (file_exists($fileLocation))
        $file = fopen($fileLocation, "a");
    else
        $file = fopen($fileLocation, "w");
    fwrite($file, json_encode(array("date" => date("Y-m-d H:i:s"), "message" => $message, "file" => $filename, "line" => $line),) . PHP_EOL);
    fclose($file);
}

function writeCustomLog($value)
{
    $fileLocation = SITE_ROOT . "/log/log_" . date("Y-m-d") . ".txt";
    if (file_exists($fileLocation))
        $file = fopen($fileLocation, "a");
    else
        $file = fopen($fileLocation, "w");
    fwrite($file, $value . PHP_EOL);
    fclose($file);
}

function readConfig()
{
    return json_decode(file_get_contents(SITE_ROOT . "/config.json"));
}
function readSystemConfig()
{
    return json_decode(file_get_contents(SITE_ROOT . "/system_config.json"));
}
function getFile($filePath)
{
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-Disposition: attachment; filename=" . $filePath);
    readfile($filePath);
}

function getRequestToken($userClass)
{
    try {
        if (array_key_exists($userClass::$tokenHeader, getallheaders())) return getallheaders()[$userClass::$tokenHeader];
        else return "";
    } catch (Error $exception) {
        writeCustomLog(json_encode($exception));
        return "";
    }
}


function stdClassToArray($classObj)
{
    if (is_array($classObj) && sizeof($classObj) > 0 && is_object(current($classObj))) {
        $result = array();
        foreach ($classObj as $data) {
            array_push($result, parseStdClass($data));
        }
        return $result;
    } else {
        return parseStdClass($classObj);
    }
}

function parseStdClass($object)
{
    if (is_object($object))
        return json_decode(json_encode($object), true);
    else
        return $object;
}



function logOutRemoveToken($userClass, $token)
{
    DB::deleteRealByWhereCondition($userClass::$tokenClass, array('token' => $token));
}

function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generateRandomNumber($length = 6)
{
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function apiKeyChecking()
{
    try{
        if (!isset(getallheaders()['Apikey']) || getallheaders()['Apikey'] != readConfig()->{"Apikey"})
            if (!isset(getallheaders()['apikey']) || getallheaders()['apikey'] != readConfig()->{"Apikey"})
                throw new Exception('api key checking failed');
    }catch(Exception $e){
        throw $e;
    }
}

function setAllowOrigin($origins = array())
{
    $data = "";
    foreach ($origins as $origin) {
        $data .= $origin . ",";
    }
    if (strlen($data) > 0) $data = substr($data, 0, -1);
    header("Access-Control-Allow-Origin: " . $data);
}

function contain($sentence, $value)
{
    try {
        return (strpos(strtolower(strval($sentence)), strtolower($value)) !== false);
    } catch (Exception $e) {
        return false;
    }
}
function isExistedNotNull($object, $key)
{
    return (array_key_exists($key, $object) && $object[$key] != null && $object[$key] != "");
}
function isDBContain($db, $parameters)
{
    foreach (DB::getAll($db) as $data) {
        $data = stdClassToArray($data);
        $isMatch = true;
        foreach ($parameters as $key => $value) {
            if ($data[$key] != $value) {
                $isMatch = false;
                break;
            }
        }
        if ($isMatch) return true;
    }
    return false;
}

function generateQRcode($path, $value)
{
    $pngAbsoluteFilePath = $path . $value . '.png';
    QRcode::png($value, $pngAbsoluteFilePath);
    return $pngAbsoluteFilePath;
}

function getParameter($post, $get)
{
    $input = file_get_contents('php://input');
    if (isJSONString($input)) $post = json_decode($input, true);
    $result = array();
    $parameter = array_merge($post, $get);
    $result = parseValue($parameter);
    return $result;
}

function parseArray($string)
{
    $array = substr($string, 1);
    $array = substr($array, 0, -1);
    $value = explode(", ", $array);
    return $value;
}

function parseValue($parameters)
{
    $result = array();
    foreach ($parameters as $key => $value) {
        if (is_array($value)) {
            $result[$key] = parseValue($value);
        } else if (isJSONString($value)) {
            $value = json_decode($value, true);
            $result[$key] = parseValue($value);
        } else if (is_string($value) && strlen($value) > 0 && $value[0] === "[" && $value[strlen($value) - 1] === "]") {
            $array = substr($value, 1);
            $array = substr($array, 0, -1);
            $value = explode(", ", $array);
            $result[$key] = parseValue($value);
        } else if (is_bool($value) || $value == null) {
            $result[$key] = $value;
        } else {
            $result[$key] = trim($value);
        }
    }
    return $result;
}

function getAllApi($parameters, $class)
{
    $cachedList = array();
    $result = array();
    $joinClassList = isset($parameters["joinClass"]) ? $parameters["joinClass"] : array();
    foreach ($joinClassList as $joinClass) {
        $cachedList[$joinClass::getSelfName()] = DB::getAllMap($joinClass);
    }
    $dataClass = DB::getAll($class, array("computed" => isset($parameters["computed"]) ? $parameters["computed"] : array()));
    foreach ($dataClass as $each) {
        $each->customAssignField($cachedList, array("computed" => isset($parameters["computed"]) ? $parameters["computed"] : array(), "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array(), "mask" => isset($parameters["mask"]) ? $parameters["mask"] : array()));
        array_push($result, $each);
    }
    if (isset($parameters["whereCondition"])) //DEPRECATED
        $result = filter($result, function ($data, $key) use ($parameters) {
            foreach ($parameters["whereCondition"] as $whereCondition) {
                foreach ($whereCondition as $key => $value) {
                    if (getDeepProp($data, $key) == $value)
                        return true;
                }
            }
            return false;
        });
    if (isset($parameters["whereOperation"])){
        $parameters["whereOperationType"] = isset($parameters["whereOperationType"]) ? $parameters["whereOperationType"] : "OR";
        if($parameters["whereOperationType"] == "OR")
            $result = filter($result, function ($data, $key) use ($parameters) {
                foreach ($parameters["whereOperation"] as $whereOperation) {
                        switch($whereOperation["type"]){
                            case "EQUAL":
                                return getDeepProp($data, $whereOperation["key"]) == $whereOperation["value"];
                            case "NOT_EQUAL":
                                return getDeepProp($data, $whereOperation["key"]) != $whereOperation["value"];
                            case "CONTAIN":
                                try{
                                    return strpos(getDeepProp($data, $whereOperation["key"]), $whereOperation["value"]) !== false;
                                }catch(Exception $e){
                                    return false;
                                }
                            case "MORE":
                                return getDeepProp($data, $whereOperation["key"]) > $whereOperation["value"];
                            case "LESS":
                                return getDeepProp($data, $whereOperation["key"]) < $whereOperation["value"];
                            case "MORE_OR_EQUAL":
                                return getDeepProp($data, $whereOperation["key"]) >= $whereOperation["value"];
                            case "LESS_OR_EQUAL":
                                return getDeepProp($data, $whereOperation["key"]) <= $whereOperation["value"];
                            case "LENGTH_EQUAL":
                                return sizeof(getDeepProp($data, $whereOperation["key"])) == $whereOperation["value"];
                            case "LENGTH_MORE":
                                return sizeof(getDeepProp($data, $whereOperation["key"])) > $whereOperation["value"];
                            case "LENGTH_LESS":
                                return sizeof(getDeepProp($data, $whereOperation["key"])) < $whereOperation["value"];
                            case "LENGTH_MORE_OR_EQUAL":
                                return sizeof(getDeepProp($data, $whereOperation["key"])) >= $whereOperation["value"];
                            case "LENGTH_LESS_OR_EQUAL":
                                return sizeof(getDeepProp($data, $whereOperation["key"])) <= $whereOperation["value"];
                            case "BETWEEN":
                                $value = getDeepProp($data, $whereOperation["key"]);
                                if($value == null)
                                    return false;
                                $start = $whereOperation["value"][0];
                                $end = $whereOperation["value"][1];
                                return $value >= $start && $value <= $end;
                            case "BETWEEN_TIME_RANGE":
                                $value = getDeepProp($data, $whereOperation["key"]);
                                if($value == null)
                                    return false;
                                $startTime = strtotime($whereOperation["value"][0]);
                                $endTime = strtotime($whereOperation["value"][1]);
                                return strtotime($value) >= $startTime && strtotime($value) <= $endTime;
                            case "ARRAY_INCLUDES_ARRAY":
                                $valueList = getDeepProp($data, $whereOperation["key"]);
                                foreach($valueList as $value){
                                    foreach($whereOperation["value"] as $whereOperationValue){
                                        if($whereOperation["value"] == $value)
                                            return true;
                                    }
                                }
                                return false;
                            case "ARRAY_INCLUDES_VALUE":
                                $value = getDeepProp($data, $whereOperation["key"]);
                                if(is_array($whereOperation["value"]))
                                    return in_array($value, $whereOperation["value"]);
                                else
                                    return in_array($whereOperation["value"],$value);
                            case "ARRAY_NOT_INCLUDES_VALUE":
                                $value = getDeepProp($data, $whereOperation["key"]);
                                if(is_array($whereOperation["value"]))
                                    return !in_array($value, $whereOperation["value"]);
                                else
                                    return !in_array($whereOperation["value"],$value);
                            case "ARRAY_OBJECT_EQUAL_VALUE":
                                $objectList = getDeepProp($data, $whereOperation["key"]);
                                foreach($objectList as $object){
                                    $value = getDeepProp($object, $whereOperation["object_prop"]);
                                    if($value == $whereOperation["value"])
                                        return true;
                                    }
                                return false;
                        }
                    // }
                }
                return false;
            });
        elseif($parameters["whereOperationType"] == 'AND'){
            $result = filter($result, function($data, $key) use($parameters){
                $isMatches = true;
                foreach ($parameters["whereOperation"] as $whereOperation) {
                    if(dataMatchesCondition($data, $whereOperation) == false){
                        $isMatches = false;
                    }
                }
                return $isMatches;
            });
        }
        else
            foreach ($parameters["whereOperation"] as $whereOperation) {
                $result = filter($result, function($data, $key) use($whereOperation){
                    switch($whereOperation["type"]){
                        case "EQUAL":
                            return getDeepProp($data, $whereOperation["key"]) == $whereOperation["value"];
                        case "NOT_EQUAL":
                            return getDeepProp($data, $whereOperation["key"]) != $whereOperation["value"];
                        case "MORE":
                            return getDeepProp($data, $whereOperation["key"]) > $whereOperation["value"];
                        case "LESS":
                            return getDeepProp($data, $whereOperation["key"]) < $whereOperation["value"];
                        case "MORE_OR_EQUAL":
                            return getDeepProp($data, $whereOperation["key"]) >= $whereOperation["value"];
                        case "LESS_OR_EQUAL":
                            return getDeepProp($data, $whereOperation["key"]) <= $whereOperation["value"];
                        case "LENGTH_EQUAL":
                            return sizeof(getDeepProp($data, $whereOperation["key"])) == $whereOperation["value"];
                        case "LENGTH_MORE":
                            return sizeof(getDeepProp($data, $whereOperation["key"])) > $whereOperation["value"];
                        case "LENGTH_LESS":
                            return sizeof(getDeepProp($data, $whereOperation["key"])) < $whereOperation["value"];
                        case "LENGTH_MORE_OR_EQUAL":
                            return sizeof(getDeepProp($data, $whereOperation["key"])) >= $whereOperation["value"];
                        case "LENGTH_LESS_OR_EQUAL":
                            return sizeof(getDeepProp($data, $whereOperation["key"])) <= $whereOperation["value"];
                        case "BETWEEN":
                            $value = getDeepProp($data, $whereOperation["key"]);
                            if($value == null)
                                return false;
                            $start = $whereOperation["value"][0];
                            $end = $whereOperation["value"][1];
                            return $value >= $start && $value <= $end;
                        case "BETWEEN_TIME_RANGE":
                            $value = getDeepProp($data, $whereOperation["key"]);
                            if($value == null)
                                return false;
                            $startTime = strtotime($whereOperation["value"][0]);
                            $endTime = strtotime($whereOperation["value"][1]);
                            return strtotime($value) >= $startTime && strtotime($value) <= $endTime;
                        case "ARRAY_INCLUDES_ARRAY":
                            $valueList = getDeepProp($data, $whereOperation["key"]);
                            foreach($valueList as $value){
                                foreach($whereOperation["value"] as $whereOperationValue){
                                    if($whereOperation["value"] == $value)
                                        return true;
                                }
                            }
                            return false;
                        case "ARRAY_INCLUDES_VALUE":
                            $value = getDeepProp($data, $whereOperation["key"]);
                            if(is_array($whereOperation["value"]))
                                return in_array($value, $whereOperation["value"]);
                            else
                                return in_array($whereOperation["value"],$value);
                        case "ARRAY_NOT_INCLUDES_VALUE":
                            $value = getDeepProp($data, $whereOperation["key"]);
                            if(is_array($whereOperation["value"]))
                                return !in_array($value, $whereOperation["value"]);
                            else
                                return !in_array($whereOperation["value"],$value);
                        case "ARRAY_OBJECT_EQUAL_VALUE":
                            $objectList = getDeepProp($data, $whereOperation["key"]);
                            foreach($objectList as $object){
                                $value = getDeepProp($object, $whereOperation["object_prop"]);
                                if($value == $whereOperation["value"])
                                    return true;
                                }
                            return false;
                    }
                        return false;
                });
            }
    }
    if (isset($parameters["advancedSearch"]))
        $result = advancedSearch($result, $parameters["advancedSearch"]);
    if (isset($parameters["paging"])) $result =  paging($result, $parameters["paging"]["page"], $parameters["paging"]["pageSize"], isset($parameters["paging"]["search"]) ? $parameters["paging"]["search"] : "", isset($parameters["paging"]["sort"]) ? $parameters["paging"]["sort"] : array("prop" => "ID", "order" => "descending"));
    return $result;
}

function checkTimeIsInRange($startTime, $endTime, $value){
    if($value == null)
        return false;
    $result = strtotime($value) >= strtotime($startTime) && strtotime($value) <= strtotime($endTime);
    return $result;
}

function dataMatchesCondition($data, $whereOperation){
    switch($whereOperation["type"]){
        case "BETWEEN_TIME_RANGE_JOURNAL_AND_LEDGER":
            $journal = array();
            $ledger = array();
            foreach(getDeepProp($data, 'journal') as $j ){
                if(checkTimeIsInRange($whereOperation["value"][0], $whereOperation["value"][1], $j->date)){
                    $journal[] = $j;
                }
            }

            foreach(getDeepProp($data, 'ledger') as $l ){
                if(checkTimeIsInRange($whereOperation["value"][0], $whereOperation["value"][1], $l->date)){
                    $ledger[] = $l;
                }
            }
            $data->journal = $journal;
            $data->ledger = $ledger;
            return true;
        case "EQUAL":
            return getDeepProp($data, $whereOperation["key"]) == $whereOperation["value"];
        case "NOT_EQUAL":
            return getDeepProp($data, $whereOperation["key"]) != $whereOperation["value"];
        case "MORE":
            return getDeepProp($data, $whereOperation["key"]) > $whereOperation["value"];
        case "LESS":
            return getDeepProp($data, $whereOperation["key"]) < $whereOperation["value"];
        case "MORE_OR_EQUAL":
            return getDeepProp($data, $whereOperation["key"]) >= $whereOperation["value"];
        case "LESS_OR_EQUAL":
            return getDeepProp($data, $whereOperation["key"]) <= $whereOperation["value"];
        case "LENGTH_EQUAL":
            return sizeof(getDeepProp($data, $whereOperation["key"])) == $whereOperation["value"];
        case "LENGTH_MORE":
            return sizeof(getDeepProp($data, $whereOperation["key"])) > $whereOperation["value"];
        case "LENGTH_LESS":
            return sizeof(getDeepProp($data, $whereOperation["key"])) < $whereOperation["value"];
        case "LENGTH_MORE_OR_EQUAL":
            return sizeof(getDeepProp($data, $whereOperation["key"])) >= $whereOperation["value"];
        case "LENGTH_LESS_OR_EQUAL":
            return sizeof(getDeepProp($data, $whereOperation["key"])) <= $whereOperation["value"];
        case "BETWEEN":
            $value = getDeepProp($data, $whereOperation["key"]);
            if($value == null)
                return false;
            $start = $whereOperation["value"][0];
            $end = $whereOperation["value"][1];
            return $value >= $start && $value <= $end;
        case "BETWEEN_TIME_RANGE":
            $value = getDeepProp($data, $whereOperation["key"]);
            if($value == null)
                return false;
            $startTime = strtotime($whereOperation["value"][0]);
            $endTime = strtotime($whereOperation["value"][1]);
            return strtotime($value) >= $startTime && strtotime($value) <= $endTime;
        case "ARRAY_INCLUDES_ARRAY":
            $valueList = getDeepProp($data, $whereOperation["key"]);
            foreach($valueList as $value){
                foreach($whereOperation["value"] as $whereOperationValue){
                    if($whereOperation["value"] == $value)
                        return true;
                }
            }
            return false;
        case "ARRAY_INCLUDES_VALUE":
            $value = getDeepProp($data, $whereOperation["key"]);
            if(is_array($whereOperation["value"]))
                return in_array($value, $whereOperation["value"]);
            else
                return in_array($whereOperation["value"],$value);
        case "ARRAY_NOT_INCLUDES_VALUE":
            $value = getDeepProp($data, $whereOperation["key"]);
            if(is_array($whereOperation["value"]))
                return !in_array($value, $whereOperation["value"]);
            else
                return !in_array($whereOperation["value"],$value);
        case "ARRAY_OBJECT_EQUAL_VALUE":
            $objectList = getDeepProp($data, $whereOperation["key"]);
            foreach($objectList as $object){
                $value = getDeepProp($object, $whereOperation["object_prop"]);
                if($value == $whereOperation["value"])
                    return true;
                }
            return false;
    }
    return false;
}

function generateBaseURL($arrayOfModel, $parameters, $options)
{
    foreach ($arrayOfModel as $key => $class) {
        if ($parameters["ACTION"] === "get_" . $class::getSelfName() . "_all") {
            return new Response(200, "Success", array($class::getSelfName() => getAllApi($parameters, $class)), true);
        } else if ($parameters["ACTION"] === "get_" . $class::getSelfName()) {
            if (!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $parameters["ID"],  array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array(),
                "computed" => isset($parameters["computed"]) ? $parameters["computed"] : array(),
                "mask" => isset($parameters["mask"]) ? $parameters["mask"] : array(),
            ))), true);
        } else if ($parameters["ACTION"] === "default_update_" . $class::getSelfName()) {
            if (!isset($parameters["ID"])) throw new Exception("ID Does Not Existed");
            $instance = DB::getByID($class::getSelfName(), $parameters["ID"]);
            $instance->update($parameters);
            return new Response(200, "Success", array());
        } else if ($parameters["ACTION"] === "default_insert_" . $class::getSelfName()) {
            $id = $class::insert($parameters);
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $id)));
        } else if ($parameters["ACTION"] === "default_delete_" . $class::getSelfName()) {
            if (!isset($parameters["ID"])) throw new Exception("ID Does Not Existed");
            $instance = DB::getByID($class::getSelfName(), $parameters["ID"]);
            $instance->delete($parameters);
            return new Response(200, "Success", array());
        } else if ($parameters["ACTION"] === "search_" . $class::getSelfName()) {
            $dataList = DB::getAll_new($class,  array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array(),
                "whereCondition" => isset($parameters["whereCondition"]) ? $parameters["whereCondition"] : null,
            ));
            $dataList = search($dataList, isset($parameters["search"]) ? $parameters["search"] : null, 100);
            $matchedData = null;
            if(isset($parameters["search"])){
                try{
                    $matchedData = DB::getByID($class, $parameters["search"], array(
                        "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array(),
                        "computed" => isset($parameters["computed"]) ? $parameters["computed"] : array(),
                        "mask" => isset($parameters["mask"]) ? $parameters["mask"] : array(),
                    ));
                }catch(Exception $e){
                    $matchedData = null;
                }
            } 
            if($matchedData != null && !in_array($matchedData->ID, map($dataList, function($data, $key){
                return $data->ID;
            })))
                array_push($dataList, $matchedData);
            return new Response(200, "Success", $dataList);
        } else if ($parameters["ACTION"] === "get_self_Notification") {
            return new Response(200, "Success", Notification::getSelfAllNotification());
        }else if ($parameters["ACTION"] === "read_notification") {
            foreach(Notification::getSelfUnReadNotification() as $notification){
                $notification->readNotification();
            }
            return new Response(200, "Success", array());
        }
        else if ($parameters["ACTION"] === "get_self") {
            return new Response(200, "Success", $GLOBALS['currentUser']);;
        }else if($parameters["ACTION"] === "update_self"){
                $parameters = array_merge($parameters, array("ID" => $GLOBALS['currentUser']->ID));
                DB::update($parameters, $options["userClass"]);
                return new Response(200, "Success", array());
        }
    }
}


function filterParameterByClass($parameters, $class)
{
    $result = array();
    $parameters = stdClassToArray($parameters);
    $classParameterList = array_merge(map($class::getRealFields(), function ($data, $key) {
        return $data["key"];
    }), array("ID"));
    foreach ($classParameterList as  $value) {
        if (array_key_exists($value, $parameters)) $result[$value] = $parameters[$value];
    }
    return $result;
}



function isJSONString($string)
{
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
}

function flat($array, &$return)
{
    if (is_array($array)) {
        array_walk_recursive($array, function ($a) use (&$return) {
            flat($a, $return);
        });
    } else if (is_string($array) && stripos($array, '[') !== false) {
        $array = explode(',', trim($array, "[]"));
        flat($array, $return);
    } else {
        $return[] = $array;
    }
}

function readXlsx($xlsx)
{
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $spreadsheet = $reader->load($xlsx);
    $worksheet = $spreadsheet->getActiveSheet();
    return $worksheet->toArray();
}

function readCsv($csv)
{
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
    $spreadsheet = $reader->load($csv);
    $worksheet = $spreadsheet->getActiveSheet();
    return $worksheet->toArray();
}

function isContain($array, $field, $value)
{
    if (sizeof($array) > 0 && is_object(current($array))) {
        foreach ($array as $data) {
            if ($data->$field == $value)
                return true;
        }
        return false;
    } else {
        foreach ($array as $data) {
            if ($data[$field] == $value)
                return true;
        }
        return false;
    }
}

function arrayFilterUnique($array, $field)
{
    $result = array();
    foreach ($array as $data) {
        if (is_object($data)) {
            if (!isContain($result, $field, $data->$field))
                array_push($result, $data);
        } else {
            if (!isContain($result, $field, $data[$field]))
                array_push($result, $data);
        }
    }
    return $result;
}
function map($array, $function)
{
    $result = array();
    foreach ($array as $key => $data) {
        array_push($result, $function($data, $key));
    }
    return $result;
}

function filter($array, $function)
{
    $result = array();
    foreach ($array as $key => $data) {
        $size = sizeof($result);
        if ($function($data, $key, $size)) {
            // array_push($result, $data);
            $result[] = $data;
        }
    }
    return $result;
}

function find($array, $function)
{
    foreach ($array as $key => $data) {
        if ($function($data, $key))
            return $data;
    }
    return null;
}

function findIndex($array, $function)
{
    foreach ($array as $key => $data) {
        if ($function($data, $key))
            return $key;
    }
    return null;
}

function getCurrentUser($userClass)
{
    $user = Auth::getLoginUser($userClass);
    if ($user == null)  return null;
    return $user;
}

function isArrayDuplicate($array)
{
    return count($array) !== count(array_unique($array));
}

function multiFieldSearch($type, $searchFilterSet, $eachData){
    if(sizeof($searchFilterSet['multiFields']) > 0){
        foreach($searchFilterSet['multiFields'] as $additionalField){
            switch($type){
                case "String":
                    if((strpos(strtolower(strval(getDeepProp($eachData, $additionalField))), strtolower(strval($searchFilterSet["value"]))) !== false))
                        return true;
                    break;
                case "Multi-Selection":
                    //TODO
                    return false;
                    break;
                case "TIME-RANGE":
                    //TODO
                    return false;
                    break;
                case "NUMBER-RANGE":
                    //TODO
                    return false;
                    break;
            }
        }
    }
}

function advancedSearch($data, $advancedSearch)
{
    foreach ($advancedSearch as $column => $searchFilterSet) {
        switch ($searchFilterSet["type"]) {
            case "FREETEXT":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    if(multiFieldSearch("String", $searchFilterSet, $data))
                        return true;
                    return (strpos(strtolower(strval(getDeepProp($data, $column))), strtolower(strval($searchFilterSet["value"]))) !== false);
                });
                break;
            case "SELECTION":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    if(multiFieldSearch("String", $searchFilterSet, $data))
                        return true;
                    return (strtolower(strval(getDeepProp($data, $column))) === strtolower(strval($searchFilterSet["value"])));
                });
                break;
            case "MULTI-SELECTION":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    foreach ($searchFilterSet["value"] as $value) {
                        $dataValue = getDeepProp($data, $column);
                        if (is_array($dataValue)) {
                            if (in_array($value, $dataValue))
                                return true;
                        } else {
                            if (strtolower(strval($dataValue)) === strtolower(strval($value)))
                                return true;
                        }
                    }
                    return false;
                });
                break;
            case "MULTI-SELECTION-SELECTOR":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    foreach ($searchFilterSet["value"] as $value) {
                        $dataValue = getDeepProp($data, $column);
                        if (is_array($dataValue)) {
                            if (in_array($value, $dataValue))
                                return true;
                        } else {
                            if (strtolower(strval($dataValue)) === strtolower(strval($value)))
                                return true;
                        }
                    }
                    return false;
                });
                break;
            case "TIME-RANGE":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    return (isBetweenDates($searchFilterSet["value"][0], $searchFilterSet["value"][1], getDeepProp($data, $column)));
                });
                break;
            case "NUMBER-RANGE":
                $data = filter($data, function ($data, $key) use ($column, $searchFilterSet) {
                    $value = intval(getDeepProp($data, $column));
                    return ($value >= intval($searchFilterSet["value"][0]) && $value <= intval($searchFilterSet["value"][1]));
                });
                break;
        }
    }
    return $data;
}
function paging($dataList, $page, $pageSize, $search = "", $sort = array(
    "prop" => "ID",
    "order" => "descending",
), $customSortFunction = null)
{
    $totalRow = 0;
    if ($search != "" && $search != null)
        $dataList = filter($dataList, function ($data, $index) use ($page, $pageSize, $search) {
            return (checkSearch($search, $data));
        });
    $totalRow = count($dataList);
    if ($customSortFunction == null)
        $dataList = sortPaging($dataList, $sort["prop"], $sort["order"]);
    else
        $dataList = $customSortFunction($dataList, $sort["prop"], $sort["order"]);
    $dataList = filter($dataList, function ($data, $index) use ($page, $pageSize, $search) {
        return (checkPaging($index, $page, $pageSize));
    });
    return array("data" => $dataList, "totalRow" => $totalRow);
}

function checkPaging($index, $page, $pageSize)
{
    return ($index >= $page * $pageSize - $pageSize &&  $index < $page * $pageSize);
}

function checkSearch($search, $data)
{
    if ($search == "" || $search == null || is_array($search)) return true;
    foreach ($data as $key => $value) { 
        if (strpos(strtolower(json_encode($value)), strtolower($search)) !== false)
            return true;
    };
    return false;
}

function sortPaging($dataList, $sortProp, $sortOrder)
{
    try {
        usort($dataList, function ($a, $b) use ($sortProp, $sortOrder) {
            if (strpos($sortProp, ".") !== false) {
                $sortPropList = explode(".", $sortProp);
                foreach ($sortPropList as $prop) {
                    if (isset($a->$prop))
                        $a = $a->$prop;
                    else
                        return false;
                    if (isset($b->$prop))
                        $b = $b->$prop;
                    else
                        return false;
                }
                if (is_array($a) || is_array($b))
                    return ($sortOrder === "ascending") ? (json_encode($a) > json_encode($b)) : (json_encode($a) < json_encode($b));
                return ($sortOrder === "ascending") ? ($a > $b) : ($a < $b);
            }
            return ($sortOrder === "ascending") ? (strtolower($a->$sortProp) > strtolower($b->$sortProp)) : (strtolower($a->$sortProp) < strtolower($b->$sortProp));
        });
    } catch (Exception $e) {
        return $dataList;
    }

    return $dataList;
}

function array_unique_stdClass($array)
{
    return array_map('json_decode', array_unique(array_map('json_encode', $array)));
}

function checkClassInstanceExisted($class, $ID)
{
    // if(DB::getByID($class, $ID) == null) throw new Exception($class::getSelfName)
}

function search($dataList, $search, $limit)
{
    $dataList = array_reverse($dataList);
    return filter($dataList, function ($data, $index, $size) use ($search, $limit) {
        if ($size > $limit) return false;
        if ($search === null) return true;
        return checkSearch($search, $data);
    });
}

function getDeepProp($classObject, $prop)
{
    $propList = explode(".", $prop);
    foreach ($propList as $prop) {
        if (isset($classObject->$prop))
            $classObject = $classObject->$prop;
        else {
            $classObject = null;
            break;
        }
    }
    if($classObject === false)
        return 0;
    return $classObject;
}

function isBeforeDate($toDate, $value)
{
    $valueDate = date('Y-m-d H:i:s', strtotime($value));
    $dateEnd = date('Y-m-d H:i:s', strtotime($toDate));
    return $valueDate <= $dateEnd;
}

function isOverDate($fromDate, $value)
{
    $valueDate = date('Y-m-d H:i:s', strtotime($value));
    $dateBegin = date('Y-m-d H:i:s', strtotime($fromDate));
    return $valueDate >= $dateBegin;
}
function isBetweenDates($fromDate, $toDate, $value)
{
    $valueDate = date('Y-m-d H:i:s', strtotime($value));
    $dateBegin = date('Y-m-d H:i:s', strtotime($fromDate));
    $dateEnd = date('Y-m-d H:i:s', strtotime($toDate));
    if (($valueDate >= $dateBegin) && ($valueDate <= $dateEnd))
        return true;
    return false;
}

function getLatestTable($table, $time, $field){
    $list = DB::getAll_new($table);
    $list = filter($list, function($data, $key) use($field, $time){
        if($data->{$field} == null)
           return false;
        return (isBetweenDates(date("Y-m-d H:i:s", strtotime($time)), date("Y-m-d H:i:s"), $data->{$field}));
     });
    usort($list, function ($a, $b) use($field){
       if($a->{$field} == null)
          return 1;
       return ($a->{$field} > $b->{$field}) ? -1 : 1;
    });
    return $list;
}

function nameSearch($dataList, $percentageThreshold, $nameFieldList, $searchText, $limit = null)
{
    $searchList = array_unique(preg_split('/\s+/', strtolower($searchText)));
    $result = array();
    foreach ($dataList as $key => $data) {
        $data = stdClassToArray($data);
        $name = "";
        foreach ($nameFieldList as $nameField) {
            $name .= " " . $data[$nameField];
        }
        $nameList = array_unique(preg_split('/\s+/', strtolower($name)));
        $count = 0;
        $isAddToResult = ($data["ID"] == $searchText) ? true : false;
        foreach ($nameList as $name) {
            $percentage = 0;
            foreach ($searchList as $searchText) {
                $tempPercentage = 0;
                similar_text($name, $searchText, $tempPercentage);
                if ($percentage < $tempPercentage) $percentage = $tempPercentage;
            }
            if ($percentage > $percentageThreshold) {
                $count += 1 * $percentage;
                $isAddToResult = true;
            }
        }
        $data["rating"] = $count;
        $data["count"] = $count;
        if ($isAddToResult)
            array_push($result, $data);
    }
    usort($result, function ($a, $b) {
        if ($a["rating"] === $b["rating"])
            return ($a["count"] > $b["count"]) ? -1 : 1;
        return ($a["rating"] > $b["rating"]) ? -1 : 1;
    });
    if($limit != null && sizeof($result) >= $limit) array_splice($result, $limit);
    return $result;
}

function isNullOrEmptyString($str){
    return (!isset($str) || trim($str) === '');
}

function isThatDay($date, $current){
    $current = strtotime($current);
    $date = strtotime($date);
    $datediff = $date - $current;
    $difference = floor($datediff/(60*60*24));
    if($difference==0)
        return true;
    else
        return false;
}