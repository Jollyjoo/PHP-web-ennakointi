<?php
// get_maakunnat.php - Fetch active regions from database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once 'db.php';

try {
    // Fetch active regions where type = 'maakunta' and active = 1
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
        'regions' => array_merge([
            [
                'Maakunta_ID' => 'koko-häme',
                'Maakunta' => 'KOKO-HÄME',
                'stat_code' => 'ALL'
            ]
        ], $regions),
        'count' => count($regions) + 1
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Fallback to hardcoded values if database fails
    $fallback = [
        'success' => false,
        'error' => 'Database connection failed',
        'fallback' => true,
        'regions' => [
            ['Maakunta_ID' => 'koko-häme', 'Maakunta' => 'KOKO-HÄME', 'stat_code' => 'ALL'],
            ['Maakunta_ID' => '1', 'Maakunta' => 'Päijät-Häme', 'stat_code' => 'MK07'],
            ['Maakunta_ID' => '2', 'Maakunta' => 'Kanta-Häme', 'stat_code' => 'MK05']
        ]
    ];
    
    echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
}
?>