<?php
class BaseSystem
{
    public $config;
    public $parameters;
    public $response;
    public $userClass;
    public $memberClass;
    public $classList;
    public $headers;
    function __construct($classList, $userClass, $memberClass = null)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(900);
        $this->config = readConfig();
        $this->classList = $classList;
        DB::getInstance($this->config);
        // setAllowOrigin(array("*"));
        init();
        $this->parameters = getParameter($_POST, $_GET);
        $this->userClass = $userClass;
        $this->memberClass = $memberClass;
        $this->headers = getallheaders();
    }

    public function ready($function)
    {
        DB::startTransaction();
        try {
            apiKeyChecking();
            $GLOBALS['currentUser'] = getCurrentUser($this->userClass);
            if(isset($this->parameters["checkUserRequired"]) && $this->parameters["checkUserRequired"])
                self::checkUserRequired();
            $GLOBALS['currentMember'] = ($this->memberClass != null)? getCurrentUser($this->memberClass): null;
            if(isset(getallheaders()["Sessionid"]))
                $GLOBALS['Sessionid'] = getallheaders()["Sessionid"];
            $this->response = generateBaseURL($this->classList, $this->parameters, array("userClass" => $this->userClass));
            $this->response = $function($this->config, $this->parameters, $this->response, $this->headers);
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
                $this->response = new Response(200, "Success", array("ShoppingCart" => getSelfShoppingCart($this->parameters)));
                break;
            case "self_get_orders":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $this->response = new Response(200, "Success", array("Orders" => getSelfOrders()));
                break;
            case "self_add_shoppingcart_item":
                if (!isset($this->parameters["productID"]))
                    throw new Exception("Product ID Cannot Be Empty");
                $shoppingCart = getSelfShoppingCart($this->parameters);
                $shoppingCartDetail = find($shoppingCart->children, function ($shoppingCartDetail) {
                    return ($shoppingCartDetail->product->ID == $this->parameters["productID"]);
                });
                if ($shoppingCartDetail == null)
                    ShoppingCartDetail::insert(array("shoppingCartID" => $shoppingCart->ID, "productID" => $this->parameters["productID"], "quantity" => $this->parameters["quantity"] ?? 1));
                else
                    $shoppingCartDetail->update(["quantity" => $shoppingCartDetail->quantity + $this->parameters["quantity"]]);
                $this->response = new Response(200, "Success", "");
                break;
            case "self_clean_shoppingcart":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $shoppingCart = getSelfShoppingCart($this->parameters);
                foreach ($shoppingCart->children as $shoppingCartDetail) {
                    $shoppingCartDetail->delete();
                }
                $this->response = new Response(200, "Success", "");
                break;
            case "self_confirm_order":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                if (!isset($this->parameters["orders"]))
                    throw new Exception("Order Cannot Be Empty");
                $orderID = Orders::insertOrder($this->parameters["orders"], $this->parameters["productList"]);
                $order = DB::getByID(Orders::class, $orderID, ["joinClass" => ["OrderDetail", "Product"]]);
                $order->update(["orderStatus" => "CONFIRMED", "children" => $order->children]);
                $order = DB::getByID(Orders::class, $orderID, ["joinClass" => ["OrderDetail", "Product", "Member"]]);
                $GLOBALS['temp_orderID'] = $order->ID;
                $mailJet = new BaseMailjet(getRenderedHTML(SITE_ROOT . "/Email/order_confirm_email_admin.php"));
                $mailJet->sendToAdmin("新訂單通知");
                $mailJet->setTemplate(getRenderedHTML(SITE_ROOT . "/Email/order_confirm_email_client.php"));
                $mailJet->sendToMember([$order->member], "新訂單通知");
                $this->response = new Response(200, "Success", array("Orders" => $order));
                break;
            case "self_draft_order":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                if (!isset($this->parameters["orders"]))
                    throw new Exception("Order Cannot Be Empty");
                $this->parameters["orders"]["orderStatus"] = "DRAFT";
                $this->parameters["orders"]["ID"] = Orders::insertOrder($this->parameters["orders"], $this->parameters["productList"]);
                $this->response = new Response(200, "Success", array("Orders" => $this->parameters["orders"]));
                break;
            case "self_member_update":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $GLOBALS['currentMember']->update($this->parameters);
                $this->response = new Response(200, "Success", "");
                break;
            case "register_member":
                // Member::insert($this->parameters);
                Auth::register(Member::class, $this->parameters, ["mobile", "email"]);
                $this->response = new Response(200, "Success", "");
                break;
            case "get_variant_product":
                if (!isset($this->parameters["productGroupIDList"]))
                    $this->response = new Response(200, "Success", array("Product" => []));
                else {
                    $productList = array();
                    $productIDList = array();
                    foreach ($this->parameters["productGroupIDList"] as $ID) {
                        foreach (DB::getByID(ProductGroup::class, $ID)->productIDList as $productID) {
                            if (!in_array($productID, $productIDList))
                                $productIDList[] = $productID;
                        }
                    }
                    foreach ($productIDList as $ID) {
                        $product = DB::getByID(Product::class, $ID, array("joinClass" => ["Inventory", "Warehouse", "Price", "ProductGroup"]));
                        if ($product != null)
                            $productList[] = $product;
                    }
                    $this->response = new Response(200, "Success", array("Product" => $productList));
                }
                break;
            case "get_discount_by_product":
                if (!isset($this->parameters["ID"]))
                    throw new Exception("Product ID Cannot Be Empty");
                $result = [];
                $discountList = DB::getAll_new(Discount::class, ["joinClass" => ["DiscountRule"], "whereOperation" => [
                    [
                        "key" => "isValid",
                        "value" => true,
                        "type" => "EQUAL"
                    ]
                ]]);
                foreach ($discountList as $discount) {
                    if (!$discount->isForever) {
                        $startTime = strtotime($discount->startDate);
                        $endTime = strtotime($discount->expiredDate);
                        $currentDate = strtotime(date("Y-m-d"));
                        if ($currentDate > $endTime ||   $currentDate < $startTime)
                            continue;
                    }
                    foreach ($discount->children as $rule) {
                        if ($rule->conditionType == "AMOUNT") {
                            $result[] = $discount;
                            break;
                        } else if ($rule->conditionType == "PRODUCT" && in_array($this->parameters["ID"], $rule->conditionProductIDList)) {
                            $result[] = $discount;
                            break;
                        }
                    }
                }
                $this->response = new Response(200, "Success", array("Discount" => $result));
                break;
            case "calculate_discount_amount":
                $this->response = new Response(200, "Success", calculateDiscountAmount($this->parameters));
                break;
            case "send_verification_code":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $member = DB::getByID(Member::class, $GLOBALS['currentMember']->ID);
                if($member->isValidated)
                    throw new Exception("Member Has Been Validated Already");
                $verifyCode = generateRandomNumber();
                $member->update(["verifyCode" => $verifyCode]);
                if($this->parameters["type"] == "MOBILE"){
                    $baseSMS = new BaseSMS();
                    $template = str_replace("{{verificationCode}}", $verifyCode, $baseSMS->verificationCodeTemplate);
                    Member::sendVerificationCode($member->mobile, $template);
                }else{
                    $GLOBALS['temp_memberID'] = $GLOBALS['currentMember']->ID;
                    $mailJet = new BaseMailjet(getRenderedHTML(SITE_ROOT . "/Email/verification_email.php"));
                    $mailJet->sendToMember([$member], "新訂單通知");
                }
                $this->response = new Response(200, "Success", "");
                break;
            case "verify_member":
                if ($GLOBALS['currentMember'] == null)
                    throw new Exception("Member Login Required");
                $member = DB::getByID(Member::class, $GLOBALS['currentMember']->ID);
                if($member->verifyCode != $this->parameters["verifyCode"])
                    throw new Exception("Invalid Verify Code");
                $member->update(["isValidated" => 1]);
                $this->response = new Response(200, "Success", "");
                break;
        }
    }

    private function onlineStoreCMSAPI()
    {
        switch ($this->parameters["ACTION"]) {
            // case "cms_insert_orders":
            //     if ($GLOBALS['currentUser'] == null)
            //         throw new Exception("User Login Required");
            //     Orders::insertOrder($this->parameters);
            //     $this->response = new Response(200, "Success", "");
            //     break;
            // case "cms_update_orders":
            //     if ($GLOBALS['currentUser'] == null)
            //         throw new Exception("User Login Required");
            //     $order = DB::getByID(Orders::class, $this->parameters["ID"], array("joinClass" => ["OrderDetail"]));
            //     $order->updateOrder($this->parameters);
            //     $this->response = new Response(200, "Success", "");
            //     break;
            case "cms_add_variant_product":
                if ($GLOBALS['currentUser'] == null)
                    throw new Exception("User Login Required");
                if (!isset($this->parameters["product"]))
                    throw new Exception("Product Cannot Be Empty");
                if (!isset($this->parameters["productGroupParameterList"]))
                    throw new Exception("Product Group Cannot Be Empty");
                $productID = Product::insert($this->parameters["product"]);
                foreach ($this->parameters["productGroupParameterList"] as $productGroupParameter) {
                    $productGroup = DB::getByID(ProductGroup::class, $productGroupParameter["ID"]);
                    $parameters = array(
                        "productIDList" => $productGroup->productIDList,
                        "optionList" => $productGroup->optionList
                    );
                    $parameters["productIDList"][] = $productID;
                    $parameters["optionList"][$productGroupParameter["index"]]->productIDList[] = $productID;
                    $productGroup->update($parameters);
                }
                $this->response = new Response(200, "Success", ["Product" => DB::getByID(Product::class, $productID, ["joinClass" => ["ProductGroup"]])]);
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
                switch ($this->parameters['accountType']) {
                    case "NORMAL":
                        if (!isExistedNotNull($this->parameters, "password")) throw new Exception('Password does not existed');
                        $result = Auth::passwordLogin($this->memberClass, ["email", "mobile"],$this->parameters['loginName'], $this->parameters['password']);
                        $member = $result["user"];
                        $member->afterLogin();
                        $this->response = new Response(200, "Success", $result);
                        break;
                    case "GOOGLE":
                        if (!isset($this->parameters["email"])) throw new Exception('Email does not existed');
                        $memberList = DB::getAll_new($this->memberClass, array("whereOperationType" => "AND", "whereOperation" => array(array("type" => "EQUAL", "key" => "accountType", "value" => "GOOGLE"), array("type" => "EQUAL", "key" => "email", "value" => $this->parameters["email"]))));
                        if (sizeof($memberList) == 0) {
                            $memberID = $this->memberClass::insert($this->parameters);
                            $member = DB::getByID($this->memberClass, $memberID);
                        } else
                            $member = $memberList[0];
                        if(!$member->isValidated)
                            $member->update(["isValidated" => true]);
                        $member->afterLogin();
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
    public static function checkUserRequired(){
        if($GLOBALS['currentUser'] == null)
            throw new BaseException("Token Invalid", -2);
    }
}

function calculateDiscountAmount($parameters)
{
    if (!isset($parameters["productList"]))
        throw new Exception("Product List Cannot Be Empty");
    $validDiscountRuleList = [];
    $validDiscountList = [];
    $originalTotalPrice = 0;
    foreach($parameters["productList"] as &$product){
        $originalProduct = DB::getByID(Product::class, $product["ID"]);
        $product["price"] = $originalProduct->price;
        $product["finalPrice"] = $originalProduct->price;
        $originalTotalPrice += $product["quantity"] * $originalProduct->price;
    }
    unset($product);
    $discountList = DB::getAll_new(Discount::class, ["joinClass" => ["DiscountRule"], "whereOperation" => [
        [
            "key" => "isValid",
            "value" => true,
            "type" => "EQUAL"
        ]
    ]]);
    foreach ($discountList as $discount) {
        if (!$discount->isForever) {
            $startTime = strtotime($discount->startDate);
            $endTime = strtotime($discount->expiredDate);
            $currentDate = strtotime(date("Y-m-d"));
            if ($currentDate > $endTime ||   $currentDate < $startTime)
                continue;
        }
        foreach ($discount->children as $rule) {
            if ($rule->conditionType == "AMOUNT" && $originalTotalPrice >= $rule->conditionAmount) {
                $validDiscountList[] = $discount;
                $validDiscountRuleList[] = $rule;
                break;
            }else if ($rule->conditionType == "PRODUCT") {
                foreach($parameters["productList"] as $product){
                    if(in_array($product["ID"], $rule->conditionProductIDList)){
                        if($product["quantity"] >= $rule->conditionQuantity){
                            $validDiscountList[] = $discount;
                            $validDiscountRuleList[] = $rule;
                            break;
                        }
                    }
                }
                break;
            }
        }
    }
    foreach(filter($validDiscountRuleList, function($rule){
        return $rule->discountType == DiscountType::PERCENTAGE;
    }) as $rule){
        if($rule->isApplyProduct){
            foreach($parameters["productList"] as &$product){
                if(in_array($product["ID"], $rule->productIDList))
                    $product["finalPrice"] = $product["finalPrice"] * (100 - $rule->amount)/100;
            }
        }
    }
    unset($product);
    foreach(filter($validDiscountRuleList, function($rule){
        return $rule->discountType == DiscountType::DISCOUNT_PRODUCT;
    }) as $rule){
        foreach($parameters["productList"] as &$product){
            if(in_array($product["ID"], $rule->productIDList) && $product["price"] >= $rule->amount)
                $product["finalPrice"] = $rule->amount;
        }
    }
    unset($product);
    foreach(filter($validDiscountRuleList, function($rule){
        return $rule->discountType == DiscountType::FIXED;
    }) as $rule){
        if($rule->isApplyProduct){
            foreach($parameters["productList"] as &$product){
                if(in_array($product["ID"], $rule->productIDList)){
                    $product["finalPrice"] = $product["finalPrice"] - $rule->amount;
                    if($product["finalPrice"] < 0)
                        $product["finalPrice"] = 0;
                }
            }
        }
    }
    unset($product);
    $deductAmount = 0;
    $finalPriceSum = 0;
    foreach($parameters["productList"] as &$product){
        $finalPriceSum += $product["finalPrice"] * $product["quantity"];
    }
    unset($product);
    //TODO Implement FREE_PRODUCT Logic
    foreach(filter($validDiscountRuleList, function($rule){
        return $rule->discountType == DiscountType::PERCENTAGE;
    }) as $rule){
        if(!$rule->isApplyProduct){
            $deductAmount += $finalPriceSum * $rule->amount / 100;
        }
    }
    foreach(filter($validDiscountRuleList, function($rule){
        return $rule->discountType == DiscountType::FIXED;
    }) as $rule){
        if(!$rule->isApplyProduct){
            $deductAmount += $rule->amount;
        }
    }
    return [
        "originalTotalPrice" => $originalTotalPrice,
        "totalPrice" => $finalPriceSum - $deductAmount,
        "discountList" => $validDiscountList,
        "productList" => $parameters["productList"]
    ];
}

function getSelfShoppingCart($parameters)
{
    $whereOperationList = [];
    if(isset($GLOBALS['currentMember']->ID))
        $whereOperationList[] = array("type" => "EQUAL", "key" => "memberID", "value" => $GLOBALS['currentMember']->ID);
    if(isset($GLOBALS['Sessionid']))
        $whereOperationList[] = array("type" => "EQUAL", "key" => "sessionID", "value" => $GLOBALS['Sessionid']);
    $shoppingCartList = DB::getAll_new(ShoppingCart::class, array(
        "joinClass" => ["ShoppingCartDetail", "Product", "ProductGroup"],
        "whereOperationType" => "OR",
        "whereOperation" => $whereOperationList
    ));
    if(sizeof($shoppingCartList) > 0){
        usort($shoppingCartList, function ($a, $b){
            try{
                return strtotime($a->lastUpdateDate) < strtotime($b->lastUpdateDate);
            }catch(Exception $e){
                return true;
            }
        });
        $shoppingCart = $shoppingCartList[0];
        $shoppingCart->cleanOldProduct();
        return $shoppingCart;
    }else{
        $id = ShoppingCart::insert(array("memberID" => isset($GLOBALS['currentMember']->ID) ? $GLOBALS['currentMember']->ID : null, "sessionID" => isset($GLOBALS['Sessionid']) ? $GLOBALS['Sessionid'] : null));
        return DB::getByID(ShoppingCart::class, $id);
    }
}


function getSelfOrders()
{
    return DB::getAll_new(Orders::class, array(
        "joinClass" => ["OrderDetail", "Product", "DeliveryMethod", "PaymentMethod"],
        "whereOperation" => [array("type" => "EQUAL", "key" => "memberID", "value" => $GLOBALS['currentMember']->ID)]
    ));
}

function getRenderedHTML($path)
{
    ob_start();
    include($path);
    $var=ob_get_contents(); 
    ob_end_clean();
    return $var;
}