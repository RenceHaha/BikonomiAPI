<?php

require_once('dbcon.php');

header('Content-Type: application/json');
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success' => false,'message' => 'Invalid request method']);
    exit;
}

if(!isset($data['gps_serial'])){
    echo json_encode(['success' => false,'message' => 'GPS Serial is required']);
    exit;
}

$gps_serial = $data['gps_serial'];

$query = "SELECT * FROM bike_location WHERE bike_serial_gps = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $gps_serial);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $query = "UPDATE bike_location SET status = 'lost' WHERE bike_serial_gps = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $gps_serial);
    $stmt->execute();
    if($stmt->affected_rows > 0){
        echo json_encode(['success' => true,'message' => 'GPS status updated to lost successfuly']);
        exit;
    }else{
        echo json_encode(['success' => false,'message' => 'No changes made to GPS Serial']);
        exit;
    }
} else{
    echo json_encode(['success' => false,'message' => 'GPS Serial not found']);
}

?>