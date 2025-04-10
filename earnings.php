<?php
require_once('dbcon.php');
header('Content-Type: application/json');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Read JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($method === 'POST' && isset($data['action'])) {
    switch ($data['action']) {
        case 'insertPayment':
            insertPayment($data);
            break;
        case 'temporary':
            temporary();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}


function insertPayment($data){

    global $conn;

    if(!isset($data['rent_id'])){
        echo json_encode(['success' => false, 'message' => 'Missing Rent ID']);
        return;
    }

    try{
        $sql = "INSERT INTO payment_tbl(rent_id, amount_paid, date) VALUES(?,?,?)";
        $statement = $conn->prepare($sql);
        $statement->bind_param("iss", $data['rent_id'], $data['amount_paid'], $data['date']);
        if($statement->execute()){
            echo json_encode(['success' => true, 'message' => 'successfully inserted into payment table']);
            $conn->commit();
        }else{
            echo json_encode(['success' => false, 'message' => 'Unknown error occured: having trouble inserting']);
            $conn->rollback();
        }
    }catch(Exception $e){
        echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
        $conn->rollback();
    }finally{
        $conn->close();
    }

}















function temporary(){

    global $conn;

    try{
        $sql = "SELECT SUM(amount_paid) AS daily FROM payment_tbl";
        $statement = $conn->prepare($sql);
        $statement->execute();
        $result = $statement->get_result();
        if ($result->num_rows > 0) {
            $earnings;
            if($row = $result->fetch_assoc()) {
                $earnings = $row;
            }
            echo json_encode(["success" => true, "earnings" => $earnings['daily']]);
        } else {
            echo json_encode(["success" => false, "message" => "No payment found"]);
        }
    }catch(Exception $e){
        echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
    }finally{
        $conn->close();
    }

}

?>