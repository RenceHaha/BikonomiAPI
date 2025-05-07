<?php
header('Content-Type: application/json');

// Path to the predictions JSON file
$predictionsFile = __DIR__ . '/revenue_predictions.json';

try {
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
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error reading predictions file: ' . $e->getMessage()
    ]);
}
?>