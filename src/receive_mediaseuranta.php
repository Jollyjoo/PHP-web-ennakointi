<?php
// receive_mediaseuranta.php - Free endpoint to receive data from Azure SQL
header('Content-Type: application/json');

// Simple API key validation (change this secret key!)
$valid_api_key = 'your-secret-mediaseuranta-key-2025';
$provided_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';

if ($provided_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get data from POST request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data && $_POST) {
        // Fallback to form data if JSON fails
        $data = $_POST;
    }
    
    if (!$data) {
        throw new Exception('No data received');
    }
    
    // Log the received data for debugging
    error_log('Received from Azure: ' . print_r($data, true));
    
    // Include MySQL connection
    require_once 'db.php';
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    
    // Prepare insert statement - adjust fields based on your table structure
    $sql = "INSERT INTO Mediaseuranta (
        Maakunta_ID, Teema, uutisen_pvm, Uutinen, Url, Hankkeen_luokitus,
        ai_analysis_status, ai_relevance_score, ai_economic_impact,
        ai_employment_impact, ai_key_sectors, ai_sentiment, ai_crisis_probability
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $data['Maakunta_ID'] ?? null,
        $data['Teema'] ?? null,
        $data['uutisen_pvm'] ?? null,
        $data['Uutinen'] ?? null,
        $data['Url'] ?? null,
        $data['Hankkeen_luokitus'] ?? null,
        $data['ai_analysis_status'] ?? 'pending',
        $data['ai_relevance_score'] ?? null,
        $data['ai_economic_impact'] ?? null,
        $data['ai_employment_impact'] ?? null,
        $data['ai_key_sectors'] ?? null,
        $data['ai_sentiment'] ?? null,
        $data['ai_crisis_probability'] ?? null
    ]);
    
    $mysql_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Record successfully forwarded to MySQL',
        'mysql_id' => $mysql_id,
        'received_fields' => count($data)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('MySQL Error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    error_log('General Error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Processing error',
        'details' => $e->getMessage()
    ]);
}
?>