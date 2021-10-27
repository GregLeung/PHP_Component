<?php
class DB_Column
{
    public $name;
    public $type;
    public $isNullAble;
    public $defaultValue;
    

    function __construct($name, $db_columnType, $isNullAble = true, $defaultValue = null) {
        $this->name = $name;
        $this->isNullAble = $isNullAble;
        $this->defaultValue = $defaultValue;
        $this->type = $db_columnType;
    }

    function generateSql(){
        $sql = "`" . $this->name . "`";
        switch($this->type){
            case DB_ColumnType::VARCHAR;
                $sql .= " varchar(1024) ";
                break;
            case DB_ColumnType::INT;
                $sql .= " int(11) ";
                break;
            case DB_ColumnType::DECIMAL;
                $sql .= " decimal(11,2) ";
                break;
            case DB_ColumnType::BOOLEAN;
                $sql .= " tinyint(4) ";
                break;
            case DB_ColumnType::DATE;
                $sql .= " datetime ";
                break;
            case DB_ColumnType::ARRAY;
                $sql .= " varchar(2046) ";
                break;
            case DB_ColumnType::TEXT;
                $sql .= " text ";
                break;
        }
        if($this->isNullAble == false && $this->type != DB_ColumnType::ARRAY)
            $sql .= " NOT NULL ";
        if($this->type == DB_ColumnType::ARRAY){
            $sql .= " DEFAULT '[]'";
        }
        else if($this->defaultValue === null)
            $sql .= " DEFAULT NULL";
        else if($this->type == DB_ColumnType::VARCHAR || $this->type == DB_ColumnType::ARRAY){
            $sql .= " DEFAULT '" . $this->defaultValue . "'";
        }else{
            $sql .= " DEFAULT " . $this->defaultValue;
        }
        $sql .= ",";
        return $sql;
    }

}
abstract class DB_ColumnType{
    const VARCHAR = "VARCHAR";
    const INT = "INT";
    const DECIMAL = "DECMIAL";
    const BOOLEAN = "BOOLEAN";
    const DATE = "DATE";
    const ARRAY = "ARRAY";
    const TEXT = "TEXT";
}
?>