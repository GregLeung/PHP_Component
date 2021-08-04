<?php
$database_type = property_exists(readConfig(), "database_type") ? readConfig()->database_type : "MYSQL";
if($database_type == "MSSQL"){
    class DB extends DB_mssql{}
}else if($database_type == "MYSQL"){
    class DB extends DB_mysql{}
}else{
    class DB extends DB_mysql{}
}
