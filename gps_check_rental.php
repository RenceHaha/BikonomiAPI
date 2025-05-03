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
    // Check if there is an active rental
    $rental_query = "SELECT start_time, expected_end_time FROM rental_tbl
    JOIN bike_tbl ON bike_tbl.bike_id = rental_tbl.bike_id WHERE bike_serial_gps = ? AND end_time IS NULL";
    $rental_stmt = $conn->prepare($rental_query);
    $rental_stmt->bind_param("s", $gps_serial);
    $rental_stmt->execute();
    $rental_result = $rental_stmt->get_result();

    if ($rental_result->num_rows > 0) {
        $rental_data = $rental_result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'message' => 'Rental found',
            'start_time' => $rental_data['start_time'],
            'expected_end_time' => $rental_data['expected_end_time']
        ]);
        exit;
    }
}

echo json_encode(['success' => false,'message' => 'No active rental found']);
exit;
?>