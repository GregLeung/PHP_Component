<?php
    require_once '../vendor/autoload.php';
    $response = new Response(200, "Success", "");
    DB::getInstance('localhost', 'root', '', 'cmab_event');
    echo "Fix Bug1";
    