<?php
require_once('dbcon.php');

// Function to check for bikes that are about to reach their expected end time
function checkRentalEndingSoon() {
    global $conn;
    
    // Get current time
    $current_time = date('Y-m-d H:i:s');
    
    // Query to find bikes that will reach their expected end time in the next 15 minutes
    $query = "SELECT r.rent_id, r.bike_id, r.expected_end_time, b.bike_name, b.account_id 
              FROM rental_tbl r 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              WHERE r.end_time IS NULL 
              AND r.expected_end_time > ?
              AND r.expected_end_time <= DATE_ADD(?, INTERVAL 15 MINUTE)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $current_time, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Calculate minutes left
        $end_time = new DateTime($row['expected_end_time']);
        $now = new DateTime();
        $interval = $now->diff($end_time);
        $minutes_left = ($interval->h * 60) + $interval->i;
        
        // Check if notification already exists for this rental
        $check_query = "SELECT notification_id FROM notification_tbl 
                        WHERE account_id = ? 
                        AND title = 'Rental Ending Soon'
                        AND message LIKE ? 
                        AND DATE(date_created) = CURRENT_DATE()";
        
        $message_pattern = "%{$row['bike_name']} has % minutes left%";
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $row['account_id'], $message_pattern);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Only insert notification if it doesn't already exist
        if ($check_result->num_rows == 0) {
            // Create notification for the bike owner
            $title = "Rental Ending Soon";
            $message = "{$row['bike_name']} has {$minutes_left} minutes left on their time. 🚲 ⏳ Keep an eye on their return status.";
            
            // Add notification
            $notification_query = "INSERT INTO notification_tbl (account_id, title, message, date_created, is_read) 
                                 VALUES (?, ?, ?, NOW(), 0)";
            $notification_stmt = $conn->prepare($notification_query);
            $notification_stmt->bind_param("iss", $row['account_id'], $title, $message);
            $notification_stmt->execute();
        }
    }
}

// Function to check for bikes that have reached their expected end time
function checkRentalEndTimes() {
    global $conn;
    
    // Get current time
    $current_time = date('Y-m-d H:i:s');
    
    // Query to find bikes that have reached their expected end time
    $query = "SELECT r.rent_id, r.bike_id, r.expected_end_time, b.bike_name, b.account_id 
              FROM rental_tbl r 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              WHERE r.end_time IS NULL 
              AND r.expected_end_time <= ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Check if notification already exists for this rental
        $check_query = "SELECT notification_id FROM notification_tbl 
                        WHERE account_id = ? 
                        AND title = 'Overdue Notification'
                        AND message LIKE ? 
                        AND DATE(date_created) = CURRENT_DATE()";
        
        $message_pattern = "%{$row['bike_name']} has exceeded their rental time%";
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $row['account_id'], $message_pattern);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        // Only insert notification if it doesn't already exist
        if ($check_result->num_rows == 0) {
            // Create notification for the bike owner
            $title = "Overdue Notification";
            $message = "{$row['bike_name']} has exceeded their rental time! ⏳ They may incur extra charges. Check their location and take necessary action. 🚲❗";
            
            // Add notification
            $notification_query = "INSERT INTO notification_tbl (account_id, title, message, date_created, is_read) 
                                 VALUES (?, ?, ?, NOW(), 0)";
            $notification_stmt = $conn->prepare($notification_query);
            $notification_stmt->bind_param("iss", $row['account_id'], $title, $message);
            $notification_stmt->execute();
        }
    }
}

// Run both checks
checkRentalEndingSoon();
checkRentalEndTimes();
?>