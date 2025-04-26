<?php 
require_once('../dbcon.php'); 

header('Content-Type: application/json');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Read JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

if (!$conn || !$conn->ping()) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
}

if ($method !== "POST"){
    echo json_encode(["success" => false, "message" => "invalid request method"]);
    return;
}

try {

    if(!isset($data['notification_id']) ){
        echo json_encode(['success' => false, 'message' => 'Missing Notification ID']);
        return;
    }

    if(!isset($data['account_id']) ){
        echo json_encode(['success' => false, 'message' => 'Missing Account ID']);
        return;
    }

    $sql = "UPDATE notification_tbl SET is_deleted = 1 WHERE notification_id = ? AND account_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("ii", $data['notification_id'], $data['account_id']);
    if($stmt->execute()){
        if($stmt->affected_rows > 0){
            echo json_encode(["success" => true, "message" => "Successfully updated is_deleted to 1 for notification id " . $data['notification_id']]);
        }else{
            echo json_encode(["success" => false, "message" => "No rows affected. Either it is deleted or notification_id:". $data['notification_id'] . " doesn't exist on this account."]);
        }
    }
    
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error Occured: " . $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}

?>