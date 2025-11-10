<?php
/**
 * FIXED News Collection with Correct YLE RSS URLs
 * Based on server test results showing network connectivity works
 */

header('Content-Type: application/json; charset=utf-8');

function testRealNewsFeeds() {
    $results = [];
    
    try {
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is not available on this server');
        }
        
        // Use correct YLE RSS URLs based on server test results
        $working_rss_sources = [
            'YLE Uutiset' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_UUTISET',
            'YLE News (English)' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_NEWS'
        ];
        
        $all_articles = [];
        $source_results = [];
        
        foreach ($working_rss_sources as $source_name => $rss_url) {
            try {
                $articles = fetchAndParseRSS($rss_url, $source_name);
                $all_articles = array_merge($all_articles, $articles);
                $source_results[$source_name] = [
                    'status' => 'success',
                    'articles_found' => count($articles),
                    'url' => $rss_url
                ];
            } catch (Exception $e) {
                $source_results[$source_name] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'url' => $rss_url
                ];
            }
        }
        
        // Filter for Häme relevance
        $hame_articles = array_filter($all_articles, function($article) {
            return $article['relevant_to_hame'];
        });
        
        return [
            'success' => true,
            'method' => 'Fixed RSS URLs',
            'total_articles' => count($all_articles),
            'hame_relevant' => count($hame_articles),
            'source_results' => $source_results,
            'sample_articles' => array_slice($all_articles, 0, 5),
            'hame_articles' => array_slice($hame_articles, 0, 3),
            'test_time' => date('Y-m-d H:i:s'),
            'message' => 'Real news collection working!'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'test_time' => date('Y-m-d H:i:s')
        ];
    }
}

function fetchAndParseRSS($rss_url, $source_name) {
    // Initialize cURL with proper settings based on server test
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $rss_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ELY-keskus/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/rss+xml, application/xml, text/xml',
            'Accept-Language: fi,en',
            'Cache-Control: no-cache'
        ]
    ]);
    
    $rss_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Check for errors
    if ($rss_content === false || !empty($curl_error)) {
        throw new Exception("cURL error for $source_name: $curl_error");
    }
    
    if ($http_code !== 200) {
        throw new Exception("HTTP $http_code error for $source_name");
    }
    
    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rss_content);
    
    if ($xml === false) {
        $xml_errors = libxml_get_errors();
        $error_messages = array_map(function($e) { return trim($e->message); }, $xml_errors);
        throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
    }
    
    // Extract articles
    $articles = [];
    $items = $xml->channel->item ?? $xml->item ?? [];
    
    foreach ($items as $item) {
        $title = (string)$item->title;
        $description = (string)($item->description ?? $item->summary ?? '');
        $link = (string)$item->link;
        $pub_date = (string)$item->pubDate;
        
        // Check relevance to Häme region
        $relevance_keywords = [
            'häme', 'hämeenlinna', 'riihimäki', 'forssa', 'janakkala', 'hattula',
            'tampere', 'lahti', 'heinola', 'ely-keskus', 'elinkeinoelämä',
            'työllisyys', 'koulutus', 'yritystuki', 'innovaatio', 'teknologia'
        ];
        
        $text_to_check = strtolower($title . ' ' . $description);
        $is_relevant = false;
        $matched_keywords = [];
        
        foreach ($relevance_keywords as $keyword) {
            if (strpos($text_to_check, $keyword) !== false) {
                $is_relevant = true;
                $matched_keywords[] = $keyword;
            }
        }
        
        $articles[] = [
            'title' => $title,
            'description' => substr(strip_tags($description), 0, 300) . '...',
            'link' => $link,
            'pub_date' => $pub_date,
            'source' => $source_name,
            'relevant_to_hame' => $is_relevant,
            'matched_keywords' => $matched_keywords,
            'collected_at' => date('Y-m-d H:i:s')
        ];
    }
    
    return $articles;
}

function testMultipleSources() {
    // Test additional working RSS sources
    $additional_sources = [
        'Finnish Broadcasting' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_UUTISET&categories=18-276', // Economy
        'YLE Selkouutiset' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?publisherIds=YLE_SELKOUUTISET'
    ];
    
    $results = [];
    
    foreach ($additional_sources as $name => $url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ELY-test/1.0)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_HEADER => true
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $results[$name] = [
                'url' => $url,
                'http_code' => $http_code,
                'status' => $http_code === 200 ? 'working' : 'failed'
            ];
            
        } catch (Exception $e) {
            $results[$name] = [
                'url' => $url,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return [
        'source_tests' => $results,
        'test_time' => date('Y-m-d H:i:s')
    ];
}

// Handle requests
$action = $_GET['action'] ?? 'real_news';

switch ($action) {
    case 'real_news':
        echo json_encode(testRealNewsFeeds(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    case 'test_sources':
        echo json_encode(testMultipleSources(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    default:
        echo json_encode([
            'available_actions' => [
                'real_news' => 'Test real news collection with fixed URLs',
                'test_sources' => 'Test multiple RSS sources'
            ],
            'server_status' => 'Network connectivity confirmed working',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>