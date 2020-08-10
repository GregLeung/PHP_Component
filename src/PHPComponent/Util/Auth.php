<?php
    class Auth{
        static function checkToken($token){
            $tokenClass = DB::getByColumn(Token::class, 'token', $token)[0];
            if(time() > $tokenClass->expiredDate) throw new Exception("Token Expired");
            return $tokenClass;
        }

        static function checkRole($user, $roles){
            foreach($roles as $role){
                foreach($user->type as $type){
                    if($type == $role) return true;
                }
            }
            throw new Exception("User Role Invalid");
        }   

        static function checkAuth($userClass, $token, $role = array()){
            $tokenClass = self::checkToken($token);
            if(sizeof($role) > 0){
                self::checkRole(DB::getByColumn($userClass::getSelfName(), 'ID', $tokenClass->userID)[0], $role);
            }
        }
    }
?>