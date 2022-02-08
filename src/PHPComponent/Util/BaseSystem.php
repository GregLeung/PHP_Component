<?php
    class BaseSystem{
        public $config;
        public $parameters;
        public $response;
        public $userClass;
        public $memberClass;
        public $classList;
        function __construct($classList, $userClass, $memberClass = null) {
            ini_set('memory_limit', '1024M');
            $this->config = readConfig();
            $this->classList = $classList;
            DB::getInstance($this->config);
            setAllowOrigin(array("*"));
            init();
            $this->parameters = getParameter($_POST, $_GET);
            $this->userClass = $userClass;
            $this->memberClass = $memberClass;
        }

        public function ready($function){
            DB::startTransaction();
            try{
                apiKeyChecking();
                $GLOBALS['currentUser'] = getCurrentUser($this->userClass);
                $GLOBALS['currentMember'] = getCurrentUser($this->memberClass);
                $this->response = generateBaseURL($this->classList, $this->parameters, array("userClass" => $this->userClass));
                $this->response = $function($this->config, $this->parameters, $this->response);
                $this->loginAPI();
                $this->extraAPI();
                if ($this->response == null) throw new Exception("URL Not Found");
                DB::commit();
            }catch (BaseException $e) {
                DB::rollback();
                $this->response = new Response($e->code, $e->type, $e->getMessage());
            }catch(Exception $exception){
                DB::rollback();
                writeLog($exception->getFile(), $exception->getMessage(), $exception->getLine());
                if ($exception->getMessage() == null || $exception->getMessage() == "") $response = new Response(-1, "Failed", "URL Not Found");
                else $this->response = new Response(-1, "Failed", $exception->getMessage());
            }
            echo $this->response->send_response();
            DB::close_mysqli_conn();
        }
        private function loginAPI(){
            switch ($this->parameters["ACTION"]) {
                case "user_login":
                    if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                    if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                    $this->response = new Response(200, "Success", Auth::login($this->userClass, $this->parameters['loginName'], $this->parameters['password']));
                    break;
                case "user_logout":
                    if (!isExistedNotNull($this->parameters, "token")) throw new Exception('Token does not existed');
                    logOutRemoveToken($this->parameters['token']);
                    $this->response = new Response(200, "Success", "");
                    break;
                case "member_login":
                    if($this->memberClass == null) throw new Exception('MemberShip Function Does Not Activated');
                    if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                    if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                    $this->response = new Response(200, "Success", Auth::login($this->memberClass, $this->parameters['loginName'], $this->parameters['password']));
                    break;
                case "member_logout":
                    if($this->memberClass == null) throw new Exception('MemberShip Function Does Not Activated');
                    if (!isExistedNotNull($this->parameters, "token")) throw new Exception('Token does not existed');
                    logOutRemoveToken($this->parameters['token']);
                    $this->response = new Response(200, "Success", "");
                    break;
            }
        }
        private function extraAPI(){
            switch ($this->parameters["ACTION"]) {
                case "upload_file":
                    $file = $_FILES["file"];
                    if ($file['size'] / 1024 / 1024 > 10) throw new Exception("File Size too large");
                    $now = DateTime::createFromFormat('U.u', microtime(true));
                    $fileName = $now->format("m_d_Y_H_i_s.u") . "_" .  rand(1,999) . "_" .  $_FILES["file"]["name"];
                    if (move_uploaded_file($_FILES["file"]["tmp_name"],  SITE_ROOT . '/static/img/' . $fileName))
                        $this->response = new Response(200, "Success", $fileName);
                    else
                        throw new Exception("Create File Error");
                    break;
                case "get_table_list":
                    $this->response = new Response(200, "Success", DB::getTableList());
                    break;
                case "create_table":
                    if(!isset($parameters["tableName"]))
                        throw new Exception("tableName Cannot Be Empty");
                    if(!isset($parameters["columnList"]))
                        throw new Exception("columnList Cannot Be Empty");
                    DB::createTable($parameters["tableName"], map($parameters["columnList"], function($column){
                        return new DB_Column($column["name"], $column["type"],$column["isNullAble"],$column["defaultValue"]);
                    }));
                    $this->response = new Response(200, "Success", array());
                    break;
            }
        }
    }