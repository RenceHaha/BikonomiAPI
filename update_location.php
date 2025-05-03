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

if(!isset($data['latitude'])){
    echo json_encode(['success' => false,'message' => 'Latitude is required']);
    exit;
}

if(!isset($data['longitude'])){
    echo json_encode(['success' => false,'message' => 'Longitude is required']);
    exit;
}

$gps_serial = $data['gps_serial'];
$latitude = $data['latitude'];
$longitude = $data['longitude'];

$query = "SELECT * FROM bike_location WHERE bike_serial_gps = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $gps_serial);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $query = "UPDATE bike_location SET latitude = ?, longitude = ? WHERE bike_serial_gps = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dds", $latitude, $longitude, $gps_serial);
    $stmt->execute();
    if($stmt->affected_rows > 0){
        // Check if the status is 'lost' and rental_tbl has a null end_time
        $status_query = "
            SELECT r.end_time 
            FROM bike_location bl
            JOIN bike_tbl b ON bl.bike_serial_gps = b.bike_serial_gps
            JOIN rental_tbl r ON b.bike_id = r.bike_id
            WHERE bl.bike_serial_gps = ? AND bl.status = 'lost' AND r.end_time IS NULL
        ";
        $status_stmt = $conn->prepare($status_query);
        $status_stmt->bind_param("s", $gps_serial);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();

        if ($status_result->num_rows > 0) {
            // Update status to 'active'
            $update_status_query = "UPDATE bike_location SET status = 'active' WHERE bike_serial_gps = ?";
            $status_stmt = $conn->prepare($update_status_query);
            $status_stmt->bind_param("s", $gps_serial);
            $status_stmt->execute();
        }

        echo json_encode(['success' => true,'message' => 'GPS Serial updated successfully']);
        exit;
    }else{
        echo json_encode(['success' => false,'message' => 'No changes made to GPS Serial']);
        exit;
    }
    exit;
} else {
    // GPS Serial not found
    // insert new gps serial
    $query = "INSERT INTO bike_location (bike_serial_gps, latitude, longitude) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdd", $gps_serial, $latitude, $longitude);
    $stmt->execute();
    if($stmt->affected_rows > 0){
        echo json_encode(['success' => true,'message' => 'GPS Serial inserted successfully']);
        exit; 
    }else{
        echo json_encode(['success' => false,'message' => 'Error inserting GPS Serial']);
        exit;
    }
    exit;
}



?>