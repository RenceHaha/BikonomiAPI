<?php
include 'dbcon.php';

header('Content-Type: application/json');

/**
 * Predict bike utilization rate for the next period
 * @param int $bike_id Bike ID
 * @param string $period Type of prediction (daily, weekly, monthly)
 * @param int $days_to_analyze Number of past days to analyze
 * @return array Prediction results
 */
function predictBikeUtilization($bike_id, $period = 'weekly', $days_to_analyze = 30) {
    global $conn;
    
    // Get historical rental data for this bike
    $query = "SELECT 
                DATE(r.start_time) as rental_date,
                COUNT(*) as rental_count,
                SUM(TIMESTAMPDIFF(HOUR, r.start_time, IFNULL(r.end_time, r.expected_end_time))) as total_hours
              FROM 
                rental_tbl r
              WHERE 
                r.bike_id = ?
                AND r.start_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY 
                DATE(r.start_time)
              ORDER BY 
                rental_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bike_id, $days_to_analyze);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process the data
    $dates = [];
    $utilization_rates = [];
    $total_days = 0;
    
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['rental_date'];
        // Calculate utilization as hours rented / 24 (max hours in a day)
        $utilization_rate = min(($row['total_hours'] / 24) * 100, 100); // Cap at 100%
        $utilization_rates[] = $utilization_rate;
        $total_days++;
    }
    
    // If we don't have enough data
    if ($total_days < 7) {
        return [
            'success' => false,
            'message' => 'Not enough historical data for prediction',
            'data_points' => $total_days
        ];
    }
    
    // Calculate average utilization rate
    $avg_utilization = array_sum($utilization_rates) / count($utilization_rates);
    
    // Calculate trend (simple moving average)
    $recent_days = min(7, $total_days);
    $recent_utilization = array_slice($utilization_rates, -$recent_days);
    $recent_avg = array_sum($recent_utilization) / count($recent_utilization);
    
    // Determine if utilization is trending up or down
    $trend_direction = ($recent_avg > $avg_utilization) ? 'up' : 'down';
    $trend_percentage = abs(($recent_avg - $avg_utilization) / $avg_utilization * 100);
    
    // Get bike details
    $bike_query = "SELECT bike_name, bike_type_id FROM bike_tbl WHERE bike_id = ?";
    $bike_stmt = $conn->prepare($bike_query);
    $bike_stmt->bind_param("i", $bike_id);
    $bike_stmt->execute();
    $bike_result = $bike_stmt->get_result();
    $bike_data = $bike_result->fetch_assoc();
    
    // Predict future utilization based on recent trend
    $prediction_factor = 1 + ($trend_direction == 'up' ? 0.05 : -0.05); // 5% adjustment
    $predicted_utilization = $recent_avg * $prediction_factor;
    
    // Calculate potential earnings based on average rental price
    $price_query = "SELECT AVG(p.amount_paid / TIMESTAMPDIFF(HOUR, r.start_time, IFNULL(r.end_time, r.expected_end_time))) as avg_hourly_rate
                    FROM payment_tbl p
                    JOIN rental_tbl r ON p.rent_id = r.rent_id
                    WHERE r.bike_id = ?
                    AND TIMESTAMPDIFF(HOUR, r.start_time, IFNULL(r.end_time, r.expected_end_time)) > 0";
    
    $price_stmt = $conn->prepare($price_query);
    $price_stmt->bind_param("i", $bike_id);
    $price_stmt->execute();
    $price_result = $price_stmt->get_result();
    $price_data = $price_result->fetch_assoc();
    
    $avg_hourly_rate = $price_data['avg_hourly_rate'] ?: 0;
    
    // Calculate potential daily earnings
    $daily_hours = ($predicted_utilization / 100) * 24;
    $potential_daily_earnings = $daily_hours * $avg_hourly_rate;
    
    // Format the response based on period
    $prediction = [];
    
    switch ($period) {
        case 'weekly':
            $prediction = [
                'predicted_weekly_utilization' => round($predicted_utilization, 2),
                'potential_weekly_earnings' => round($potential_daily_earnings * 7, 2),
                'predicted_weekly_hours' => round($daily_hours * 7, 2)
            ];
            break;
        case 'monthly':
            $prediction = [
                'predicted_monthly_utilization' => round($predicted_utilization, 2),
                'potential_monthly_earnings' => round($potential_daily_earnings * 30, 2),
                'predicted_monthly_hours' => round($daily_hours * 30, 2)
            ];
            break;
        default: // daily
            $prediction = [
                'predicted_daily_utilization' => round($predicted_utilization, 2),
                'potential_daily_earnings' => round($potential_daily_earnings, 2),
                'predicted_daily_hours' => round($daily_hours, 2)
            ];
    }
    
    return [
        'success' => true,
        'bike_id' => $bike_id,
        'bike_name' => $bike_data['bike_name'] ?? 'Unknown',
        'historical_data' => [
            'days_analyzed' => $total_days,
            'average_utilization' => round($avg_utilization, 2),
            'recent_utilization' => round($recent_avg, 2),
            'trend' => $trend_direction,
            'trend_percentage' => round($trend_percentage, 2),
            'avg_hourly_rate' => round($avg_hourly_rate, 2)
        ],
        'prediction' => $prediction
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
    
    // Check if bike_id is provided
    if (!isset($data['bike_id'])) {
        echo json_encode(['success' => false, 'message' => 'Bike ID is required']);
        exit;
    }
    
    // Set default values
    $period = isset($data['period']) ? $data['period'] : 'weekly';
    $days_to_analyze = isset($data['days_to_analyze']) ? intval($data['days_to_analyze']) : 30;
    
    // Get prediction
    $result = predictBikeUtilization($data['bike_id'], $period, $days_to_analyze);
    
    // Return the result
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported']);
}
?>