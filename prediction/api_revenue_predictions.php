<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once(__DIR__ . '/../../BikonomiAPI/dbcon.php');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== "GET") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// Path to the predictions JSON file
$predictionsFile = __DIR__ . '/revenue_predictions.json';

// Check if predictions file exists and is not too old
$needsUpdate = true;
if (file_exists($predictionsFile)) {
    $fileAge = time() - filemtime($predictionsFile);
    if ($fileAge < 86400) { // 24 hours
        $needsUpdate = false;
    }
}

// If we need fresh predictions, run the Python script
if ($needsUpdate) {
    $pythonPath = 'python';
    $scriptPath = __DIR__ . '/rev_prediction.py';
    
    exec("$pythonPath \"$scriptPath\" 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate predictions',
            'error' => implode("\n", $output)
        ]);
        exit;
    }
}

try {
    if (file_exists($predictionsFile)) {
        $predictions = file_get_contents($predictionsFile);
        $predictionsData = json_decode($predictions, true);
        
        // Get total revenue from database for comparison
        $sql = "SELECT SUM(amount_paid) as total_revenue FROM payment_tbl";
        $result = $conn->query($sql);
        $totalRevenue = $result->fetch_assoc()['total_revenue'];
        
        echo json_encode([
            'success' => true,
            'total_revenue' => round($totalRevenue, 2),
            'data' => $predictionsData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Predictions file not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>