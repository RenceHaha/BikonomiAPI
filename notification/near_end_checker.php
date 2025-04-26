<?php
// Set the timezone if not already set globally
date_default_timezone_set('Asia/Manila'); // Set to your server's timezone

require_once('../dbcon.php'); // Include your database connection

// --- Configuration ---
$notification_threshold_minutes = 15; // Notify when there are 15 minutes or less before rental ends
$minimum_threshold_minutes = 0; // Don't notify if less than this many minutes remain
// --- End Configuration ---

echo "Running Near End Notification Checker at " . date('Y-m-d H:i:s') . "\n";

global $conn;

if (!$conn || !$conn->ping()) {
    echo "Database connection failed. Exiting.\n";
    // Optionally add error logging here
    exit(1); // Exit with an error code
}

try {
    // Find rentals that are ending soon and haven't had a notification sent yet
    $sql = "SELECT
                r.rent_id,
                r.bike_id,
                r.expected_end_time,
                b.account_id, -- Get owner's account ID from bike_tbl
                b.bike_name,
                acc.email -- Get owner's email from account_tbl (Example for email notification)
            FROM
                rental_tbl r
            JOIN
                bike_tbl b ON r.bike_id = b.bike_id
            JOIN
                account_tbl acc ON b.account_id = acc.account_id -- Join to get owner details
            WHERE
                r.end_time IS NULL 
            AND
                NOW() BETWEEN DATE_SUB(r.expected_end_time, INTERVAL ? MINUTE) 
                      AND DATE_SUB(r.expected_end_time, INTERVAL ? MINUTE)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("ii", $notification_threshold_minutes, $minimum_threshold_minutes);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications_sent = 0;
    while ($rental = $result->fetch_assoc()) {
        echo "Found rental ending soon: Rent ID " . $rental['rent_id'] . " for Bike ID " . $rental['bike_id'] . "\n";
        $isSent = false;
        $selectSql = "SELECT * FROM notification_tbl WHERE rent_id = ? AND tag = 'ending_soon'";
        
        $selectStmt = $conn->prepare($selectSql);
        if ($selectStmt) {
            $selectStmt->bind_param("i", $rental['rent_id']);
            $selectStmt->execute();
            $selectResult = $selectStmt->get_result(); 
        }
        if($selectResult->fetch_assoc()){
            echo "  -> Notification already sent for Rent ID ". $rental['rent_id']. "\n";
            $isSent = true;
        }
        if($isSent == false){
            // --- Send Notification Logic ---
            $insertSql = "INSERT INTO notification_tbl (account_id, rent_id, tag, title, message, date_created) VALUES (?, ?, ?, ?, ?, NOW())";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $title = "Rental Ending Soon";
                $tag = "ending_soon";
                $rentId = $rental['rent_id'];
                
                // Calculate the actual minutes remaining
                $currentTime = new DateTime();
                $endTime = new DateTime($rental['expected_end_time']);
                $minutesRemaining = ceil(($endTime->getTimestamp() - $currentTime->getTimestamp()) / 60);
                
                $message = $rental['bike_name'] . " has " . $minutesRemaining . " minutes left on their time. Keep an eye on their return status.";
                $insertStmt->bind_param("iisss", $rental['account_id'], $rentId, $tag, $title, $message);
                if ($insertStmt->execute()) {
                    echo "  -> Notification sent for Rent ID ". $rental['rent_id']. "\n";
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