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
        $userID = DB::getByColumn(Token::class, 'token', getRequestToken())[0]->userID;
        DB::$_conn->where("ID", $userID);
        DB::$_conn->where($userClass::getSelfName() . "." . 'isDeleted', 0);
        $result = DB::$_conn->get($userClass::getSelfName(), null, null);
        return new $userClass($result[0], BaseModel::SYSTEM);
    }

    static function login($userClass, $loginName, $password)
    {
        $result = DB::getByWhereCondition(Staff::class, array("loginName" => $loginName, "password" => $password));
        DB::$_conn->where("loginName", $loginName);
        DB::$_conn->where("password", $password);
        $result = DB::$_conn->get($userClass::getSelfName(), null, null);
        if (sizeof($result) == 0) throw new Exception('Please enter correct user name or password');
        $user = new $userClass($result[0], BaseModel::SYSTEM);
        $token = addToken($user);
        return array("user" => $user, 'token' => $token);
    }
}
function addToken($user)
{
    $config = readConfig();
    $token = generateRandomString();
    if (isTokenMoreThanMaximum($user, $config)) {
        $user = removeToken($user);
    }
    DB::insert(array("token" => $token, "expiredDate" => time() + 604800, "userID" => $user->ID), Token::class);
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