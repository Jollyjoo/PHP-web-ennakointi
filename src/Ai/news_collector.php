<?php
/**
 * News Collection System for Hämeen ELY-keskus
 * Collects news from various Finnish sources for AI analysis
 */

class NewsCollector {
    
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    /**
     * Collect news from various sources
     */
    public function collectNews() {
        $collected_articles = [];
        $errors = [];
        
        // First, let's test with working RSS feeds
        $rss_sources = [
            'YLE Uutiset' => 'https://feeds.yle.fi/uutiset/v1/recent.rss',
            'YLE Talous' => 'https://feeds.yle.fi/uutiset/v1/recent.rss?categories=18-276',
            // Comment out potentially problematic feeds initially
            // 'STT' => 'https://www.sttinfo.fi/rss/stt',
            // 'Hämeen Sanomat' => 'https://www.hameensanomat.fi/rss/tuoreimmat',
            // 'Aamulehti' => 'https://www.aamulehti.fi/rss/uusimmat'
        ];
        
        foreach ($rss_sources as $source_name => $rss_url) {
            try {
                $articles = $this->parseRSSFeed($rss_url, $source_name);
                $collected_articles = array_merge($collected_articles, $articles);
                
                // Store debug message instead of echo
                $errors[] = "✅ Collected " . count($articles) . " articles from $source_name";
            } catch (Exception $e) {
                $errors[] = "❌ Failed to collect from $source_name: " . $e->getMessage();
            }
        }
        
        // Skip press releases for now to avoid additional errors
        /*
        // 2. Regional Press Releases
        $press_release_sources = [
            'ELY-keskus' => 'https://www.ely-keskus.fi/rss',
            'Hämeenlinna' => 'https://www.hameenlinna.fi/feed/',
            'Tampere' => 'https://www.tampere.fi/rss'
        ];
        
        foreach ($press_release_sources as $source_name => $url) {
            try {
                $articles = $this->parseRSSFeed($url, $source_name . ' (Press Release)');
                $collected_articles = array_merge($collected_articles, $articles);
            } catch (Exception $e) {
                $errors[] = "⚠️ Press release source $source_name unavailable";
                echo "⚠️ Press release source $source_name unavailable\n";
            }
        }
        */
        
        // 3. Store collected articles
        $stored_count = 0;
        $storage_errors = [];
        
        foreach ($collected_articles as $article) {
            try {
                if ($this->storeArticle($article)) {
                    $stored_count++;
                }
            } catch (Exception $e) {
                $storage_errors[] = "Storage error: " . $e->getMessage();
            }
        }
        
        return [
            'total_collected' => count($collected_articles),
            'successfully_stored' => $stored_count,
            'collection_time' => date('Y-m-d H:i:s'),
            'errors' => $errors,
            'storage_errors' => $storage_errors,
            'debug_info' => 'News collection attempted with ' . count($rss_sources) . ' sources'
        ];
    }
    
