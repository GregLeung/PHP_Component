<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BaseExcel {
    public $currentPosition = array(1,1);
    private $sheet = null;
    private $spreadsheet = null;
    private $previousSetRowPosition = array(1,1);

    const FORMAT_GENERAL = 'General';
    const FORMAT_TEXT = '@';
    const FORMAT_NUMBER = '0';
    const FORMAT_NUMBER_00 = '0.00';
    const FORMAT_NUMBER_00_COMMA = '#,##0.00';
    const FORMAT_PERCENTAGE = '0%';
    const FORMAT_PERCENTAGE_00 = '0.00%';

    function __construct() {
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
    }

    function setCellValue($cell, $value){
        $this->sheet->setCellValue(self::convertNumberToLetter($cell[0]) . strval($cell[1]), $value);
        $this->currentPosition = $cell;
    }

    function setCellFormat($cell, $formatCode){
        if($formatCode != null){
            $this->sheet->getStyle(self::convertNumberToLetter($cell[0]) . strval($cell[1]))
                ->getNumberFormat()
                ->setFormatCode($formatCode);
            $this->currentPosition = $cell;
        }
    }

    function rawSetCellValue($cell, $value){
        $this->sheet->setCellValue($cell, $value);
    }

    function setCurrentPosition($position){
        $this->currentPosition = $position;
    }

    function checkOptionValid($valueList, $options){
        if($options != null){
            if(isset($options["formatList"]) && sizeof($options["formatList"]) != sizeof($valueList))
                throw new Exception("BaseExcel::FORMAT_LENGTH_ERROR");
        }
    }

    function setRowValue($valueList, $options = null){
        $this->checkOptionValid($valueList, $options);

        if(isset($options["currentPosition"]))
            $this->currentPosition = $options["currentPosition"];

        $this->previousSetRowPosition = $this->currentPosition;

        for($i = 0; $i < sizeof($valueList); $i++){
            $this->setCellValue($this->currentPosition, $valueList[$i]);
            if($options != null && sizeof($options['formatList']) > 0)
                $this->setCellFormat($this->currentPosition, $options['formatList'][$i]);
            $this->currentPosition[0] += 1;
        }
        $this->nextRow();
        
    }

    function setColumnValue($valueList, $options = null){
        $this->checkOptionValid($valueList, $options);

        if(isset($options["currentPosition"]))
            $this->currentPosition = $options["currentPosition"];

        $this->previousSetRowPosition = $this->currentPosition;
        
        for($i = 0; $i < sizeof($valueList); $i++){
            $this->setCellValue($this->currentPosition, $valueList[$i]);
            if($options != null && sizeof($options['formatList']) > 0)
                $this->setCellFormat($this->currentPosition, $options['formatList'][$i]);
            $this->currentPosition[1] += 1;
        }
        $this->nextColumn();
    }

    function nextRow(){
        $this->currentPosition[0] = $this->previousSetRowPosition[0];
        $this->currentPosition[1] += 1;
    }

    function nextColumn(){
        $this->currentPosition[0] += 1;
        $this->currentPosition[1] = $this->previousSetRowPosition[1];
    }

    public static function convertNumberToLetter($number){
        $number -= 1;
        for($r = ""; $number >= 0; $number = intval($number / 26) - 1)
            $r = chr($number%26 + 0x41) . $r;
        return $r;
    }

    public function setColumnWidth($columnIndex,$value, $unit){
        $this->sheet->getColumnDimension(self::convertNumberToLetter($columnIndex))->setWidth($value, $unit);
    }

    function setMultiColumnWidth($valueList){
        foreach ($valueList as $column => $width) {
            $this->sheet->getColumnDimension(self::convertNumberToLetter($column))->setWidth($width);
        }
    }

    function setMultiRowHeight($valueList){
        foreach ($valueList as $row => $height) {
            $this->sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    function save($path){
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);
    }
}