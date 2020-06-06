<?php
header('Content-Type: application/json');
require_once(dirname(__FILE__).'/classes/SIC.class.php');
try{
    $sic = new SIC;
} catch (Exception $e){
    echo json_encode(array(
        'error' => $e->getMessage()
    ));
    die();
}