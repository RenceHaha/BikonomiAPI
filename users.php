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
            addUser($data);
            break;
        case 'login':
            loginUser($data);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

// Function to add a new user
function addUser($data) {
    global $conn;

    if (!isset($data['username'], $data['password'], $data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $username = $data['username'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $email = $data['email'];

    try {
        $conn->begin_transaction();
        $sql = "INSERT INTO account_tbl (username, password, email) VALUES (?, ?, ?)";
        $statement = $conn->prepare($sql);
        $statement->bind_param("sss", $username, $password, $email);

        if ($statement->execute()) {
            echo json_encode(['success' => true, 'message' => 'User added successfully']);
            $conn->commit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert user']);
        }
        $statement->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        $conn->rollback();
    }
}

// Function to log in a user
function loginUser($data) {
    global $conn;

    if (!isset($data['username'], $data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $username = $data['username'];
    $password = $data['password'];

    $sql = "SELECT password, account_id FROM account_tbl WHERE username = ?";
    $statement = $conn->prepare($sql);
    $statement->bind_param("s", $username);
    $statement->execute();
    $statement->bind_result($hashedPassword, $account_id);
    
    if ($statement->fetch() && password_verify($password, $hashedPassword)) {
        echo json_encode(['success' => true, 'message' => 'Login successful', 'account_id' => $account_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }

    $statement->close();
}

function changePassword($data) {
    global $conn;

    if (!isset($data['account_id'], $data['password'], $data['new_password'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }

    $account_id = $data['account_id'];
    $password = $data['password'];
    $new_password = $data['new_password'];

    $sql = "SELECT password FROM account_tbl WHERE account_id = ?";
    $statement = $conn->prepare($sql);
    $statement->bind_param("i", $account_id);
    $statement->execute();
    $statement->bind_result($hashedPassword);
    
    if ($statement->fetch() && password_verify($password, $hashedPassword)) {
        $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        $sql = "UPDATE account_tbl SET password = ? WHERE account_id = ?";
        $statement = $conn->prepare($sql);
        $statement->bind_param("si", $new_hashed_password, $account_id);
        
        if ($statement->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect old password']);
    }

    $statement->close();
}

?>
