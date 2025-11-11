<?php
// Minimal version of news_intelligence_api.php for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Just check if we can even get to the basic structure
try {
    // Test config loading
    require_once 'config.php';
    
    // Skip database entirely for now
    $database_available = false;
    
    // Test OpenAI key function
    $openai_api_key = null;
    try {
        $openai_api_key = getOpenAIKey();
    } catch (Exception $e) {
        // Ignore OpenAI errors
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            $result = [
                'status' => 'Minimal API Working',
                'timestamp' => date('Y-m-d H:i:s'),
                'database_connected' => false,
                'openai_configured' => !empty($openai_api_key),
                'environment' => 'minimal'
            ];
            break;
            
            
        case 'alerts':
            // Return same format as the working version
            $result = [
                'status' => 'local_development',
                'message' => 'Demo data - minimal API version',
                'alerts' => [
                    [
                        'id' => 'demo_1',
                        'type' => 'trend',
                        'severity' => 'medium',
                        'title' => 'Demo: Minimal API Test',
                        'description' => 'Testing if basic structure works.',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'source' => 'minimal_demo'
                    ]
                ],
                'count' => 1,
                'environment' => 'minimal'
            ];
            break;
            
        default:
            $result = ['status' => 'Minimal AI News Intelligence System Active', 'timestamp' => date('Y-m-d H:i:s')];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'type' => 'Exception'], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'type' => 'Error'], JSON_UNESCAPED_UNICODE);
}
?>