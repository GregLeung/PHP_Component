<?php
    class BaseSystem{
        public $config;
        public $parameters;
        public $response;
        public $userClass;
        public $classList;
        function __construct($classList, $userClass) {
            $this->config = readConfig();
            $this->classList = $classList;
            DB::getInstance('localhost', $this->config->database_account, $this->config->database_password, $this->config->database_name );
            setAllowOrigin(array("*"));
            init();
            $this->parameters = getParameter($_POST, $_GET);
            $this->userClass = $userClass;
        }

        public function ready($function){
            try{
                $GLOBALS['currentUser'] = getCurrentUser($this->userClass);
                $this->response = generateBaseURL($this->classList, $this->parameters);
                $this->response = $function($this->config, $this->parameters, $this->response);
                $this->loginAPI();
                $this->extraAPI();
                if ($this->response == null) throw new Exception("URL Not Found");
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
        }
        private function loginAPI(){
            switch ($this->parameters["ACTION"]) {
                case "user_login":
                    if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                    if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                    $this->response = new Response(200, "Success", Auth::login($this->userClass, $this->parameters['loginName'], $this->parameters['password']));
                    break;
                case "mobile_login":
                    if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                    if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                    $this->response = new Response(200, "Success", Auth::login($this->userClass, $this->parameters['loginName'], $this->parameters['password'], 999999999));
                    break;
                case "user_logout":
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
                        $response = new Response(200, "Success", $fileName);
                    else
                        throw new Exception("Create File Error");
                    break;
            }
        }
    }