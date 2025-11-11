<?php
/**
 * Simple News Collection Test - No Database Required
 */

header('Content-Type: application/json; charset=utf-8');

function testRSSCollection() {
    $results = [];
    $errors = [];
    
    try {
        // Test with a simple RSS feed
        $rss_url = 'https://feeds.yle.fi/uutiset/v1/recent.rss';
        
        // Check if we can fetch URLs
        if (!function_exists('file_get_contents') || !ini_get('allow_url_fopen')) {
            throw new Exception('PHP allow_url_fopen is disabled or file_get_contents not available');
        }
        
        // Set up context with user agent
        $context = stream_context_create([
            'http' => [
                'user_agent' => 'Mozilla/5.0 (compatible; ELY-keskus-bot/1.0)',
                'timeout' => 10
            ]
        ]);
        
        // Try to fetch RSS
        $rss_content = @file_get_contents($rss_url, false, $context);
        
        if ($rss_content === false) {
            throw new Exception('Could not fetch RSS from YLE - network error or blocked');
        }
        
        // Try to parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rss_content);
        
        if ($xml === false) {
            $xml_errors = libxml_get_errors();
            $error_messages = [];
            foreach ($xml_errors as $error) {
                $error_messages[] = $error->message;
            }
            throw new Exception('Invalid RSS XML: ' . implode(', ', $error_messages));
        }
        
        // Parse items
        $articles = [];
        $items = $xml->channel->item ?? $xml->item ?? [];
        
        $count = 0;
        foreach ($items as $item) {
            if ($count >= 5) break; // Limit to first 5 for testing
            
            $title = (string)$item->title;
            $description = (string)($item->description ?? $item->summary ?? '');
            $link = (string)$item->link;
            $pub_date = (string)$item->pubDate;
            
            $articles[] = [
                'title' => $title,
                'description' => substr($description, 0, 200) . '...',
                'link' => $link,
                'pub_date' => $pub_date,
                'source' => 'YLE'
            ];
            
            $count++;
        }
        
        return [
            'success' => true,
            'total_found' => count($items),
            'sample_articles' => $articles,
            'test_time' => date('Y-m-d H:i:s'),
            'message' => 'RSS collection test successful'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'test_time' => date('Y-m-d H:i:s'),
            'suggestions' => [
                'Check if server allows outbound HTTP requests',
                'Verify PHP allow_url_fopen is enabled',
                'Check network connectivity'
            ]
        ];
    }
}

// Handle request
$action = $_GET['action'] ?? 'test';

if ($action === 'test') {
    echo json_encode(testRSSCollection(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'status' => 'Simple News Test Ready',
        'available_actions' => ['test'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>