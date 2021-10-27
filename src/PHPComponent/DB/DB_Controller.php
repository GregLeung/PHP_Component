<?php
    class DB_Controller{
        static function createTable($tableName, $db_columnList){
            $sql = "CREATE TABLE `" . $tableName . "` (
                `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `createdDate` datetime DEFAULT current_timestamp(),
                `modifiedDate` datetime DEFAULT current_timestamp(),
                `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
                `createdUserID` int(11) DEFAULT NULL,
                `modifiedUserID` int(11) DEFAULT NULL,";
            foreach($db_columnList as $column){
                $sql .= $column->generateSql();
            }
            $sql .= "PRIMARY KEY (`ID`) ";
            $sql .= ") ";
            $sql .= "ENGINE=InnoDB DEFAULT CHARSET=utf8";
            writeCustomLog($sql);
            return $sql;
        }
    }
?>