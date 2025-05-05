<?php

require_once('dbcon.php');

header('Content-Type: application/json');
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if(!isset($data['account_id'])){
    echo json_encode(['success' => false,'message' => 'Account ID is required']);
    exit;
}

$query = "SELECT longitude, latitude, bike_tbl.bike_id, bike_type_name, bike_name, rent_id, start_time, expected_end_time,
CASE 
    WHEN rental_tbl.end_time IS NULL 
        AND TIMESTAMPDIFF(SECOND, rental_tbl.start_time, NOW()) > TIME_TO_SEC(rental_tbl.time_limit) THEN 'Overdue'
    WHEN rental_tbl.end_time IS NULL THEN 'On Going'
END AS rental_status
FROM bike_location
JOIN bike_tbl ON bike_location.bike_serial_gps = bike_tbl.bike_serial_gps
JOIN bike_type_tbl ON bike_tbl.bike_type_id = bike_type_tbl.bike_type_id
JOIN rental_tbl ON rental_tbl.bike_id = bike_tbl.bike_id
WHERE bike_tbl.account_id = ? AND rental_tbl.end_time IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $data['account_id']);
$stmt->execute();
$result = $stmt->get_result();

$bike_locations = [];
while ($row = $result->fetch_assoc()) {
    $bike_locations[] = $row;
}
if(empty($locations)){
    echo json_encode(['success' => false, 'message' => 'No Bike with GPS Active Rental Found']);
    exit; 
}
echo json_encode(['success' => true, 'bike_locations' => $bike_locations]);
exit;

?>