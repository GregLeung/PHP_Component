<?php
initLog();
function initLog(){
    if(defined("SITE_ROOT")){
        date_default_timezone_set("Asia/Hong_Kong");
        umask(0);
        if(!file_exists(SITE_ROOT."/log")) 
            mkdir(SITE_ROOT."/log", 0777);
        register_shutdown_function(function(){
            $error = error_get_last();
            if($error != null)
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
    if(($_SERVER['REQUEST_METHOD'] == 'OPTIONS')){
        $response =  new Response(200, "Success", "");
        echo $response->send_response();
        die();
    }
}

function writeLog($filename, $message, $line){
    $fileLocation = SITE_ROOT . "/log/log_" . date("Y-m-d") . ".txt";
    if(file_exists($fileLocation))
        $file = fopen($fileLocation,"a");
    else
        $file = fopen($fileLocation,"w");
    fwrite($file, json_encode(array("date" => date("Y-m-d H:i:s"), "message" => $message, "file" => $filename, "line" => $line),).PHP_EOL);
    fclose($file);
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
        if(array_key_exists('Token', getallheaders())) return getallheaders()['Token'];
        else return "";
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
    DB::deleteRealByWhereCondition(Token::class, array('token' => $token));
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
            return new Response(200, "Success", array($class::getSelfName() => map(DB::getAll($class, BaseModel::PUBLIC), function($data) use($class){
                return $data->filterField($data::getFields(BaseModel::PUBLIC));
            })), true);
        } else if ($parameters["ACTION"] == "get_" . $class::getSelfName()) {
            if (!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $parameters["ID"], BaseModel::DETAIL, array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array()
            ))->filterField($class::getFields(BaseModel::DETAIL))), true);
        } 
        else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all_detail") { // will be deprecated
            return new Response(200, "Success", array($class::getSelfName() => map(DB::getAll($class, BaseModel::DETAIL), function($data) use($class){
                return $data->filterField($class::getFields(BaseModel::DETAIL));
            })), true);
        } else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . '_detail') { // will be deprecated
            if (!isExistedNotNull($parameters, "ID")) throw new Exception('ID does not existed');
            return new Response(200, "Success", array($class::getSelfName() => DB::getByID($class, $parameters["ID"], BaseModel::DETAIL, array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array()
            ))->filterField($class::getFields(BaseModel::DETAIL))), true);
        } 
        else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . "_all_paging") {
            if(!isset($parameters["filter"])) $parameters["filter"] = null;
            if(!isset($parameters["type"])) $parameters["type"] = BaseModel::PUBLIC;
            $dataList = DB::getAll($class, $parameters["type"], array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array()
            ));
            if($parameters["filter"] != null)
                $dataList = filter($dataList, function($data, $key) use($parameters){
                    foreach($parameters["filter"] as $filterKey => $filterValue){
                        if($data->$filterKey == $filterValue){
                            return true;
                        }
                    }
                    return false;
                });
            $dataList = map($dataList, function($data) use ($class, $parameters){
                return $data->filterField($data::getFields($parameters["type"]));
            });
            $dataList = paging($dataList, $parameters["paging"]["page"], $parameters["paging"]["pageSize"], isset($parameters["paging"]["search"])?$parameters["paging"]["search"] : "", isset($parameters["paging"]["sort"])?$parameters["paging"]["sort"]:null);
            return new Response(200, "Success", array($class::getSelfName() => $dataList));
        }
        else if ($parameters["ACTION"] == "get_" . $class::getSelfName() . "_detail_all_paging") { // will be deprecated
            return new Response(200, "Success", array($class::getSelfName() => paging(map(DB::getAll($class, BaseModel::DETAIL, array(
                "joinClass" => isset($parameters["joinClass"]) ? $parameters["joinClass"] : array()
            )), function($data) use($class){
                return $data->filterField($data::getFields(BaseModel::DETAIL));
            }), $parameters["paging"]["page"], $parameters["paging"]["pageSize"], isset($parameters["paging"]["search"])?$parameters["paging"]["search"] : "", isset($parameters["paging"]["sort"])?$parameters["paging"]["sort"]:null)), true);
        }else if($parameters["ACTION"] === "update_" . $class::getSelfName()){
            if(!isset($parameters["ID"])) throw new Exception("ID Does Not Existed");
            $instance = DB::getByID($class::getSelfName(), $parameters["ID"], BaseModel::DETAIL);
            $instance->update($parameters);
            return new Response(200, "Success", array());
        }else if($parameters["ACTION"] === "insert_" . $class::getSelfName()){
            $class::insert($parameters);
            return new Response(200, "Success", array());
        }else if($parameters["ACTION"] === "delete_" . $class::getSelfName()){
            if(!isset($parameters["ID"])) throw new Exception("ID Does Not Existed");
            $instance = DB::getByID($class::getSelfName(), $parameters["ID"], BaseModel::DETAIL);
            $instance->delete($parameters);
            return new Response(200, "Success", array());
        }else if($parameters["ACTION"] === "search_" . $class::getSelfName()){
            $dataList = DB::getAll($class, BaseModel::DETAIL);
            return new Response(200, "Success", search($dataList, isset($parameters["search"]) ? $parameters["search"]: null, 50));
        }
    }
}


