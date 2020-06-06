<?php
// NOTE: We do not use ../init.php here, because we want to check for an specific error code
header('Content-Type: application/json');
require_once(dirname(__FILE__).'/../classes/SIC.class.php');
$file_is_present = true;
try{
    $sic = new SIC;
} catch (Exception $e){
    // check if the specific error code (SIC-E-001) is present in exeption message
    if(str_replace('SIC-E-001','',$e->getMessage())){
        $file_is_present = false;
    }
}
echo json_encode($file_is_present);
