<?php
class BaseSystem
{
    public $config;
    public $parameters;
    public $response;
    public $userClass;
    public $memberClass;
    public $classList;
    function __construct($classList, $userClass, $memberClass = null)
    {
        ini_set('memory_limit', '1024M');
        $this->config = readConfig();
        $this->classList = $classList;
        DB::getInstance($this->config);
        // setAllowOrigin(array("*"));
        init();
        $this->parameters = getParameter($_POST, $_GET);
        $this->userClass = $userClass;
        $this->memberClass = $memberClass;
    }

    public function ready($function)
    {
        DB::startTransaction();
        try {
            apiKeyChecking();
            $GLOBALS['currentUser'] = getCurrentUser($this->userClass);
            $GLOBALS['currentMember'] = getCurrentUser($this->memberClass);
            $this->response = generateBaseURL($this->classList, $this->parameters, array("userClass" => $this->userClass));
            $this->response = $function($this->config, $this->parameters, $this->response);
            $this->loginAPI();
            $this->onlineStoreAPI();
            $this->onlineStoreCMSAPI();
            $this->extraAPI();
            if ($this->response == null) throw new Exception("URL Not Found");
            DB::commit();
        } catch (BaseException $e) {
            DB::rollback();
            $this->response = new Response($e->code, $e->type, $e->getMessage());
        } catch (Exception $exception) {
            DB::rollback();
            writeLog($exception->getFile(), $exception->getMessage(), $exception->getLine());
            if ($exception->getMessage() == null || $exception->getMessage() == "") $response = new Response(-1, "Failed", "URL Not Found");
            else $this->response = new Response(-1, "Failed", $exception->getMessage());
        }
        echo $this->response->send_response();
        DB::close_mysqli_conn();
    }
    private function onlineStoreAPI()
    {
        switch ($this->parameters["ACTION"]) {
            case "self_get_shoppingCart":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $this->response = new Response(200, "Success", array("ShoppingCart" => getSelfShoppingCart()));
                break;
            case "self_get_orders":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $this->response = new Response(200, "Success", array("Orders" => getSelfOrders()));
                break;
            case "self_add_shoppingcart_item":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                if (!isset($this->parameters["productID"]))
                    throw new Exception("Product ID Cannot Be Empty");
                $shoppingCart = getSelfShoppingCart();
                ShoppingCartDetail::insert(array("shoppingCartID" => $shoppingCart->ID, "productID" => $this->parameters["productID"], "quantity" => $this->parameters["quantity"] ?? 1));
                $this->response = new Response(200, "Success", "");
                break;
            case "self_clean_shoppingcart":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $shoppingCart = getSelfShoppingCart();
                foreach($shoppingCart->children as $shoppingCartDetail){
                    $shoppingCartDetail->delete();
                }
                $this->response = new Response(200, "Success", "");
                break;
            case "self_confirm_order":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required"); 
                if (!isset($this->parameters["orders"]))
                    throw new Exception("Order Cannot Be Empty");
                $parameters["orders"]["status"] = "UNPAID";
                $this->parameters["orders"]["ID"] = Orders::insertOrderWithShoppingCart($this->parameters["orders"], getSelfShoppingCart());
                $this->response = new Response(200, "Success", array("Orders" => $this->parameters["orders"]));
                break;
            case "self_member_update":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required"); 
                $GLOBALS['currentMember']->update($this->parameters);
                $this->response = new Response(200, "Success", "");
                break;
            case "register_member":
                Member::insert($this->parameters);
                $this->response = new Response(200, "Success", "");
                break;
        }
    }

