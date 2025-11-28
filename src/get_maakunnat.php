<?php
// get_maakunnat.php - Fetch active regions from database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Include database connection variables
    require_once 'db.php';
    
    // Create PDO connection (same pattern as other files)
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    
    // Simple query to fetch active regions
    $sql = "SELECT Maakunta_ID, Maakunta, stat_code 
            FROM Maakunnat 
            WHERE type = 'maakunta' AND active = 1 
            ORDER BY Maakunta ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add special "koko-häme" option at the beginning
    $response = [
        'success' => true,
        'debug_info' => [
            'query' => $sql,
            'raw_count' => count($regions)
        ],
        'regions' => array_merge([
            [
                'Maakunta_ID' => 'koko-häme',
                'Maakunta' => 'KOKO-HÄME',
                'stat_code' => 'ALL'
            ]
        ], array_map(function($region) {
            // Use Maakunta name as the value instead of ID for compatibility with haeMaakunnalla.php
            return [
                'Maakunta_ID' => $region['Maakunta'], // Send name as value
                'Maakunta' => $region['Maakunta'],    // Display name
                'stat_code' => $region['stat_code'],
                'original_id' => $region['Maakunta_ID'] // Keep original ID for reference
            ];
        }, $regions)),
        'count' => count($regions) + 1
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Database specific error
    http_response_code(200); // Don't send 500, send 200 with error info
    $error_response = [
        'success' => false,
        'error_type' => 'PDO_ERROR',
        'error' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'fallback' => true,
        'regions' => [
            ['Maakunta_ID' => 'koko-häme', 'Maakunta' => 'KOKO-HÄME', 'stat_code' => 'ALL'],
            ['Maakunta_ID' => 'Päijät-Häme', 'Maakunta' => 'Päijät-Häme', 'stat_code' => 'MK07'],
            ['Maakunta_ID' => 'Kanta-Häme', 'Maakunta' => 'Kanta-Häme', 'stat_code' => 'MK05']
        ]
    ];
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // General error
    http_response_code(200); // Don't send 500, send 200 with error info
    $error_response = [
        'success' => false,
        'error_type' => 'GENERAL_ERROR',
        'error' => $e->getMessage(),
        'fallback' => true,
        'regions' => [
            ['Maakunta_ID' => 'koko-häme', 'Maakunta' => 'KOKO-HÄME', 'stat_code' => 'ALL'],
            ['Maakunta_ID' => 'Päijät-Häme', 'Maakunta' => 'Päijät-Häme', 'stat_code' => 'MK07'],
            ['Maakunta_ID' => 'Kanta-Häme', 'Maakunta' => 'Kanta-Häme', 'stat_code' => 'MK05']
        ]
    ];
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}
?>