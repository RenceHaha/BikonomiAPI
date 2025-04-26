<?php
include 'dbcon.php';

header('Content-Type: application/json');

/**
 * Predict peak rental times based on historical data
 * @param int $account_id Account ID to analyze
 * @param int $days_to_analyze Number of past days to analyze
 * @return array Prediction results with peak hours and days
 */
function predictPeakRentalTimes($account_id, $days_to_analyze = 30) {
    global $conn;
    
    // Get all bikes owned by this account
    $bikes_query = "SELECT bike_id FROM bike_tbl WHERE account_id = ?";
    $bikes_stmt = $conn->prepare($bikes_query);
    $bikes_stmt->bind_param("i", $account_id);
    $bikes_stmt->execute();
    $bikes_result = $bikes_stmt->get_result();
    
    $bike_ids = [];
    while ($row = $bikes_result->fetch_assoc()) {
        $bike_ids[] = $row['bike_id'];
    }
    
    if (empty($bike_ids)) {
        return [
            'success' => false,
            'message' => 'No bikes found for this account'
        ];
    }
    
    // Get hourly rental data
    $hourly_query = "SELECT 
                        HOUR(start_time) as hour_of_day,
                        COUNT(*) as rental_count
                     FROM rental_tbl
                     WHERE bike_id IN (" . implode(',', $bike_ids) . ")
                     AND start_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     GROUP BY HOUR(start_time)
                     ORDER BY rental_count DESC";
    
    $hourly_stmt = $conn->prepare($hourly_query);
    $hourly_stmt->bind_param("i", $days_to_analyze);
    $hourly_stmt->execute();
    $hourly_result = $hourly_stmt->get_result();
    
    $hourly_data = [];
    $total_rentals = 0;
    
    while ($row = $hourly_result->fetch_assoc()) {
        $hourly_data[] = [
            'hour' => $row['hour_of_day'],
            'count' => $row['rental_count'],
            'formatted_time' => sprintf('%02d:00 - %02d:00', $row['hour_of_day'], ($row['hour_of_day'] + 1) % 24)
        ];
        $total_rentals += $row['rental_count'];
    }
    
    // Get daily rental data
    $daily_query = "SELECT 
                      DAYOFWEEK(start_time) as day_of_week,
                      COUNT(*) as rental_count
                   FROM rental_tbl
                   WHERE bike_id IN (" . implode(',', $bike_ids) . ")
                   AND start_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   GROUP BY DAYOFWEEK(start_time)
                   ORDER BY rental_count DESC";
    
    $daily_stmt = $conn->prepare($daily_query);
    $daily_stmt->bind_param("i", $days_to_analyze);
    $daily_stmt->execute();
    $daily_result = $daily_stmt->get_result();
    
    $daily_data = [];
    $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    while ($row = $daily_result->fetch_assoc()) {
        $day_index = $row['day_of_week'] - 1; // MySQL DAYOFWEEK() returns 1 for Sunday, 2 for Monday, etc.
        $daily_data[] = [
            'day_index' => $day_index,
            'day_name' => $day_names[$day_index],
            'count' => $row['rental_count']
        ];
    }
    
    // Identify peak hours (top 3)
    $peak_hours = array_slice($hourly_data, 0, 3);
    
    // Identify peak days (top 2)
    $peak_days = array_slice($daily_data, 0, 2);
    
    // Calculate hourly distribution percentages
    foreach ($hourly_data as &$hour) {
        $hour['percentage'] = round(($hour['count'] / $total_rentals) * 100, 2);
    }
    
    // Calculate potential revenue increase if focusing on peak times
    $revenue_query = "SELECT 
                        AVG(p.amount_paid) as avg_rental_value
                      FROM payment_tbl p
                      JOIN rental_tbl r ON p.rent_id = r.rent_id
                      WHERE r.bike_id IN (" . implode(',', $bike_ids) . ")
                      AND r.start_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    
    $revenue_stmt = $conn->prepare($revenue_query);
    $revenue_stmt->bind_param("i", $days_to_analyze);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
    $revenue_data = $revenue_result->fetch_assoc();
    
    $avg_rental_value = $revenue_data['avg_rental_value'] ?: 0;
    
    // Calculate potential increase by ensuring availability during peak times
    $peak_hour_rentals = 0;
    foreach ($peak_hours as $hour) {
        $peak_hour_rentals += $hour['count'];
    }
    
    $peak_hour_percentage = ($peak_hour_rentals / $total_rentals) * 100;
    $potential_increase = $avg_rental_value * ($peak_hour_rentals * 0.2); // Assuming 20% more rentals during peak hours
    
    return [
        'success' => true,
        'account_id' => $account_id,
        'bikes_analyzed' => count($bike_ids),
        'days_analyzed' => $days_to_analyze,
        'total_rentals' => $total_rentals,
        'peak_hours' => $peak_hours,
        'peak_days' => $peak_days,
        'hourly_distribution' => $hourly_data,
        'daily_distribution' => $daily_data,
        'insights' => [
            'peak_hour_percentage' => round($peak_hour_percentage, 2),
            'avg_rental_value' => round($avg_rental_value, 2),
            'potential_revenue_increase' => round($potential_increase, 2),
            'recommendation' => "Ensure bikes are available during peak hours (" . 
                                implode(', ', array_map(function($h) { return $h['formatted_time']; }, $peak_hours)) . 
                                ") and peak days (" . 
                                implode(', ', array_map(function($d) { return $d['day_name']; }, $peak_days)) . 
                                ") to maximize earnings."
        ]
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
    $days_to_analyze = isset($data['days_to_analyze']) ? intval($data['days_to_analyze']) : 30;
    
    // Get prediction
    $result = predictPeakRentalTimes($data['account_id'], $days_to_analyze);
    
    // Return the result
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported']);
}
?>