function filterParameterByClass($parameters, $class)
{
    $result = array();
    $parameters = stdClassToArray($parameters);
    $classParameterList = array_merge($class::getRealFields(), array("ID"));
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

function find($array, $function){
    foreach($array as $key=> $data){
        if($function($data, $key))
            return $data;
    }
    return null;
}

function getCurrentUser($userClass){
    $user = Auth::getLoginUser($userClass);
    if($user == null)  return null;
    return $user;
 }

 function paging($dataList,$page, $pageSize, $search = "", $sort = array(
     "prop"=> "ID",
     "order"=> "descending",
 )){
    $totalRow = 0;
    if($search != "" && $search != null)
    $dataList = filter($dataList, function($data, $index) use($page, $pageSize, $search){
       return (checkSearch($search, $data));
    });
    $totalRow = count($dataList);
    $dataList = sortPaging($dataList, $sort["prop"], $sort["order"]);
    $dataList = filter($dataList, function($data, $index) use($page, $pageSize, $search){
       return (checkPaging($index, $page, $pageSize));
    });
    return array("data" => $dataList, "totalRow" => $totalRow);
 }
 
 function checkPaging($index, $page, $pageSize){
    return ($index >= $page * $pageSize - $pageSize &&  $index < $page * $pageSize);
 }
 
 function checkSearch($search, $data){
    if($search == "" || $search == null) return true;
    foreach ($data as $key => $value) {
       if (strpos(strtolower(json_encode($value)), strtolower($search)) !== false) 
          return true;
   };
   return false;
 }
 
 function sortPaging($dataList, $sortProp, $sortOrder){
    try{
        if($sortOrder == "ascending")
            usort($dataList, function ($a, $b) use($sortProp){ return ($a < $b) ? -1 : 1;});
        else
            usort($dataList, function ($a, $b) use($sortProp){ return ($b < $a) ? -1 : 1;});
    }catch(Exception $e){
       return $dataList;
    }
   return $dataList;
 }

 function array_unique_stdClass($array){
    return array_map('json_decode', array_unique(array_map('json_encode', $array)));
 }

 function search($dataList, $search, $limit){
    return filter($dataList, function($data, $index) use($search, $limit){
        if($index >= $limit) return false;
        if($search === null) return true;
        return checkSearch($search, $data);
    });
 }

 function nameSearch($dataList, $percentageThreshold, $nameFieldList, $searchText){
    $searchList = array_unique(preg_split('/\s+/', strtolower($searchText)));
    $result = array();
    foreach($dataList as $key => $data){
       $data = stdClassToArray($data);
       $name = "";
       foreach($nameFieldList as $nameField){
          $name .= " " . $data[$nameField];
       }
       $nameList = array_unique(preg_split('/\s+/', strtolower($name)));
       $count = 0;
       $isAddToResult = false;
       foreach($nameList as $name){
          $percentage = 0;
          foreach($searchList as $searchText){
              $tempPercentage = 0;
              similar_text($name, $searchText, $tempPercentage);
              if($percentage < $tempPercentage) $percentage = $tempPercentage;
          }
          if($percentage > $percentageThreshold){
              $count +=1;
              $isAddToResult = true;
          } 
       }
       $data["rating"] = $count/sizeof($nameList);
       $data["count"] = $count;
       if($isAddToResult)
             array_push($result, $data);
    }
    usort($result, function ($a, $b) { 
       if($a["rating"] === $b["rating"])
           return ($a["count"] > $b["count"]) ? -1 : 1; 
       return ($a["rating"] > $b["rating"]) ? -1 : 1;
   });
   return $result;
 }