<?php
/**
 * News Collection with cURL Alternative
 * Works around allow_url_fopen restrictions
 */

header('Content-Type: application/json; charset=utf-8');

function testWithCurl() {
    $results = [];
    
    try {
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            throw new Exception('Neither allow_url_fopen nor cURL is available on this server');
        }
        
        $rss_url = 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_UUTISET';
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $rss_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ELY-keskus-bot/1.0)',
            CURLOPT_SSL_VERIFYPEER => false, // For SSL issues
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/xml, text/xml',
                'Cache-Control: no-cache'
            ]
        ]);
        
        // Execute request
        $rss_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Check for cURL errors
        if ($rss_content === false || !empty($curl_error)) {
            throw new Exception("cURL error: $curl_error (HTTP code: $http_code)");
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP error: Server returned code $http_code");
        }
        
        // Try to parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rss_content);
        
        if ($xml === false) {
            $xml_errors = libxml_get_errors();
            $error_messages = [];
            foreach ($xml_errors as $error) {
                $error_messages[] = trim($error->message);
            }
            throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        // Parse articles
        $articles = [];
        $items = $xml->channel->item ?? $xml->item ?? [];
        
        $count = 0;
        foreach ($items as $item) {
            if ($count >= 5) break;
            
            $title = (string)$item->title;
            $description = (string)($item->description ?? $item->summary ?? '');
            $link = (string)$item->link;
            $pub_date = (string)$item->pubDate;
            
            // Check if relevant to Häme region
            $is_relevant = false;
            $relevance_keywords = ['häme', 'hämeenlinna', 'tampere', 'riihimäki', 'forssa', 'työllisyys', 'koulutus', 'yritys'];
            $text_to_check = strtolower($title . ' ' . $description);
            
            foreach ($relevance_keywords as $keyword) {
                if (strpos($text_to_check, $keyword) !== false) {
                    $is_relevant = true;
                    break;
                }
            }
            
            $articles[] = [
                'title' => $title,
                'description' => substr(strip_tags($description), 0, 200) . '...',
                'link' => $link,
                'pub_date' => $pub_date,
                'source' => 'YLE',
                'relevant_to_hame' => $is_relevant
            ];
            
            $count++;
        }
        
        return [
            'success' => true,
            'method' => 'cURL',
            'total_found' => count($items),
            'sample_articles' => $articles,
            'relevant_articles' => count(array_filter($articles, function($a) { return $a['relevant_to_hame']; })),
            'test_time' => date('Y-m-d H:i:s'),
            'http_code' => $http_code,
            'message' => 'cURL RSS collection successful'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'method' => 'cURL',
            'error' => $e->getMessage(),
            'test_time' => date('Y-m-d H:i:s'),
            'debug_info' => [
                'curl_available' => function_exists('curl_init'),
                'allow_url_fopen' => ini_get('allow_url_fopen') ? 'enabled' : 'disabled',
                'openssl_available' => extension_loaded('openssl')
            ]
        ];
    }
}

function testWithMockData() {
    // Fallback: return sample data to test the rest of the system
    return [
        'success' => true,
        'method' => 'mock_data',
        'total_found' => 3,
        'sample_articles' => [
            [
                'title' => 'Hämeen ELY-keskus myönsi 2,5M€ vihreän teknologian avustuksia',
                'description' => 'Hämeen elinkeino-, liikenne- ja ympäristökeskus myönsi yhteensä 2,5 miljoonaa euroa avustuksia vihreän teknologian hankkeisiin...',
                'link' => 'https://example.com/news1',
                'pub_date' => date('r'),
                'source' => 'Mock Data',
                'relevant_to_hame' => true
            ],
            [
                'title' => 'Tampereen teknologiakeskus laajentaa Hämeenlinnaan',
                'description' => 'Teknologiakeskus avaa uuden toimipisteen Hämeenlinnaan, joka keskittyy tekoälyn ja automaation tutkimukseen...',
                'link' => 'https://example.com/news2',
                'pub_date' => date('r', strtotime('-1 day')),
                'source' => 'Mock Data',
                'relevant_to_hame' => true
            ],
            [
                'title' => 'Riihimäen ammattikorkeakouluun AI-laboratorio',
                'description' => 'Uusi tekoälylaboratorio palvelee Hämeen alueen yritysten digitalisaatiotarpeita...',
                'link' => 'https://example.com/news3',
                'pub_date' => date('r', strtotime('-2 days')),
                'source' => 'Mock Data',
                'relevant_to_hame' => true
            ]
        ],
        'relevant_articles' => 3,
        'test_time' => date('Y-m-d H:i:s'),
        'message' => 'Using mock data - network collection not available'
    ];
}

function checkServerCapabilities() {
    return [
        'php_version' => phpversion(),
        'allow_url_fopen' => ini_get('allow_url_fopen') ? 'enabled' : 'disabled',
        'curl_available' => function_exists('curl_init') ? 'yes' : 'no',
        'curl_version' => function_exists('curl_version') ? curl_version()['version'] : 'n/a',
        'openssl_available' => extension_loaded('openssl') ? 'yes' : 'no',
        'user_agent_allowed' => ini_get('user_agent') ?: 'default',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'outbound_connections' => 'unknown - needs testing'
    ];
}

// Handle different actions
$action = $_GET['action'] ?? 'curl_test';

switch ($action) {
    case 'curl_test':
        echo json_encode(testWithCurl(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'mock_test':
        echo json_encode(testWithMockData(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'capabilities':
        echo json_encode([
            'server_capabilities' => checkServerCapabilities(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    default:
        echo json_encode([
            'available_tests' => [
                'curl_test' => 'Test RSS collection with cURL',
                'mock_test' => 'Use sample data for testing',
                'capabilities' => 'Check server capabilities'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>