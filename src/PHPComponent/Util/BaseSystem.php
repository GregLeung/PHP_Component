<?php
    class BaseSystem{
        public object $config;
        public array $parameters;
        public ?object $response;
        public ?string $userClass;
        function __construct($classList, $userClass) {
            $this->config = readConfig();
            DB::getInstance('localhost', $this->config->database_account, $this->config->database_password, $this->config->database_name );
            setAllowOrigin(array("*"));
            init();
            $this->parameters = getParameter($_POST, $_GET);
            $this->response = generateBaseURL($classList, $this->parameters);
            $this->userClass = $userClass;
        }

        public function ready($function){
            try{
                $GLOBALS['currentUser'] = getCurrentUser($this->userClass);
                $this->response = $function($this->config, $this->parameters, $this->response);
                $this->loginAPI();
                if ($this->response == null) throw new Exception("URL Not Found");
            }catch (BaseException $e) {
                DB::rollback();
                $this->response = new Response($e->code, $e->type, $e->getMessage());
            }catch(Exception $exception){
                DB::rollback();
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
                case "user_logout":
                    if (!isExistedNotNull($this->parameters, "token")) throw new Exception('Token does not existed');
                    logOutRemoveToken($this->parameters['token']);
                    $this->response = new Response(200, "Success", "");
                    break;
            }
        }
    }