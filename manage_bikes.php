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
        case 'add':
            addBike($data);
            break;
        case 'getAll':
            getBikes($data);
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function addBike($data) {
    global $conn;

    if (!isset($data['type'], $data['color'], $data['brand'], $data['accessories'], $data['name'], $data['gps_serial'], $data['account_id'], $data['rate_per_minute'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $color = $data['color'];
    $type = $data['type'];
    $brand = $data['brand'];
    $name = $data['name'];
    $gps_serial = $data['gps_serial'];
    $account_id = $data['account_id'];
    $accessories = $data['accessories'];
    $rate_per_minute = $data['rate_per_minute'];
    $imageBase64 = $data['image']; // Base64-encoded image

// Decode the Base64 image into binary data
    $imageBlob = base64_decode($imageBase64);

    try {
        $conn->begin_transaction();
        
        // Check if the bike type exists
        $sql = "SELECT bike_type_id FROM bike_type_tbl WHERE bike_type_name = ?";
        $statement = $conn->prepare($sql);
        $statement->bind_param("s", $type);
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows > 0) {
            // If the bike type is found
            $row = $result->fetch_assoc();
            $typeId = $row['bike_type_id'];

            // Insert into bike_tbl
            $insertBikeSql = "INSERT INTO bike_tbl(bike_type_id, account_id, bike_name, bike_color, bike_accessories, bike_brand, bike_serial_gps, image_blob) 
                              VALUES(?, ?, ?, ?, ?, ?, ?, ?)";
            if (insertBike($insertBikeSql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imageBlob)) {
                // Get the inserted bike_id
                $bike_id = $conn->insert_id;

                // Insert the rate into the rate_tbl
                $insertRateSql = "INSERT INTO rate_tbl(bike_id, rate_per_minute, date_time) VALUES(?, ?, ?)";
                $statement = $conn->prepare($insertRateSql);
                $date_time = date('Y-m-d H:i:s'); // Current timestamp
                $statement->bind_param("ids", $bike_id, $rate_per_minute, $date_time);
                
                if ($statement->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Bike and rate added']);
                    $conn->commit();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to insert rate! ' . $conn->error]);
                    $conn->rollback();
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert bike! ' . $conn->error]);
                $conn->rollback();
            }
        } else {
            // If bike type is not found, insert the new bike type
            $insertTypeSql = "INSERT INTO bike_type_tbl(bike_type_name) VALUES(?)";
            $statement = $conn->prepare($insertTypeSql);
            $statement->bind_param("s", $type);

            if ($statement->execute()) {
                // Get the newly inserted typeId
                $typeId = $conn->insert_id;

                // Insert into bike_tbl
                $insertBikeSql = "INSERT INTO bike_tbl(bike_type_id, account_id, bike_name, bike_color, bike_accessories, bike_brand, bike_serial_gps, image_blob) 
                                  VALUES(?, ?, ?, ?, ?, ?, ?, ?)";
                if (insertBike($insertBikeSql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imageBlob)) {
                    // Get the inserted bike_id
                    $bike_id = $conn->insert_id;

                    // Insert the rate into the rate_tbl
                    $insertRateSql = "INSERT INTO rate_tbl(bike_id, rate_per_minute, date_time) VALUES(?, ?, ?)";
                    $statement = $conn->prepare($insertRateSql);
                    $date_time = date('Y-m-d H:i:s'); // Current timestamp
                    $statement->bind_param("ids", $bike_id, $rate_per_minute, $date_time);

                    if ($statement->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Bike and rate added']);
                        $conn->commit();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to insert rate! ' . $conn->error]);
                        $conn->rollback();
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to insert bike! ' . $conn->error]);
                    $conn->rollback();
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert bike type: ' . $conn->error]);
                $conn->rollback();
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        $conn->rollback();
    } finally {
        if (isset($statement)) {
            $statement->close();
        }
        $conn->close();
    }
}

// Function to insert the bike
function insertBike($sql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imageBlob) {
    global $conn;
    $statement = $conn->prepare($sql);
    $statement->bind_param("iissssss", $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imageBlob);
    
    $result = $statement->execute();
    $statement->close();
    return $result;
}

function getBikes(){
    global $conn;
    
    if(!isset($data['account_id'])){
        echo json_encode(["success" => false, "message" => "Account ID Missing"]);
        return;
    }

    $sql = "SELECT * FROM bikes_tbl";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $bikes = array();
        while ($row = $result->fetch_assoc()) {
            $bikes[] = $row;
        }
        echo json_encode(["success" => true, "bikes" => $bikes]);
    } else {
        echo json_encode(["success" => false, "message" => "No bikes found"]);
    }

    $conn->close();
}
?>
