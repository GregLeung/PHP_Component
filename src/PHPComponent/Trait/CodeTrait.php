<?php
    trait CodeTrait {
        public static function checkNewCodeDuplicated($code, $customErrorMessage = "Code Duplicated"){
            $dataList = DB::getAll(static::class, BaseModel::PUBLIC);
            foreach($dataList as $data){
                if(strtolower($code) === strtolower($data->code))
                    throw new Exception($customErrorMessage);
            }
        }

        public function checkCodeDuplicated($code, $customErrorMessage = "Code Duplicated"){
            $dataList = DB::getAll(static::class, BaseModel::PUBLIC);
            foreach($dataList as $data){
                if($data->ID !== $this->ID && strtolower($code) === strtolower($data->code))
                    throw new Exception($customErrorMessage);
            }
        }
    }
    