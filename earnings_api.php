<?php
include 'dbcon.php';

// Function to get daily earnings
function getDailyEarnings($date, $account_id) {
    global $conn;
    
    $query = "SELECT p.payment_id, p.amount_paid, p.date, r.rent_id, b.bike_id, b.bike_name, bt.bike_type_name, b.image_path, rt.rate_per_minute, r.start_time, r.end_time
              FROM payment_tbl p 
              JOIN rental_tbl r ON p.rent_id = r.rent_id 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              JOIN rate_tbl rt ON rt.rate_id = rt.rate_id
              JOIN bike_type_tbl bt ON b.bike_type_id = bt.bike_type_id 
              WHERE DATE(p.date) = ? AND b.account_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $date, $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totalIncome = 0;
    $earnings = [];
    
    while ($row = $result->fetch_assoc()) {
        $totalIncome += $row['amount_paid'];
        $earnings[] = [
            'bike_id' => $row['bike_id'],
            'bike_type' => $row['bike_type_name'],
            'bike_name' => $row['bike_name'],
            'rent_id' => $row['rent_id'],
            'image_path' => $row['image_path'],
            'rate_per_minute' => $row['rate_per_minute'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'amount' => $row['amount_paid']
        ];
    }
    
    return [
        'success' => true,
        'date' => $date,
        'total_income' => $totalIncome,
        'earnings' => $earnings
    ];
}

// Function to get rental income details
function getRentalIncome($rentalId, $account_id) {
    global $conn;
    
    $query = "SELECT r.rent_id, r.start_time, r.end_time, p.amount_paid 
              FROM rental_tbl r 
              LEFT JOIN payment_tbl p ON r.rent_id = p.rent_id 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              WHERE r.rent_id = ? AND b.account_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $rentalId, $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format times
        $startTime = date('h:i a', strtotime($row['start_time']));
        $endTime = $row['end_time'] ? date('h:i a', strtotime($row['end_time'])) : null;
        
        return [
            'success' => true,
            'bike' => [
                'rental_id' => $row['rent_id'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'amount' => $row['amount_paid'] ?? 0
            ]
        ];
    }
    
    return ['success' => false, 'message' => 'Rental not found'];
}

// Function to get weekly earnings
function getWeeklyEarnings($month, $year, $account_id) {
    global $conn;
    
    // Get weekly earnings and organize by month
    $query = "SELECT 
                MONTH(p.date) as month,
                YEAR(p.date) as year,
                WEEKOFYEAR(p.date) as week_num,
                MIN(DATE(p.date)) as week_start, 
                MAX(DATE(p.date)) as week_end, 
                SUM(p.amount_paid) as total 
              FROM payment_tbl p 
              JOIN rental_tbl r ON p.rent_id = r.rent_id 
              WHERE YEAR(p.date) = ? AND r.account_id = ? 
              GROUP BY MONTH(p.date), WEEKOFYEAR(p.date) 
              ORDER BY month ASC, week_num DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Organize data by month
    $monthlyData = [];
    
    while ($row = $result->fetch_assoc()) {
        $monthName = date('F', mktime(0, 0, 0, $row['month'], 1));
        $monthShort = date('M', mktime(0, 0, 0, $row['month'], 1));
        $year = $row['year'];
        // Format date range as MM.DD - MM.DD
        $weekStart = date('m.d', strtotime($row['week_start']));
        $weekEnd = date('m.d', strtotime($row['week_end']));
        $weekRange = $weekStart . ' - ' . $weekEnd;
        
        // Initialize month data if not set
        if (!isset($monthlyData[$row['month']])) {
            $monthlyData[$row['month']] = [
                'month' => $monthShort,
                'year' => $year,
                'amount' => 0,
                'Range' => []
            ];
        }
        
        // Add weekly data and accumulate monthly total
        $monthlyData[$row['month']]['amount'] += $row['total'];
        $monthlyData[$row['month']]['Range'][] = [
            'week' => $weekRange,
            'amount' => $row['total']
        ];
    }
    
    // Convert to desired format
    $earnings = [];
    foreach ($monthlyData as $month) {
        // Format amount with PHP currency format
        $earnings[] = [
            'Month' => $month['month'],
            'Year' => $month['year'],
            'amount' => $month['amount'],
            'Range' => $month['Range']
        ];
    }
    
    return [
        'success' => true,
        'earnings' => $earnings
    ];
}

// Function to get monthly earnings
function getMonthlyEarnings($year, $account_id) {
    global $conn;
    
    $query = "SELECT MONTH(p.date) as month, 
              SUM(p.amount_paid) as total 
              FROM payment_tbl p 
              JOIN rental_tbl r ON p.rent_id = r.rent_id 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              WHERE YEAR(p.date) = ? AND b.account_id = ? 
              GROUP BY MONTH(p.date) 
              ORDER BY month";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlyEarnings = [];
    
    while ($row = $result->fetch_assoc()) {
        $monthlyEarnings[] = [
            'month' => date('F', mktime(0, 0, 0, $row['month'], 1)),
            'amount' => $row['total']
        ];
    }
    
    return [
        'success' => true,
        'earnings' => $monthlyEarnings
    ];
}

// Function to get yearly earnings
function getYearlyEarnings($account_id) {
    global $conn;
    
    $query = "SELECT YEAR(p.date) as year, 
              SUM(p.amount_paid) as total 
              FROM payment_tbl p 
              JOIN rental_tbl r ON p.rent_id = r.rent_id 
              JOIN bike_tbl b ON r.bike_id = b.bike_id 
              WHERE b.account_id = ? 
              GROUP BY YEAR(p.date) 
              ORDER BY year DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $yearlyEarnings = [];
    
    while ($row = $result->fetch_assoc()) {
        $yearlyEarnings[] = [
            'year' => $row['year'],
            'amount' => $row['total']
        ];
    }
    
    return [
        'success' => true,
        'earnings' => $yearlyEarnings
    ];
}

// Function to get earnings summary
function getEarningsSummary($account_id, $date = null) {
    global $conn;
    
    // If no date provided, use current date
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Get date info
    $targetDate = new DateTime($date);
    $targetYear = $targetDate->format('Y');
    $targetMonth = $targetDate->format('m');
    $targetDay = $targetDate->format('d');
    
    // Calculate week start (Sunday) and end (Saturday) dates
    $dayOfWeek = $targetDate->format('w'); // 0 (Sunday) through 6 (Saturday)
    $weekStart = clone $targetDate;
    $weekStart->modify("-{$dayOfWeek} day"); // Go back to Sunday
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 day'); // Go forward to Saturday
    
    // Get total (all-time) earnings
    $totalQuery = "SELECT SUM(p.amount_paid) as total FROM payment_tbl p JOIN rental_tbl r ON p.rent_id = r.rent_id JOIN bike_tbl b ON r.bike_id = b.bike_id WHERE b.account_id = ?";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->bind_param("i", $account_id);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $totalEarnings = $totalRow['total'] ?? 0;
    
    // Get year earnings
    $yearQuery = "SELECT SUM(p.amount_paid) as total FROM payment_tbl p JOIN rental_tbl r ON p.rent_id = r.rent_id JOIN bike_tbl b ON r.bike_id = b.bike_id WHERE YEAR(p.date) = ? AND b.account_id = ?";
    $yearStmt = $conn->prepare($yearQuery);
    $yearStmt->bind_param("ii", $targetYear, $account_id);
    $yearStmt->execute();
    $yearResult = $yearStmt->get_result();
    $yearRow = $yearResult->fetch_assoc();
    $yearEarnings = $yearRow['total'] ?? 0;
    
    // Get month earnings
    $monthQuery = "SELECT SUM(p.amount_paid) as total FROM payment_tbl p JOIN rental_tbl r ON p.rent_id = r.rent_id JOIN bike_tbl b ON r.bike_id = b.bike_id WHERE YEAR(p.date) = ? AND MONTH(p.date) = ? AND b.account_id = ?";
    $monthStmt = $conn->prepare($monthQuery);
    $monthStmt->bind_param("iii", $targetYear, $targetMonth, $account_id);
    $monthStmt->execute();
    $monthResult = $monthStmt->get_result();
    $monthRow = $monthResult->fetch_assoc();
    $monthEarnings = $monthRow['total'] ?? 0;
    
    // Get week earnings (Sunday to Saturday)
    $weekQuery = "SELECT SUM(p.amount_paid) as total FROM payment_tbl p JOIN rental_tbl r ON p.rent_id = r.rent_id JOIN bike_tbl b ON r.bike_id = b.bike_id WHERE DATE(p.date) BETWEEN ? AND ? AND b.account_id = ?";
    $weekStmt = $conn->prepare($weekQuery);
    $weekStartStr = $weekStart->format('Y-m-d');
    $weekEndStr = $weekEnd->format('Y-m-d');
    $weekStmt->bind_param("ssi", $weekStartStr, $weekEndStr, $account_id);
    $weekStmt->execute();
    $weekResult = $weekStmt->get_result();
    $weekRow = $weekResult->fetch_assoc();
    $weekEarnings = $weekRow['total'] ?? 0;
    
    // Get day earnings
    $dayQuery = "SELECT SUM(p.amount_paid) as total FROM payment_tbl p JOIN rental_tbl r ON p.rent_id = r.rent_id JOIN bike_tbl b ON r.bike_id = b.bike_id WHERE DATE(p.date) = ? AND b.account_id = ?";
    $dayStmt = $conn->prepare($dayQuery);
    $dayStmt->bind_param("si", $date, $account_id);
    $dayStmt->execute();
    $dayResult = $dayStmt->get_result();
    $dayRow = $dayResult->fetch_assoc();
    $dayEarnings = $dayRow['total'] ?? 0;
    
    return [
        'success' => true,
        'date' => $date,
        'total_earnings' => $totalEarnings,
        'year_earnings' => $yearEarnings,
        'month_earnings' => $monthEarnings,
        'week_earnings' => $weekEarnings,
        'day_earnings' => $dayEarnings,
        'week_start' => $weekStartStr,
        'week_end' => $weekEndStr
    ];
}

// API Endpoint Handler
header('Content-Type: application/json');

// Handle POST requests
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
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'daily':
                if(!isset($data['date'])) {
                    echo json_encode(['success' => false, 'message' => 'Date parameter required']);
                    exit;
                } else {
                    echo json_encode(getDailyEarnings($data['date'], $data['account_id']));
                }
                break;
                
            case 'rental':
                if (isset($data['rental_id'])) {
                    echo json_encode(getRentalIncome($data['rental_id'], $data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Rental ID parameter required']);
                }
                break;
                
            case 'weekly':
                if (isset($data['year'])) {
                    $month = isset($data['month']) ? $data['month'] : null;
                    echo json_encode(getWeeklyEarnings($month, $data['year'], $data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Year parameter required']);
                }
                break;
                
            case 'monthly':
                if (isset($data['year'])) {
                    echo json_encode(getMonthlyEarnings($data['year'], $data['account_id']));
                } else {
                    echo json_encode(['success' => false, 'message' => 'Year parameter required']);
                }
                break;
                
            case 'yearly':
                echo json_encode(getYearlyEarnings($data['account_id']));
                break;
                
            case 'summary':
                $date = isset($data['date']) ? $data['date'] : null;
                echo json_encode(getEarningsSummary($data['account_id'], $date));
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Action parameter required']);
    }
}
?>