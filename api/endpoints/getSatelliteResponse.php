<?php
require_once('../init.php');

// getting data sent via json POST to this file
$data = json_decode(file_get_contents('php://input'), true);

if(is_array($data) AND array_key_exists('hash',$data)){
    $hash =  $data['hash'];
    $response = $sic->getSatelliteResponse($hash,$json=false);

    // save result to file
    if($sic->saveToCSV()){
        $response['history'] = $sic->getCsvSaveUrl($hash);
    }

    echo json_encode($response); 
}