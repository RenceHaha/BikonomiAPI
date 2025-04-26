<?php
include 'dbcon.php';

header('Content-Type: application/json');

/**
 * Simple linear regression to predict future earnings
 * @param array $data Array of [x, y] points where x is the time period and y is the earnings
 * @param int $futurePeriods Number of periods to predict into the future
 * @return array Predicted values
 */
function linearRegression($data, $futurePeriods = 7) {
    $n = count($data);
    
    if ($n < 2) {
        return ['error' => 'Not enough data points for prediction'];
    }
    
    // Calculate sums for the linear regression formula
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumXX = 0;
    
    foreach ($data as $point) {
        $x = $point[0];
        $y = $point[1];
        
        $sumX += $x;
        $sumY += $y;
        $sumXY += ($x * $y);
        $sumXX += ($x * $x);
    }
    
    // Calculate slope (m) and y-intercept (b) for the line y = mx + b
    $slope = (($n * $sumXY) - ($sumX * $sumY)) / (($n * $sumXX) - ($sumX * $sumX));
    $yIntercept = ($sumY - ($slope * $sumX)) / $n;
    
    // Get the last x value
    $lastX = $data[$n - 1][0];
    
    // Generate predictions for future periods
    $predictions = [];
    for ($i = 1; $i <= $futurePeriods; $i++) {
        $futureX = $lastX + $i;
        $predictedY = ($slope * $futureX) + $yIntercept;
        $predictions[] = [
            'period' => $futureX,
            'predicted_earnings' => max(0, $predictedY) // Ensure no negative predictions
        ];
    }
    
    return [
        'slope' => $slope,
        'intercept' => $yIntercept,
        'predictions' => $predictions
    ];
}

/**
 * Get historical daily earnings data for prediction
 * @param int $account_id Account ID
 * @param int $days Number of past days to analyze
 * @return array Daily earnings data
 */
function getHistoricalDailyData($account_id, $days = 30) {
    global $conn;
    
    $query = "SELECT 
                DATE(p.date) as day,
                SUM(p.amount_paid) as daily_total
              FROM payment_tbl p
              JOIN rental_tbl r ON p.rent_id = r.rent_id
              JOIN bike_tbl b ON r.bike_id = b.bike_id
              WHERE b.account_id = ?
              AND p.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY DATE(p.date)
              ORDER BY day ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $account_id, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $index = 1; // Start with day 1
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [$index, floatval($row['daily_total'])];
        $index++;
    }
    
    return $data;
}

/**
 * Get historical weekly earnings data for prediction
 * @param int $account_id Account ID
 * @param int $weeks Number of past weeks to analyze
 * @return array Weekly earnings data
 */
function getHistoricalWeeklyData($account_id, $weeks = 12) {
    global $conn;
    
    $query = "SELECT 
                YEARWEEK(p.date, 1) as week_num,
                MIN(DATE(p.date)) as week_start,
                SUM(p.amount_paid) as weekly_total
              FROM payment_tbl p
              JOIN rental_tbl r ON p.rent_id = r.rent_id
              JOIN bike_tbl b ON r.bike_id = b.bike_id
              WHERE b.account_id = ?
              AND p.date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
              GROUP BY YEARWEEK(p.date, 1)
              ORDER BY week_num ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $account_id, $weeks);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $index = 1; // Start with week 1
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [$index, floatval($row['weekly_total'])];
        $index++;
    }
    
    return $data;
}

/**
 * Get historical monthly earnings data for prediction
 * @param int $account_id Account ID
 * @param int $months Number of past months to analyze
 * @return array Monthly earnings data
 */
function getHistoricalMonthlyData($account_id, $months = 12) {
    global $conn;
    
    $query = "SELECT 
                YEAR(p.date) * 12 + MONTH(p.date) as month_num,
                MIN(DATE(p.date)) as month_start,
                SUM(p.amount_paid) as monthly_total
              FROM payment_tbl p
              JOIN rental_tbl r ON p.rent_id = r.rent_id
              JOIN bike_tbl b ON r.bike_id = b.bike_id
              WHERE b.account_id = ?
              AND p.date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
              GROUP BY YEAR(p.date), MONTH(p.date)
              ORDER BY month_num ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $account_id, $months);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $index = 1; // Start with month 1
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [$index, floatval($row['monthly_total'])];
        $index++;
    }
    
    return $data;
}

/**
 * Predict future earnings
 * @param int $account_id Account ID
 * @param string $period Type of prediction (daily, weekly, monthly)
 * @param int $historyLength Number of past periods to analyze
 * @param int $futurePeriods Number of future periods to predict
 * @return array Prediction results
 */
function predictEarnings($account_id, $period = 'weekly', $historyLength = 12, $futurePeriods = 7) {
    // Get historical data based on period type
    $historicalData = [];
    $periodLabel = '';
    
    switch ($period) {
        case 'daily':
            $historicalData = getHistoricalDailyData($account_id, $historyLength);
            $periodLabel = 'days';
            break;
        case 'weekly':
            $historicalData = getHistoricalWeeklyData($account_id, $historyLength);
            $periodLabel = 'weeks';
            break;
        case 'monthly':
            $historicalData = getHistoricalMonthlyData($account_id, $historyLength);
            $periodLabel = 'months';
            break;
        default:
            return ['success' => false, 'message' => 'Invalid period type'];
    }
    
    // Check if we have enough data
    if (count($historicalData) < 2) {
        return [
            'success' => false,
            'message' => 'Not enough historical data for prediction',
            'data_points' => count($historicalData)
        ];
    }
    
    // Perform linear regression
    $prediction = linearRegression($historicalData, $futurePeriods);
    
    // Format the response
    $formattedPredictions = [];
    foreach ($prediction['predictions'] as $pred) {
        $formattedPredictions[] = [
            'period' => $pred['period'],
            'label' => 'Period ' . $pred['period'],
            'predicted_earnings' => round($pred['predicted_earnings'], 2)
        ];
    }
    
    return [
        'success' => true,
        'period_type' => $period,
        'historical_data_points' => count($historicalData),
        'prediction_model' => [
            'type' => 'linear_regression',
            'slope' => $prediction['slope'],
            'intercept' => $prediction['intercept']
        ],
        'predictions' => $formattedPredictions,
        'total_predicted' => array_sum(array_column($prediction['predictions'], 'predicted_earnings'))
    ];
}

// API Endpoint Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Check if account_id is provided
    if (!isset($data['account_id'])) {
        echo json_encode(['success' => false, 'message' => 'Account ID is required']);
        exit;
    }
    
    // Set default values
    $period = isset($data['period']) ? $data['period'] : 'weekly';
    $historyLength = isset($data['history_length']) ? intval($data['history_length']) : 12;
    $futurePeriods = isset($data['future_periods']) ? intval($data['future_periods']) : 7;
    
    // Get prediction
    $result = predictEarnings($data['account_id'], $period, $historyLength, $futurePeriods);
    
    // Return the result
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported']);
}
?>