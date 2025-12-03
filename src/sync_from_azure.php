
<?php
// EI KÄYTÖSSÄ TÄLLÄ HETKELLÄ
// HOMMAN HOITAA : receive_mediaseuranta.php . .. joka on webhook endpoint, johon Azure SQL lähettää uudet rivit heti kun ne lisätään sinne.


// sync_from_azure.php - Poll Azure SQL and sync new records to MySQL
// Run this via cron job every few minutes

require_once 'db.php';

try {
    // Azure SQL connection
    $azure_dsn = 'sqlsrv:Server=your-azure-server.database.windows.net;Database=your-database';
    $azure_user = 'your-azure-username';
    $azure_pass = 'your-azure-password';
    
    $azure_pdo = new PDO($azure_dsn, $azure_user, $azure_pass);
    
    // MySQL connection (your existing)
    $mysql_pdo = new PDO($dsn, $db_user, $db_pass, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
    
    // Get the last synced ID from MySQL
    $last_sync_query = "SELECT MAX(azure_sync_id) as last_id FROM Mediaseuranta WHERE azure_sync_id IS NOT NULL";
    $last_sync_stmt = $mysql_pdo->query($last_sync_query);
    $last_sync = $last_sync_stmt->fetch(PDO::FETCH_ASSOC);
    $last_synced_id = $last_sync['last_id'] ?? 0;
    
    // Get new records from Azure SQL
    $azure_query = "
        SELECT TOP 100 
            ID, Maakunta_ID, Teema, uutisen_pvm, Uutinen, Url, Hankkeen_luokitus,
            ai_analysis_status, ai_relevance_score, ai_economic_impact,
            ai_employment_impact, ai_key_sectors, ai_sentiment, ai_crisis_probability
        FROM Mediaseuranta 
        WHERE ID > ? 
        ORDER BY ID ASC
    ";
    
    $azure_stmt = $azure_pdo->prepare($azure_query);
    $azure_stmt->execute([$last_synced_id]);
    $new_records = $azure_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($new_records) > 0) {
        // Prepare MySQL insert
        $mysql_insert = "INSERT INTO Mediaseuranta (
            azure_sync_id, Maakunta_ID, Teema, uutisen_pvm, Uutinen, Url, Hankkeen_luokitus,
            ai_analysis_status, ai_relevance_score, ai_economic_impact,
            ai_employment_impact, ai_key_sectors, ai_sentiment, ai_crisis_probability
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $mysql_stmt = $mysql_pdo->prepare($mysql_insert);
        
        $synced_count = 0;
        foreach ($new_records as $record) {
            $result = $mysql_stmt->execute([
                $record['ID'], // Store Azure ID for tracking
                $record['Maakunta_ID'],
                $record['Teema'],
                $record['uutisen_pvm'],
                $record['Uutinen'],
                $record['Url'],
                $record['Hankkeen_luokitus'],
                $record['ai_analysis_status'] ?? 'pending',
                $record['ai_relevance_score'],
                $record['ai_economic_impact'],
                $record['ai_employment_impact'],
                $record['ai_key_sectors'],
                $record['ai_sentiment'],
                $record['ai_crisis_probability']
            ]);
            
            if ($result) {
                $synced_count++;
            }
        }
        
        echo "Synced $synced_count new records from Azure SQL to MySQL\n";
        error_log("Azure sync: $synced_count records synchronized");
    } else {
        echo "No new records to sync\n";
    }
    
} catch (Exception $e) {
    echo "Sync error: " . $e->getMessage() . "\n";
    error_log("Azure sync error: " . $e->getMessage());
}
?>