    /**
     * Parse RSS feed and extract articles
     */
    private function parseRSSFeed($rss_url, $source_name) {
        $articles = [];
        
        // Set user agent to avoid blocking
        $context = stream_context_create([
            'http' => [
                'user_agent' => 'Hämeen ELY-keskus News Monitor/1.0',
                'timeout' => 30
            ]
        ]);
        
        $rss_content = @file_get_contents($rss_url, false, $context);
        
        if ($rss_content === false) {
            throw new Exception("Could not fetch RSS from $rss_url");
        }
        
        $xml = simplexml_load_string($rss_content);
        
        if ($xml === false) {
            throw new Exception("Invalid RSS format from $rss_url");
        }
        
        // Parse RSS items
        $items = $xml->channel->item ?? $xml->item ?? [];
        
        foreach ($items as $item) {
            $title = (string)$item->title;
            $description = (string)($item->description ?? $item->summary ?? '');
            $link = (string)$item->link;
            $pub_date = (string)$item->pubDate;
            
            // Filter for Häme region relevance
            if ($this->isRelevantToHame($title . ' ' . $description)) {
                $articles[] = [
                    'title' => $title,
                    'content' => $description,
                    'url' => $link,
                    'source' => $source_name,
                    'published_date' => $this->parseDate($pub_date),
                    'collected_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $articles;
    }
    
    /**
     * Check if article is relevant to Häme region
     */
    private function isRelevantToHame($text) {
        $hame_keywords = [
            // Cities and regions
            'häme', 'hämeenlinna', 'riihimäki', 'forssa', 'janakkala', 'hattula',
            'hausjärvi', 'loppi', 'tammela', 'humppila', 'ypäjä',
            
            // ELY topics
            'ely-keskus', 'elinkeinoelämä', 'työllisyys', 'koulutus', 'ympäristö',
            'liikenne', 'infrastruktuuri', 'yritystuki', 'innovaatio',
            
            // Business and economy
            'teollisuus', 'teknologia', 'startup', 'yrityskehitys', 'investointi',
            'rahoitus', 'hanke', 'kehitysohjelma'
        ];
        
        $text_lower = strtolower($text);
        
        foreach ($hame_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse date from various formats
     */
    private function parseDate($date_string) {
        if (empty($date_string)) {
            return date('Y-m-d H:i:s');
        }
        
        // Try to parse the date
        $timestamp = strtotime($date_string);
        
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return date('Y-m-d H:i:s'); // Default to now
    }
    
    /**
     * Store article in database
     */
    private function storeArticle($article) {
        try {
            // Check if we even have database connection
            if (!$this->db) {
                throw new Exception("No database connection available");
            }
            
            // Check if news_articles table exists
            $result = $this->db->query("SHOW TABLES LIKE 'news_articles'");
            if ($result->num_rows == 0) {
                throw new Exception("news_articles table does not exist. Please run create_news_tables.sql first.");
            }
            
            // Check if article already exists (avoid duplicates)
            $check_stmt = $this->db->prepare("
                SELECT id FROM news_articles 
                WHERE title = ? AND source = ? 
                LIMIT 1
            ");
            
            if (!$check_stmt) {
                throw new Exception("Failed to prepare check statement: " . $this->db->error);
            }
            
            $check_stmt->bind_param("ss", $article['title'], $article['source']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                return false; // Article already exists
            }
            
            // Insert new article
            $insert_stmt = $this->db->prepare("
                INSERT INTO news_articles 
                (title, content, url, source, published_date, collected_at, analysis_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            if (!$insert_stmt) {
                throw new Exception("Failed to prepare insert statement: " . $this->db->error);
            }
            
            $insert_stmt->bind_param("ssssss", 
                $article['title'],
                $article['content'],
                $article['url'],
                $article['source'],
                $article['published_date'],
                $article['collected_at']
            );
            
            $result = $insert_stmt->execute();
            
            if (!$result) {
                throw new Exception("Failed to execute insert: " . $insert_stmt->error);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to store article: " . $e->getMessage());
            throw $e; // Re-throw to be caught by caller
        }
    }
    
    /**
     * Test news collection without database storage
     */
    public function testCollection() {
        $collected_articles = [];
        $errors = [];
        $debug_messages = [];
        
        // Simple test with YLE RSS
        $test_sources = [
            'YLE Test' => 'https://feeds.yle.fi/uutiset/v1/recent.rss'
        ];
        
        foreach ($test_sources as $source_name => $rss_url) {
            try {
                $articles = $this->parseRSSFeed($rss_url, $source_name);
                $collected_articles = array_merge($collected_articles, $articles);
                
                $debug_messages[] = "✅ Test collected " . count($articles) . " articles from $source_name";
            } catch (Exception $e) {
                $errors[] = "❌ Test failed for $source_name: " . $e->getMessage();
                $debug_messages[] = "❌ Test failed for $source_name: " . $e->getMessage();
            }
        }
        
        return [
            'test_mode' => true,
            'total_collected' => count($collected_articles),
            'articles_sample' => array_slice($collected_articles, 0, 3), // First 3 articles
            'collection_time' => date('Y-m-d H:i:s'),
            'errors' => $errors,
            'debug_messages' => $debug_messages,
            'message' => 'Test completed without database storage'
        ];
    }
    public function getCollectionStats() {
        $stats = [];
        
        // Total articles
        $result = $this->db->query("SELECT COUNT(*) as total FROM news_articles");
        $stats['total_articles'] = $result->fetch_assoc()['total'];
        
        // By source
        $result = $this->db->query("
            SELECT source, COUNT(*) as count 
            FROM news_articles 
            GROUP BY source 
            ORDER BY count DESC
        ");
        
        $stats['by_source'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_source'][$row['source']] = $row['count'];
        }
        
        // Recent activity (last 24 hours)
        $result = $this->db->query("
            SELECT COUNT(*) as recent 
            FROM news_articles 
            WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['recent_24h'] = $result->fetch_assoc()['recent'];
        
        return $stats;
    }
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'collect';
    
    try {
        // For test mode, we don't need database
        if ($action === 'test') {
            $collector = new NewsCollector(null); // No database needed
            $result = $collector->testCollection();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        // For other actions, try database connection
        require_once 'db.php';
        
        // Create MySQLi connection
        $mysqli = new mysqli('tulevaisuusluotain.fi', 'catbxjbt_readonly', 'TamaonSalainen44', 'catbxjbt_ennakointi');
        $mysqli->set_charset("utf8mb4");
        
        if ($mysqli->connect_error) {
            throw new Exception('Database connection failed: ' . $mysqli->connect_error);
        }
        
        $collector = new NewsCollector($mysqli);
        
        switch ($action) {
            case 'collect':
                $result = $collector->collectNews();
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
                
            case 'stats':
                $stats = $collector->getCollectionStats();
                echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
                
            default:
                echo json_encode(['status' => 'News Collector Ready', 'timestamp' => date('Y-m-d H:i:s')]);
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'suggestion' => 'Try the test mode first: ?action=test',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
?>

<!-- HTML Interface for Manual Collection -->
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <title>News Collection System</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .result { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
        .loading { display: none; color: #666; }
    </style>
</head>
<body>
    <h1>📰 News Collection System</h1>
    <p>Collect news from Finnish sources for AI analysis</p>
    
    <button class="btn" onclick="collectNews()">🔍 Collect News Now</button>
    <button class="btn" onclick="testNews()">🧪 Test Collection (No DB)</button>
    <button class="btn" onclick="getStats()">📊 View Statistics</button>
    
    <div class="loading" id="loading">⏳ Collecting news...</div>
    <div id="results"></div>
    
    <script>
        async function testNews() {
            document.getElementById('loading').style.display = 'block';
            try {
                const response = await fetch('?action=test');
                const data = await response.json();
                
                let samplesHtml = '';
                if (data.articles_sample) {
                    samplesHtml = '<h4>Sample Articles:</h4><ul>';
                    data.articles_sample.forEach(article => {
                        samplesHtml += `<li><strong>${article.title}</strong> (${article.source})</li>`;
                    });
                    samplesHtml += '</ul>';
                }
                
                document.getElementById('results').innerHTML = `
                    <div class="result">
                        <h3>🧪 Test Results</h3>
                        <p><strong>Status:</strong> ${data.test_mode ? 'Test Mode' : 'Normal Mode'}</p>
                        <p><strong>Articles Found:</strong> ${data.total_collected}</p>
                        <p><strong>Time:</strong> ${data.collection_time}</p>
                        <p><strong>Message:</strong> ${data.message}</p>
                        ${samplesHtml}
                        ${data.errors && data.errors.length > 0 ? '<p><strong>Errors:</strong> ' + data.errors.join(', ') + '</p>' : ''}
                    </div>
                `;
            } catch (error) {
                document.getElementById('results').innerHTML = `
                    <div class="result" style="border-left-color: #dc3545;">
                        <h3>❌ Test Failed</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
            document.getElementById('loading').style.display = 'none';
        }

        async function collectNews() {
            document.getElementById('loading').style.display = 'block';
            try {
                const response = await fetch('?action=collect', { method: 'POST' });
                const data = await response.json();
                
                document.getElementById('results').innerHTML = `
                    <div class="result">
                        <h3>✅ Collection Complete</h3>
                        <p><strong>Total Collected:</strong> ${data.total_collected}</p>
                        <p><strong>Successfully Stored:</strong> ${data.successfully_stored}</p>
                        <p><strong>Collection Time:</strong> ${data.collection_time}</p>
                    </div>
                `;
            } catch (error) {
                document.getElementById('results').innerHTML = `
                    <div class="result" style="border-left-color: #dc3545;">
                        <h3>❌ Collection Failed</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
            document.getElementById('loading').style.display = 'none';
        }
        
        async function getStats() {
            try {
                const response = await fetch('?action=stats');
                const data = await response.json();
                
                let sourcesList = '';
                for (const [source, count] of Object.entries(data.by_source || {})) {
                    sourcesList += `<li>${source}: ${count} articles</li>`;
                }
                
                document.getElementById('results').innerHTML = `
                    <div class="result">
                        <h3>📊 Collection Statistics</h3>
                        <p><strong>Total Articles:</strong> ${data.total_articles}</p>
                        <p><strong>Recent (24h):</strong> ${data.recent_24h}</p>
                        <h4>By Source:</h4>
                        <ul>${sourcesList}</ul>
                    </div>
                `;
            } catch (error) {
                console.error('Error fetching stats:', error);
            }
        }
    </script>
</body>
</html>