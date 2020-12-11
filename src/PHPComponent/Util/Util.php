<?php
function init()
{
    set_error_handler(function ($severity, $message, $filename, $lineno) {
        if (error_reporting() == 0) {
            return;
        }
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $filename, $lineno);
        }
    });
    if (!function_exists('getallheaders')) {
        function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
        }
    }
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: token, Apikey, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    date_default_timezone_set("Asia/Hong_Kong");
    if(($_SERVER['REQUEST_METHOD'] == 'OPTIONS')){
        $response =  new Response(200, "Success", "");
        echo $response->send_response();
        die();
    }
}

function readConfig()
{
    return json_decode(file_get_contents("./config.json"));
}
function getFile($filePath)
{
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-Disposition: attachment; filename=" . $filePath);
    readfile($filePath);
}

function getRequestToken()
{
    try {
        return array_change_key_case(getallheaders(), CASE_LOWER)["token"];
    } catch (Exception $exception) {
        return "";
    }
}

function stdClassToArray($classObj){
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

function parseStdClass($object){
    if(is_object($object)) 
        return json_decode(json_encode($object), true);
    else
        return $object;
}



function logOutRemoveToken($token)
{
    DB::deleteByWhereCondition(Token::class, array('token' => $token));
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

function apiKeyChecking()
{
    if (getallheaders()['Apikey'] != readConfig()->Apikey)
        throw new Exception('api key checking failed');
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
    try{
        return (strpos(strtolower(strval($sentence)), strtolower($value)) !== false);
    }catch(Exception $e){
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
    (isJSONString($input)) ? $post = json_decode($input, true) : $post = array();
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
        } else if (is_string($value) && strlen($value > 0) && $value[0] == "[" && $value[strlen($value) - 1] == "]") {
            $array = substr($value, 1);
            $array = substr($array, 0, -1);
            $value = explode(", ", $array);
            $result[$key] = parseValue($value);
        } else if(is_bool($value) || $value == null){
            $result[$key] = $value;
        }
        else{
            $result[$key] = trim($value);
        }
    }
    return $result;
}

function generateBaseURL($arrayOfModel, $parameters)
{
    foreach ($arrayOfModel as $key => $class) {
        if ($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all") {
            $class::getPublicCheck();
            return new Response(200, "Success", array($class::getSelfName() => map(DB::getAll($class, BaseModel::PUBLIC), function($data) use($class){
                return $data->filterField($data::getFields(BaseModel::PUBLIC));
            })));
        } else if ($parameters["ACTION"] == "get_" . $class::getSelfName()) {
            if (!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            $class::getPublicCheck();
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $parameters["ID"], BaseModel::PUBLIC)->filterField($class::getFields(BaseModel::PUBLIC))));
        } 
        else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all_detail") {
            $class::getDetailCheck();
            return new Response(200, "Success", array($class::getSelfName() => map(DB::getAll($class, BaseModel::DETAIL), function($data) use($class){
                return $data->filterField($class::getFields(BaseModel::DETAIL));
            })));
        } else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . '_detail') {
            if (!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            $class::getDetailCheck();
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $parameters["ID"], BaseModel::DETAIL)->filterField($class::getFields(BaseModel::DETAIL))));
        }
    }
}


function filterParameterByClass($parameters, $class, $mode = BaseModel::PUBLIC)
{
    $result = array();
    $parameters = stdClassToArray($parameters);
    $classParameterList = $class::getFields($mode);
    foreach ($classParameterList as  $value) {
        if (array_key_exists($value, $parameters)) $result[$value] = $parameters[$value];
    }
    return $result;
}

function catchWarningToException()
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
        if (0 === error_reporting()) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
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

function isContain($array, $field, $value){
    if (sizeof($array) > 0 && is_object(current($array))) {
        foreach($array as $data){
            if($data->$field == $value)
                return true;
        }
        return false;
    }else{
        foreach($array as $data){
            if($data[$field] == $value)
                return true;
        }
        return false;
    }
}

function arrayFilterUnique($array, $field){
    $result = array();
    foreach($array as $data){
        if(is_object($data)){
            if(!isContain($result, $field, $data->$field))
                array_push($result, $data);
        }else{
            if(!isContain($result, $field, $data[$field]))
                array_push($result, $data);
        }
    }
    return $result;
}
function map($array, $function){
    $result = array();
    foreach($array as $key=> $data){
        array_push($result, $function($data, $key));
    }
    return $result;
}

function filter($array, $function){
    $result = array();
    foreach($array as $key=> $data){
        if($function($data, $key)){
            array_push($result, $data);
        }
    }
    return $result;
}

function getCurrentUser($userClass){
    $user = Auth::getLoginUser($userClass);
    if($user == null)  return null;
    return $user;
 }