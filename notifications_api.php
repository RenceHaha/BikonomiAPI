<?php
include 'dbcon.php';
header('Content-Type: application/json');

// Function to get all notifications for a specific account
function getAllNotifications($account_id) {
    global $conn;
    
    $query = "SELECT * FROM notification_tbl WHERE account_id = ? ORDER BY date_created DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'notification_id' => $row['notification_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'date_created' => $row['date_created'],
            'is_read' => (bool)$row['is_read']
        ];
    }
    
    return [
        'success' => true,
        'notifications' => $notifications
    ];
}

// Function to get unread notifications for a specific account
function getUnreadNotifications($account_id) {
    global $conn;
    
    $query = "SELECT * FROM notification_tbl WHERE account_id = ? AND is_read = 0 ORDER BY date_created DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'notification_id' => $row['notification_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'date_created' => $row['date_created']
        ];
    }
    
    return [
        'success' => true,
        'notifications' => $notifications
    ];
}

// Function to mark notification as read
function markAsRead($notification_id, $account_id) {
    global $conn;
    
    $query = "UPDATE notification_tbl SET is_read = 1 WHERE notification_id = ? AND account_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $account_id);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Notification marked as read'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to mark notification as read'
        ];
    }
}

// Function to mark all notifications as read for a specific account
function markAllAsRead($account_id) {
    global $conn;
    
    $query = "UPDATE notification_tbl SET is_read = 1 WHERE account_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'All notifications marked as read'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to mark notifications as read'
        ];
    }
}

// Function to delete notification
function deleteNotification($notification_id, $account_id) {
    global $conn;
    
    $query = "DELETE FROM notification_tbl WHERE notification_id = ? AND account_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $account_id);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Notification deleted successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to delete notification'
        ];
    }
}

// Function to delete all notifications for a specific account
function deleteAllNotifications($account_id) {
    global $conn;
    
    $query = "DELETE FROM notification_tbl WHERE account_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'All notifications deleted successfully'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to delete notifications'
        ];
    }
}

// Function to add new notification
function addNotification($account_id, $title, $message) {
    global $conn;
    
    $query = "INSERT INTO notification_tbl (account_id, title, message, date_created, is_read) VALUES (?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $account_id, $title, $message);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Notification added successfully',
            'notification_id' => $conn->insert_id
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to add notification'
        ];
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'get_all':
                if (isset($data['account_id'])) {
                    echo json_encode(getAllNotifications($data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Account ID required']);
                }
                break;
                
            case 'get_unread':
                if (isset($data['account_id'])) {
                    echo json_encode(getUnreadNotifications($data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Account ID required']);
                }
                break;
                
            case 'mark_read':
                if (isset($data['notification_id'], $data['account_id'])) {
                    echo json_encode(markAsRead($data['notification_id'], $data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Notification ID and Account ID required']);
                }
                break;
                
            case 'mark_all_read':
                if (isset($data['account_id'])) {
                    echo json_encode(markAllAsRead($data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Account ID required']);
                }
                break;
                
            case 'delete':
                if (isset($data['notification_id'], $data['account_id'])) {
                    echo json_encode(deleteNotification($data['notification_id'], $data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Notification ID and Account ID required']);
                }
                break;
                
            case 'delete_all':
                if (isset($data['account_id'])) {
                    echo json_encode(deleteAllNotifications($data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Account ID required']);
                }
                break;
                
            case 'add':
                if (isset($data['account_id'], $data['title'], $data['message'])) {
                    echo json_encode(addNotification($data['account_id'], $data['title'], $data['message']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Account ID, title and message required']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Action parameter required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST method is supported']);
}
?> 