<?php
// Set the timezone if not already set globally
date_default_timezone_set('Asia/Manila'); // Set to your server's timezone

require_once(__DIR__ . '/../../BikonomiAPI/dbcon.php');

global $conn;

if (!$conn || !$conn->ping()) {
    echo "Database connection failed. Exiting.\n";
    // Optionally add error logging here
    exit(1); // Exit with an error code
}

try {

    $sql = "SELECT
                r.rent_id,
                r.bike_id,
                r.expected_end_time,
                b.account_id, 
                b.bike_name,
                acc.email ,
                bike_serial_gps
            FROM
                rental_tbl r
            JOIN
                bike_tbl b ON r.bike_id = b.bike_id
            JOIN
                account_tbl acc ON b.account_id = acc.account_id 
            WHERE
                r.end_time IS NULL";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $notifications_sent = 0;
    while ($gps = $result->fetch_assoc()) {
        echo "Found lost gps " . $gps['bike_serial_gps'] . " for Bike ID " . $gps['bike_id'] . "\n";
        $isSent = false;
        $selectSql = "SELECT * FROM notification_tbl WHERE rent_id = ? AND tag = 'gps_lost'";
        
        $selectStmt = $conn->prepare($selectSql);
        if ($selectStmt) {
            $selectStmt->bind_param("i", $gps['rent_id']);
            $selectStmt->execute();
            $selectResult = $selectStmt->get_result(); 
        }
        if($selectResult->fetch_assoc()){
            echo "  -> Notification already sent for Rent ID ". $gps['rent_id']. "\n";
            $isSent = true;
        }
        if($isSent == false){
            // --- Send Notification Logic ---
            $insertSql = "INSERT INTO notification_tbl (account_id, rent_id, tag, title, message, date_created) VALUES (?, ?, ?, ?, ?, NOW())";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $title = "GPS Connection Lost";
                $tag = "gps_lost";
                
                $message = "Warning! The GPS tracker on ".$gps['bike_name']." has lost connection. Check the last known location now!";
                $insertStmt->bind_param("iisss", $gps['account_id'], $gps['rent_id'], $tag, $title, $message);
                if ($insertStmt->execute()) {
                    echo "  -> Notification sent for Account ID ". $gps['rent_id']. "\n";
                    $notifications_sent++;  
                }
            }
        }
    }

    $stmt->close();
    echo "Finished. Notifications sent: " . $notifications_sent . "\n";

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
    // Log the error details
    exit(1); // Exit with an error code
} finally {
    if ($conn) {
        $conn->close();
    }
}

exit(0); // Exit successfully
?>