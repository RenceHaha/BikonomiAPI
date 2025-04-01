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
        case 'fetchAll':
            fetchBikes($data);
            break;
        case 'fetchBikeInfo':
            fetchBikeInfo($data);
            break;
        case 'fetchCount' :
            fetchBikeCount($data);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function addBike($data) {
    global $conn;

    // Check for required parameters
    if (!isset($data['type'], $data['color'], $data['brand'], $data['accessories'], $data['name'], $data['gps_serial'], $data['account_id'], $data['rate_per_minute'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    // Other parameters
    $color = $data['color'];
    $bikeType = $data['type']; // This is the bike type
    $brand = $data['brand'];
    $name = $data['name'];
    $gps_serial = $data['gps_serial'];
    $account_id = $data['account_id'];
    $accessories = $data['accessories'];
    $rate_per_minute = $data['rate_per_minute'];
    $imagePath = null; // Initialize the variable

    // Check if image is provided in base64 format
    if (isset($data['image'])) {
        $base64String = $data['image'];
        // Check if the base64 string is valid
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $base64String = substr($base64String, strpos($base64String, ',') + 1);
            $imageType = strtolower($type[1]); // This is the image type (jpg, png, gif)

            // Decode the base64 string
            $base64String = base64_decode($base64String);
            if ($base64String === false) {
                echo json_encode(['success' => false, 'message' => 'Base64 decode failed.']);
                return;
            }

            // Set the target directory and file name
            $targetDir = "images/"; // Adjust this path as needed
            $fileName = uniqid() . '.' . $imageType; // Generate a unique file name with the correct image type
            $targetFile = $targetDir . $fileName;

            // Save the image to the target directory
            if (file_put_contents($targetFile, $base64String) !== false) {
                $imagePath = $targetFile; // Set the path if the upload is successful
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save the image.']);
                return;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid base64 image format.']);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No image provided.']);
        return;
    }

    try {
        $conn->begin_transaction();
        
        // Check if the bike type exists
        $sql = "SELECT bike_type_id FROM bike_type_tbl WHERE bike_type_name = ?";
        $statement = $conn->prepare($sql);
        $statement->bind_param("s", $bikeType); // Use bikeType instead of type
        $statement->execute();
        $result = $statement->get_result();

        if ($result->num_rows > 0) {
            // If the bike type is found
            $row = $result->fetch_assoc();
            $typeId = $row['bike_type_id'];

            // Insert into bike_tbl
            $insertBikeSql = "INSERT INTO bike_tbl(bike_type_id, account_id, bike_name, bike_color, bike_accessories, bike_brand, bike_serial_gps, image_path) 
                              VALUES(?, ?, ?, ?, ?, ?, ?, ?)";
            if (insertBike($insertBikeSql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imagePath)) {
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
            $statement->bind_param("s", $bikeType); // Use bikeType instead of type

            if ($statement->execute()) {
                // Get the newly inserted typeId
                $typeId = $conn->insert_id;

                // Insert into bike_tbl
                $insertBikeSql = "INSERT INTO bike_tbl(bike_type_id, account_id, bike_name, bike_color, bike_accessories, bike_brand, bike_serial_gps, image_path) 
                                  VALUES(?, ?, ?, ?, ?, ?, ?, ?)";
                if (insertBike($insertBikeSql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imagePath)) {
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
function insertBike($sql, $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imagePath) {
    global $conn;
    $statement = $conn->prepare($sql);
    $statement->bind_param("iissssss", $typeId, $account_id, $name, $color, $accessories, $brand, $gps_serial, $imagePath);
    
    $result = $statement->execute();
    $statement->close();
    return $result;
}

function fetchBikes($data){
    global $conn;

    if(!isset($data['account_id'])){ 
        echo json_encode(["success" => false, "message" => "Account ID Missing"]);
        return;
    }

    $account_id = $data['account_id'];

    $sql = "SELECT * FROM bike_tbl LEFT JOIN rate_tbl ON bike_tbl.bike_id = rate_tbl.bike_id LEFT JOIN bike_type_tbl ON bike_tbl.bike_type_id = bike_type_tbl.bike_type_id WHERE account_id = ?";
    $statement = $conn->prepare($sql);
    $statement->bind_param("i", $account_id);
    $statement->execute();
    $result = $statement->get_result();
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

function fetchBikeInfo($data){
    global $conn;

    if(!isset($data['account_id'], $data['bike_id'])){
        echo json_encode(["success" => false, "message" => "Missing Parameters"]);
        return;
    }

    $account_id = $data['account_id'];
    $bike_id = $data['bike_id'];

    $sql = "SELECT * FROM bike_tbl LEFT JOIN bike_type_tbl ON bike_tbl.bike_type_id = bike_type_tbl.bike_type_id LEFT JOIN rate_tbl ON bike_tbl.bike_id = rate_tbl.bike_id WHERE bike_tbl.bike_id = ? AND account_id = ?";
    $statement = $conn->prepare($sql);
    $statement->bind_param("ii", $bike_id, $account_id);
    $statement->execute();
    $result = $statement->get_result();
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

function fetchBikeCount($data){
    global $conn;

    if(!isset($data['account_id'])){
        echo json_encode(["success" => false, "message" => "Missing Account ID"]);
        return;
    }

    $account_id = $data['account_id'];

    $sql = "SELECT 
                account_id,
                TotalBikes, 
                Rented, 
                TotalBikes - Rented AS Available
            FROM (
                SELECT 
                    bt.account_id,
                    (SELECT COUNT(DISTINCT(bt_inner.bike_id)) 
                    FROM bike_tbl bt_inner 
                    WHERE bt_inner.account_id = bt.account_id) AS TotalBikes,
                    
                    (SELECT COUNT(DISTINCT(bt_inner.bike_id)) 
                    FROM rental_tbl rt 
                    LEFT JOIN bike_tbl bt_inner ON rt.bike_id = bt_inner.bike_id 
                    WHERE rt.end_time IS NULL 
                    AND bt_inner.account_id = bt.account_id) AS Rented
                FROM bike_tbl bt WHERE bt.account_id = ?
                GROUP BY bt.account_id
            ) AS bike_count";
    $statement = $conn->prepare($sql);
    $statement->bind_param("i", $account_id);
    $statement->execute();
    $result = $statement->get_result();
    if ($result->num_rows > 0) {
        $count = array();
        while ($row = $result->fetch_assoc()) {
            $count[] = $row;
        }
        echo json_encode(["success" => true, "counts" => $count]);
    } else {
        echo json_encode(["success" => false, "message" => "No bikes found"]);
    }

    $conn->close();
}
?>
