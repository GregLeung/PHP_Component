<?php
class Auth
{
    static function checkToken($token)
    {
        $tokenClass = DB::getByColumn(Token::class, 'token', $token)[0];
        if (time() > $tokenClass->expiredDate) throw new Exception("Token Expired");
        return $tokenClass;
    }

    static function checkRole($user, $roles)
    {
        foreach ($roles as $role) {
            foreach ($user->type as $type) {
                if ($type == $role) return true;
            }
        }
        throw new Exception("User Role Invalid");
    }

    static function checkAuth($userClass,  $role = array())
    {
        $tokenClass = self::checkToken(getRequestToken());
        if (sizeof($role) > 0) {
            self::checkRole(DB::getByColumn($userClass::getSelfName(), 'ID', $tokenClass->userID)[0], $role);
        }
    }

    static function getLoginUser($userClass)
    {
        try {
            if (getRequestToken() == "" || getRequestToken() == null)
                return null;
            if (sizeof(filter(DB::getByColumn(Token::class, 'token', getRequestToken()), function($token){return  time() < $token->expiredDate;})) == 0)
                throw new Exception("Token invalid");
            $userID = DB::getByColumn(Token::class, 'token', getRequestToken())[0]->userID;
            DB::$_conn->where("ID", $userID);
            DB::$_conn->where($userClass::getSelfName() . "." . 'isDeleted', 0);
            $result = DB::$_conn->get($userClass::getSelfName(), null, null);
            if (method_exists($userClass, "loginCheckingHandling"))
                $result = $userClass::loginCheckingHandling($result);
            return new $userClass($result[0], BaseModel::SYSTEM);
        } catch (Exception $e) {
            return null;
        }
    }

    static function login($userClass, $loginName, $password)
    {
        $result = DB::getByWhereCondition($userClass, array("loginName" => $loginName, "password" => $password));
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