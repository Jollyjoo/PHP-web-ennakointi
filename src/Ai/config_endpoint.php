<?php
/**
 * Configuration Endpoint for Frontend
 * 
 * This endpoint returns OpenAI limits configuration as JSON for frontend consumption.
 * This ensures single-source configuration - JavaScript reads PHP configuration.
 * 
 * Usage: fetch('config_endpoint.php') returns JSON with all configuration values
 */

// Set JSON response header
header('Content-Type: application/json');

// Enable CORS if needed (for local development)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Include the centralized configuration
    require_once 'openai_limits_config.php';
    
    // Return configuration as JSON for frontend consumption
    $config = [
        'openai_analysis_limit' => OPENAI_ANALYSIS_LIMIT,
        'news_analysis_limit' => NEWS_ANALYSIS_LIMIT,
        'mediaseuranta_analysis_limit' => MEDIASEURANTA_ANALYSIS_LIMIT,
        'competitive_analysis_limit' => COMPETITIVE_ANALYSIS_LIMIT,
        'alerts_analysis_limit' => ALERTS_ANALYSIS_LIMIT,
        'cost_protection_message' => getCostProtectionMessage(),
        'development_status_message' => getDevelopmentStatusMessage(),
        'last_updated' => date('Y-m-d H:i:s'),
        'source' => 'openai_limits_config.php'
    ];
    
    // Add some helpful metadata
    $config['meta'] = [
        'description' => 'OpenAI API limits configuration - single source of truth',
        'usage' => 'This endpoint provides centralized configuration for both PHP and JavaScript',
        'benefit' => 'No duplicate configuration variables - always synchronized',
        'environment' => OPENAI_ANALYSIS_LIMIT === 1 ? 'development' : 'production'
    ];
    
    // Return as JSON
    echo json_encode($config, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Configuration loading failed',
        'message' => $e->getMessage(),
        'fallback_config' => [
            'openai_analysis_limit' => 1, // Safe fallback
            'cost_protection_message' => 'Configuration error - using safe fallback limit of 1',
            'development_status_message' => '🔧 FALLBACK MODE: 1 article limit',
            'environment' => 'fallback'
        ]
    ], JSON_PRETTY_PRINT);
}
?>