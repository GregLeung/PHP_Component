<?php
function init(){
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    date_default_timezone_set("Asia/Hong_Kong");
}

function contain($sentence, $value){
    if (strpos($sentence,$value) !== false) return true;
    return false;
}
function isExistedNotNull($object, $key){
    return (array_key_exists($key,$object) && $object[$key] != null && $object[$key] != "");
}
function isDBContain($db, $parameters, $dataList = null){
    if($dataList == null) $dataList = DB::get($db, false);
    foreach(DB::get($db, false) as $data){
        $isMatch = true;
        foreach($parameters as $key => $value){
           if($data[$key] != $value){
                $isMatch = false;
                break;
           }
        }
        if($isMatch) return true;
     }
      return false;
}

function generateQRcode($path, $value){
    $pngAbsoluteFilePath = $path.$value.'.png';
    QRcode::png($value, $pngAbsoluteFilePath);
    return $pngAbsoluteFilePath;
}

function getParameter($post, $get){
    $input = file_get_contents('php://input');
    (isJSONString($input))?$post = json_decode($input, true):$post = array();
    $result = array();
    $parameter = array_merge($post, $get);
    $result = parseValue($parameter);
    return $result;
}

function parseArray($string){
    $array = substr($string, 1);
    $array = substr($array, 0, -1);
    $value = explode(", ", $array);
    return $value;
}

function parseValue($parameters){
    $result = array();
    foreach($parameters as $key => $value){
        if(is_array($value)){
            $result[$key] = parseValue($value);
        }else if(isJSONString($value)){
            $value = json_decode($value, true);
            $result[$key] = parseValue($value);
        }else if(is_string($value) && strlen($value > 0) && $value[0] == "[" && $value[strlen($value)- 1] == "]"){
            $array = substr($value, 1);
            $array = substr($array, 0, -1);
            $value = explode(", ", $array);
            $result[$key] = parseValue($value);
        }
        else{
            $result[$key] = $value;
        }
        // if(isJSONString($value)){
        //     $parameters[$key] = json_decode($value);
        //     $parameter[$key] = parseJSON($value);
        // }else if(is_array($value)){
        //     $parameters[$key] = json_decode($value);
        //     $parameter[$key] = parseJSON($value);
        // }
    }
    return $result;
}

function generateBaseURL($arrayOfModel, $parameters){
    foreach ($arrayOfModel as $key => $class) {
        if($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all"){
            return new Response(200, "Success", array($class::getSelfName()=>DB::get($class)));
        }
        else if($parameters["ACTION"] == "get_" . $class::getSelfName()){
            if(!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            return new Response(200, "Success", array($class::getSelfName()=>DB::getByID($class, $parameters["ID"])));
        }
        else if($parameters["ACTION"] == "insert_" . $class::getSelfName()){
            DB::insert(filterParameterByClass($parameters, $class), $class);
            return new Response(200, "Success", array());
        }
        else if($parameters["ACTION"] == "update_" . $class::getSelfName()){
            DB::update(filterParameterByClass($parameters, $class), $class);
            return new Response(200, "Success", array());
        }
        else if($parameters["ACTION"] == "delete_" . $class::getSelfName()){
            DB::delete($parameters["ID"], $class);
            return new Response(200, "Success", array());
        }
    }
}


function filterParameterByClass($parameters, $class){
    $result = array();
    $classParameterList = get_class_vars($class);
    foreach ($classParameterList as $key => $value) {
        if(array_key_exists($key, $parameters)) $result[$key] = $parameters[$key];
    }
    return $result;
}

function catchWarningToException(){
    set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
        if (0 === error_reporting()) {
            return false;
        }
    
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
}


function isJSONString($string){
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
 }

 function flat($array, &$return) {
    if (is_array($array)) {
        array_walk_recursive($array, function($a) use (&$return) { flat($a, $return); });
    } else if (is_string($array) && stripos($array, '[') !== false) {
        $array = explode(',', trim($array, "[]"));
        flat($array, $return);
    } else {
        $return[] = $array;
    }
}

function readXlsx($xlsx){
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $spreadsheet = $reader->load($xlsx);
    $worksheet = $spreadsheet->getActiveSheet();
    return $worksheet->toArray();
}
