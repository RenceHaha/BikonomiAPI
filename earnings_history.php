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

$query = "SELECT payment_tbl.payment_id, payment_tbl.rent_id, payment_tbl.amount_paid, payment_tbl.date FROM payment_tbl JOIN rental_tbl ON payment_tbl.rent_id = rental_tbl.rent_id JOIN bike_tbl ON bike_tbl.bike_id = rental_tbl.bike_id WHERE bike_tbl.account_id = ? ORDER BY date DESC ";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $data['account_id']);
$stmt->execute();
$result = $stmt->get_result();

$payment_history = [];
while ($row = $result->fetch_assoc()) {
    $payment_history[] = $row;
}
if(empty($payment_history)){
    echo json_encode(['success' => false, 'message' => 'No Data Found']);
    exit; 
}
echo json_encode(['success' => true, 'bike_locations' => $payment_history]);
exit;

?>