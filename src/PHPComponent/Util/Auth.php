<?php
class Auth{
    static function checkAuth($permission_list){
        foreach(LoginUser::getCurrentType() as $auth){
            if(in_array($auth, $permission_list))
                return true;
        }
        throw new BaseException("Permission Denied", -3);
    }

    static function getLoginUser($userClass)
    {
        if (getRequestToken() == "" || getRequestToken() == null)
            return null;
        if (sizeof(filter(DB::getByColumn(Token::class, 'token', getRequestToken()), function($token){return  time() < $token->expiredDate;})) == 0)
            throw new BaseException("Token Invalid", -2);
        // $userID = DB::getByColumn(Token::class, 'token', getRequestToken())[0]->userID;
        $token = DB::getByColumn(Token::class, 'token', getRequestToken())[0];
        $token->expiredDate = time() + 6048000;
        DB::update(array("ID" => $token->ID, "expiredDate" => $token->expiredDate), Token::class);
        return DB::getByID($userClass, $token->userID, array("fullRight" => true));
    }

    static function login($userClass, $loginName, $password, $expiredTime = 6048000)
    {
        $result = DB::getByWhereCondition($userClass, array("loginName" => $loginName, "password" => $password));
        DB::$_conn->where("loginName", $loginName);
        DB::$_conn->where("password", $password);
        $result = DB::$_conn->get($userClass::getSelfName(), null, null);
        if (sizeof($result) == 0) throw new Exception('Please enter correct user name or password');
        $user = new $userClass($result[0]);
        $token = addToken($user, $expiredTime);
        return array("user" => $user, 'token' => $token);
    }
}
function addToken($user, $expiredTime = 6048000)
{
    $config = readConfig();
    $token = generateRandomString();
    if (isTokenMoreThanMaximum($user, $config)) {
        $user = removeToken($user);
    }
    DB::insert(array("token" => $token, "expiredDate" => time() + $expiredTime, "userID" => $user->ID), Token::class);
    return $token;
}

function isTokenMoreThanMaximum($user, $config)
{
    return DB::getCount(Token::class, array("userID" => $user->ID)) >= $config->MaximumNumberOfToken;
}

function removeToken($user)
{
    $result = DB::getByColumn(Token::class, "userID", $user->ID)[0];
    DB::delete($result->ID, Token::class);
    return $user;
}