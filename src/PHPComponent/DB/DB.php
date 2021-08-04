<?php
if(readConfig()->database_type == "MSSQL"){
    class DB extends DB_mssql{}
}else if(readConfig()->database_type == "MYSQL"){
    class DB extends DB_mysql{}
}else{
    class DB extends DB_mysql{}
}