    private function onlineStoreCMSAPI()
    {
        switch ($this->parameters["ACTION"]) {
            case "cms_insert_orders":
                if ($GLOBALS['currentUser'] == null)
                    throw new Exception("User Login Required");
                Orders::insertOrder($this->parameters);
                $this->response = new Response(200, "Success", "");
                break;
            case "cms_update_orders":
                if ($GLOBALS['currentUser'] == null)
                    throw new Exception("User Login Required");
                $order = DB::getByID(Orders::class, $this->parameters["ID"], array("joinClass" => ["OrderDetail"]));
                $order->updateOrder($this->parameters);
                $this->response = new Response(200, "Success", "");
                break;
            case "cms_add_variant_product":
                if ($GLOBALS['currentUser'] == null)
                    throw new Exception("User Login Required");
                if(!isset($this->parameters["product"]))
                    throw new Exception("Product Cannot Be Empty");
                if(!isset($this->parameters["productGroupParameterList"]))
                    throw new Exception("Product Group Cannot Be Empty");
                $productID = Product::insert($this->parameters["product"]);
                foreach($this->parameters["productGroupParameterList"] as $productGroupParameter){
                    $productGroup = DB::getByID(ProductGroup::class, $productGroupParameter["ID"]);
                    $parameters = array(
                        "productIDList" => $productGroup->productIDList,
                        "optionList" => $productGroup->optionList
                    );
                    $parameters["productIDList"][] = $productID;
                    $parameters["optionList"][$productGroupParameter["index"]]->productIDList[] = $productID;
                    $productGroup->update($parameters);
                }
                $this->response = new Response(200, "Success", "");
                break;
        }
    }
    private function loginAPI()
    {
        switch ($this->parameters["ACTION"]) {
            case "user_login":
                if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                $this->response = new Response(200, "Success", Auth::login($this->userClass, $this->parameters['loginName'], $this->parameters['password']));
                break;
            case "user_logout":
                if (!isExistedNotNull($this->parameters, "token")) throw new Exception('Token does not existed');
                logOutRemoveToken($this->userClass, $this->parameters['token']);
                $this->response = new Response(200, "Success", "");
                break;
            case "member_login":
                if ($this->memberClass == null) throw new Exception('MemberShip Function Does Not Activated');
                switch($this->parameters['accountType']){
                    case "NORMAL":
                        if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                        if (!isExistedNotNull($this->parameters, "loginName")) throw new Exception('Login Name does not existed');
                        $this->response = new Response(200, "Success", Auth::login($this->memberClass, $this->parameters['loginName'], $this->parameters['password']));
                        break;
                    case "GOOGLE":
                        if(!isset($this->parameters["email"])) throw new Exception('Email does not existed');
                        $memberList = DB::getAll_new($this->memberClass, array("whereOperationType" => "AND", "whereOperation" => array(array("type" => "EQUAL", "key" => "accountType", "value" => "GOOGLE"), array("type" => "EQUAL", "key" => "email", "value" => $this->parameters["email"]))));
                        if(sizeof($memberList) == 0){
                            $memberID = $this->memberClass::insert($this->parameters);
                            $member = DB::getByID($this->memberClass, $memberID);
                        }else
                            $member = $memberList[0];
                        $token = addToken($member);
                        $this->response = new Response(200, "Success", array("user" => $member, "token" => $token));
                        break;
                }
                break;
            case "member_logout":
                if ($this->memberClass == null) throw new Exception('MemberShip Function Does Not Activated');
                if (!isExistedNotNull($this->parameters, "token")) throw new Exception('Token does not existed');
                logOutRemoveToken($this->memberClass, $this->parameters['token']);
                $this->response = new Response(200, "Success", "");
                break;
            case "member_self":
                $this->response = new Response(200, "Success", array("Member" => $GLOBALS['currentMember']));
                break;
        }
    }
    private function extraAPI()
    {
        switch ($this->parameters["ACTION"]) {
            case "upload_file":
                $file = $_FILES["file"];
                if ($file['size'] / 1024 / 1024 > 10) throw new Exception("File Size too large");
                $now = DateTime::createFromFormat('U.u', microtime(true));
                $fileName = $now->format("m_d_Y_H_i_s.u") . "_" .  rand(1, 999) . "_" .  $_FILES["file"]["name"];
                if (move_uploaded_file($_FILES["file"]["tmp_name"],  SITE_ROOT . '/static/img/' . $fileName))
                    $this->response = new Response(200, "Success", $fileName);
                else
                    throw new Exception("Create File Error");
                break;
            case "get_table_list":
                $this->response = new Response(200, "Success", DB::getTableList());
                break;
            case "create_table":
                if (!isset($parameters["tableName"]))
                    throw new Exception("tableName Cannot Be Empty");
                if (!isset($parameters["columnList"]))
                    throw new Exception("columnList Cannot Be Empty");
                DB::createTable($parameters["tableName"], map($parameters["columnList"], function ($column) {
                    return new DB_Column($column["name"], $column["type"], $column["isNullAble"], $column["defaultValue"]);
                }));
                $this->response = new Response(200, "Success", array());
                break;
        }
    }
}

function getSelfShoppingCart(){
    $shoppingCartList = DB::getAll_new(ShoppingCart::class, array(
        "joinClass" => ["ShoppingCartDetail", "Product"],
        "whereOperation" => [array("type" => "EQUAL", "key" => "memberID", "value" => $GLOBALS['currentMember']->ID)]
    ));
    if (sizeof($shoppingCartList) > 0)
        return $shoppingCartList[0];
    else {
        $id = DB::insert(array("memberID" => $GLOBALS['currentMember']->ID), ShoppingCart::class);
        return DB::getByID(ShoppingCart::class, $id);
    }
}

function getSelfOrders(){
    return DB::getAll_new(Orders::class, array(
        "joinClass" => ["OrderDetail", "Product"],
        "whereOperation" => [array("type" => "EQUAL", "key" => "memberID", "value" => $GLOBALS['currentMember']->ID)]
    ));
}

