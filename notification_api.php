<?php

require_once 'dbcon.php';
global $conn;

header('Content-Type: application/json');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Read JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if($method !== 'POST'){
    echo json_encode(['success' => false,'message' => 'Invalid request method']);
    return; 
}

if(!isset($data['account_id'])){
    echo json_encode(['success' => false, 'message' => 'Missing Account ID']);
    return;
}

try{
    $sql = "SELECT * FROM notification_tbl WHERE account_id = ? AND is_deleted = 0 ORDER BY date_created DESC";
    $statement = $conn->prepare($sql);
    $statement->bind_param("i", $data['account_id']);
    $statement->execute();
    $result = $statement->get_result();
    
    $notifications = [];
    if($result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            $notifications[] = $row;
        } 
    }

    echo json_encode(['success' => true, 'notifications' => $notifications]);
}catch(Exception $e){
    echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
}finally{
    $conn->close();
}

?>