<?php
header('Content-Type: application/json');

// Path to the predictions JSON file
$predictionsFile = __DIR__ . '/revenue_predictions.json';

// Check if predictions file exists and is not too old (e.g., older than 24 hours)
$needsUpdate = true;
if (file_exists($predictionsFile)) {
    $fileAge = time() - filemtime($predictionsFile);
    if ($fileAge < 86400) { // 86400 seconds = 24 hours
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

// Read and return the predictions
if (file_exists($predictionsFile)) {
    $predictions = file_get_contents($predictionsFile);
    $predictionsData = json_decode($predictions, true);
    
    echo json_encode([
        'success' => true,
        'data' => $predictionsData
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Predictions file not found'
    ]);
}
?>