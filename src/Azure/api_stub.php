<?php
header('Content-Type: application/json');

$headers = function_exists('getallheaders') ? getallheaders() : [];
$bodyRaw = file_get_contents('php://input');

$form = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/x-www-form-urlencoded') !== false) {
        parse_str($bodyRaw, $form);
    } elseif (stripos($ctype, 'application/json') !== false) {
        $form = json_decode($bodyRaw, true);
    }
}

$apiKey = ($form['api_key'] ?? ($_GET['api_key'] ?? ''));
if ($apiKey !== 'your-secret-api-key') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $form['action'] ?? ($_GET['action'] ?? 'get_queue');

try {
    // Move real Azure SQL logic here (api.php is flaky)
    $dsn = 'sqlsrv:Server=ennakointisrv.database.windows.net;Database=EnnakointiDB';
    $user = 'Christian';
    $pass = 'Ennakointi24';
    $pdo = new PDO($dsn, $user, $pass);

    if ($action === 'get_queue') {
        // Match your table schema: queue_id, record_id, created_at, status, retry_count, last_attempt, error_message
        $query = "
            SELECT TOP 50 
                   q.queue_id AS QueueID,
                   q.record_id AS MediaseurantaID,
                   q.created_at AS CreatedAt,
                   m.Maakunta_ID, m.Teema, m.uutisen_pvm, m.Uutinen, m.Url, m.Hankkeen_luokitus,
                   m.ai_analysis_status, m.ai_analyzed_at, m.ai_relevance_score, m.ai_economic_impact,
                   m.ai_employment_impact, m.ai_key_sectors, m.ai_sentiment, m.ai_crisis_probability,
                   m.ai_summary, m.ai_keywords, m.ai_full_analysis, m.ai_processing_time,
                   m.competitive_analysis_status, m.competitive_analyzed_at, m.competitive_analysis,
                   m.competitors_mentioned, m.funding_intelligence, m.market_opportunities,
                   m.partnership_opportunities, m.competitive_score, m.business_relevance,
                   m.strategic_importance, m.competitive_threats, m.market_intelligence,
                   m.action_recommendations
            FROM MediaseurantaQueue q
            INNER JOIN Mediaseuranta m ON q.record_id = m.ID
            WHERE q.status = 'pending'
            ORDER BY q.created_at ASC
        ";
        $stmt = $pdo->query($query);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'success','count'=>count($records),'records'=>$records,'timestamp'=>date('Y-m-d H:i:s')]);
    } elseif ($action === 'mark_processed') {
        // Accept queue_ids from form or query
        $queueIds = $form['queue_ids'] ?? ($_GET['queue_ids'] ?? '');
        if (empty($queueIds)) {
            echo json_encode(['status' => 'error', 'message' => 'No queue IDs provided']);
            exit;
        }
        // Normalize IDs (comma-separated)
        $ids = preg_split('/\s*,\s*/', $queueIds, -1, PREG_SPLIT_NO_EMPTY);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Update per your schema: mark processed and set last_attempt
        $update = "UPDATE MediaseurantaQueue SET status = 'processed', last_attempt = GETDATE(), error_message = NULL WHERE queue_id IN ($placeholders)";
        $stmt = $pdo->prepare($update);
        $stmt->execute($ids);
        echo json_encode(['status'=>'success','message'=>'Records marked as processed','updated_count'=>$stmt->rowCount(),'timestamp'=>date('Y-m-d H:i:s')]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage(),'timestamp'=>date('Y-m-d H:i:s')]);
}
?>