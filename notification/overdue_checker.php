<?php
// Set the timezone if not already set globally
date_default_timezone_set('Asia/Manila'); // Set to your server's timezone

require_once('../dbcon.php'); // Include your database connection

// --- Configuration ---
$notification_threshold_minutes = 0; // Notify immediately when overdue (adjust as needed, e.g., 5 for 5 minutes past due)
// --- End Configuration ---

echo "Running Notification Checker at " . date('Y-m-d H:i:s') . "\n";

global $conn;

if (!$conn || !$conn->ping()) {
    echo "Database connection failed. Exiting.\n";
    // Optionally add error logging here
    exit(1); // Exit with an error code
}

// Add at the top of your script
$logFile = __DIR__ . '/overdue_log.txt';
file_put_contents($logFile, "Script started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Keep the rest of your script as is, but add logging at key points
try {
    // Find overdue rentals that haven't had a notification sent yet
    // Assumes you add a column like `overdue_notification_sent_at` (DATETIME NULL DEFAULT NULL) to rental_tbl
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
                NOW() >= DATE_ADD(r.expected_end_time, INTERVAL ? MINUTE)"; // Check if current time is past expected end + threshold

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("i", $notification_threshold_minutes);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications_sent = 0;
    while ($rental = $result->fetch_assoc()) {
        echo "Found overdue rental: Rent ID " . $rental['rent_id'] . " for Bike ID " . $rental['bike_id'] . "\n";
        $isSent = false;
        $selectSql = "SELECT * FROM notification_tbl WHERE rent_id = ? AND tag = 'overdue'";
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
                $title = "Overdue Notification";
                $tag = "overdue";
                $rentId = $rental['rent_id'];
                $message = $rental['bike_name']. " has exceeded their rental time! They may incur extra charges. Check their location and take necessary action.";
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

    // Add logging for important events
    file_put_contents($logFile, "Found {$result->num_rows} overdue rentals\n", FILE_APPEND);
    
} catch (Exception $e) {
    $errorMessage = "An error occurred: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    exit(1);
} finally {
    if ($conn) {
        $conn->close();
    }
    file_put_contents($logFile, "Script completed at " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
}

exit(0); // Exit successfully
?>