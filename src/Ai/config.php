<?php
/**
 * Secure Configuration for AI System
 * This file contains sensitive configuration data
 * DO NOT commit this file to version control
 */

// OpenAI API Configuration
function getOpenAIKey() {
    // Try environment variable first (most secure)
    $api_key = getenv('OPENAI_API_KEY');
    
    if ($api_key) {
        return $api_key;
    }
    
    // Fallback to local config file
    $config_file = __DIR__ . '/ai_config.json';
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        if (isset($config['openai_api_key'])) {
            return $config['openai_api_key'];
        }
    }
    
    // Last resort - throw error if no key found
    throw new Exception('OpenAI API key not configured. Please set OPENAI_API_KEY environment variable or create ai_config.json');
}

// Database Configuration (if needed separately)
function getDatabaseConfig() {
    return [
        'host' => 'tulevaisuusluotain.fi',
        'username' => 'catbxjbt_Christian',
        'password' => 'Juustonaksu5',
        'database' => 'catbxjbt_ennakointi',
        'charset' => 'utf8mb4'
    ];
}


// testailut




// Python script configuration

function getPythonOpenAIConfig() {
    try {
        $api_key = getOpenAIKey();
        return [
            'api_key' => $api_key,
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1500,
            'temperature' => 0.3
        ];
    } catch (Exception $e) {
        error_log("OpenAI configuration error: " . $e->getMessage());
        return null;
    }
}
?>