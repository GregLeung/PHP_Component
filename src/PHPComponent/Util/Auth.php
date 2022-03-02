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
        if (getRequestToken($userClass) == "" || getRequestToken($userClass) == null || getRequestToken($userClass) == "null")
            return null;
        if (sizeof(filter(DB::getByColumn($userClass::$tokenClass, "token", getRequestToken($userClass)), function($token){return  time() < $token->expiredDate;})) == 0)
            throw new BaseException("Token Invalid", -2);
        $token = DB::getByColumn($userClass::$tokenClass, "token", getRequestToken($userClass))[0];
        $token->expiredDate = time() + 6048000;
        DB::update(array("ID" => $token->ID, "expiredDate" => $token->expiredDate), $userClass::$tokenClass);
        return DB::getByID($userClass, $token->userID, array("fullRight" => true));
    }

    static function login($userClass, $loginName, $password, $expiredTime = 6048000, $callback = null)
    {
        $result = DB::getByWhereCondition($userClass, array("loginName" => $loginName, "password" => $password));
        if (sizeof($result) == 0) throw new Exception('Please enter correct user name or password');
        $user = $result[0];
        if($callback != null) $callback($user);
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
    DB::insert(array("token" => $token, "expiredDate" => time() + $expiredTime, "userID" => $user->ID), $user::$tokenClass);
    return $token;
}

function isTokenMoreThanMaximum($user, $config)
{
    return DB::getCount($user::$tokenClass, array("userID" => $user->ID)) >= $config->MaximumNumberOfToken;
}

function removeToken($user)
{
    $result = DB::getByColumn($user::$tokenClass, "userID", $user->ID)[0];
    DB::delete($result->ID, $user::$tokenClass);
    return $user;
}