<?php
// require_once  "../src/PHPComponent/phpqrcode/qrlib.php";


function init(){
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    date_default_timezone_set("Asia/Hong_Kong");
    catchWarningToException();
}
function isExistedNotNull($object, $key){
    return (array_key_exists($key,$object) && $object[$key] != null);
}
function generateQRcode($path, $value){
    $pngAbsoluteFilePath = $path.$value.'.png';
    QRcode::png($value, $pngAbsoluteFilePath);
    return $pngAbsoluteFilePath;
}

function getParameter($post, $get){
    $input = file_get_contents('php://input');
    (isJSON($input))?$post = json_decode($input, true):$post = array();
    $result = array();
    $parameter = array_merge($post, $get);
    foreach ($parameter as $key => $value) {
        if(is_string($value) && strlen($value > 0) && $value[0] == "[" && $value[strlen($value)- 1] == "]"){
            $array = substr($value, 1);
            $array = substr($array, 0, -1);
            $result[$key] = explode(", ", $array);
        }else{
            $result[$key] = $value;
        }
    }
    return $result;
}

function generateBaseURL($arrayOfModel, $parameters){
    foreach ($arrayOfModel as $key => $class) {
        if($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all"){
            return new Response(200, "Success", array($class::getSelfName()=>DB::get($class)));
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
        if(array_key_exists($key, $parameters) && $parameters[$key] !== null) $result[$key] = $parameters[$key];
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


function isJSON($string){
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
 }
?>