<?php
$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_readonly";
$password = "TamaonSalainen44";
$dbname = "catbxjbt_ennakointi";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Set character set to UTF-8
$conn->set_charset("utf8");

$q = $_GET['q'];
$start = isset($_GET['start']) ? intval($_GET['start']) : 0; // Default to 0 if not provided

// Adjust the query based on the value of 'q'
if ($q === "koko-häme") {
    // Fetch all content if 'koko-häme' is selected, including AI analysis data
    $sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Hankkeen_luokitus, Url, 
                   ai_relevance_score, ai_economic_impact, ai_employment_impact, ai_key_sectors, 
                   ai_sentiment, ai_crisis_probability, ai_summary, ai_keywords, ai_analysis_status
            FROM catbxjbt_ennakointi.Mediaseuranta
            ORDER BY uutisen_pvm DESC
            LIMIT $start, 20;";
} else {
    // Fetch content filtered by 'Maakunta_ID', including AI analysis data
    $sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Hankkeen_luokitus, Url,
                   ai_relevance_score, ai_economic_impact, ai_employment_impact, ai_key_sectors, 
                   ai_sentiment, ai_crisis_probability, ai_summary, ai_keywords, ai_analysis_status
            FROM catbxjbt_ennakointi.Mediaseuranta
            WHERE Maakunta_ID = (SELECT maakunta_id FROM catbxjbt_ennakointi.Maakunnat WHERE maakunta LIKE '%" . $conn->real_escape_string($q) . "%')
            ORDER BY uutisen_pvm DESC
            LIMIT $start, 20;";
}

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convert 'aika' to Finnish date format
        $formattedDate = (new DateTime($row["aika"]))->format('d.m.Y');

        // Replace '-?' with '-' in the 'Uutinen' field
        $cleanedUutinen = str_replace('-?', '-', $row["Uutinen"]);

        // Truncate 'Teema' to 15 characters
        $truncatedLuokitus = mb_substr($row["Teema"], 0, 20);
        if (mb_strlen($row["Teema"]) > 18) {
            $truncatedLuokitus .= "..."; // Add ellipsis if text is truncated
        }

        // Prepare AI analysis data for tooltip
        $hasAiAnalysis = ($row["ai_analysis_status"] === 'completed');
        $aiTooltip = "";
        $aiIndicator = "";
        $recordClass = "record";
        
        if ($hasAiAnalysis) {
            // Decode JSON fields
            $keySectors = json_decode($row["ai_key_sectors"], true) ?: [];
            $keywords = json_decode($row["ai_keywords"], true) ?: [];
            
            // Build tooltip content
            $aiTooltip = "🤖 AI ANALYYSI:\n\n";
            $aiTooltip .= "📊 Relevanssi: " . ($row["ai_relevance_score"] ?: "N/A") . "/10\n";
            $aiTooltip .= "😊 Tunnelma: " . ($row["ai_sentiment"] ?: "N/A") . "\n";
            $aiTooltip .= "💰 Talous: " . ($row["ai_economic_impact"] ?: "N/A") . "\n";
            
            if (!empty($row["ai_employment_impact"])) {
                $aiTooltip .= "👷 Työllisyys: " . $row["ai_employment_impact"] . "\n";
            }
            
            if (!empty($keySectors)) {
                $aiTooltip .= "🏢 Sektorit: " . implode(", ", array_slice($keySectors, 0, 3)) . "\n";
            }
            
            if ($row["ai_crisis_probability"] && $row["ai_crisis_probability"] > 0.3) {
                $crisisPercent = round($row["ai_crisis_probability"] * 100);
                $aiTooltip .= "⚠️ Kriisiriski: " . $crisisPercent . "%\n";
            }
            
            if (!empty($row["ai_summary"])) {
                $aiTooltip .= "\n📝 Yhteenveto: " . $row["ai_summary"];
            }
            
            // Determine AI indicator and styling
            $sentimentClass = "";
            switch($row["ai_sentiment"]) {
                case 'positive':
                case 'myönteinen':
                    $sentimentClass = "ai-positive";
                    $aiIndicator = "😊";
                    break;
                case 'negative': 
                case 'kielteinen':
                    $sentimentClass = "ai-negative";
                    $aiIndicator = "😟";
                    break;
                default:
                    $sentimentClass = "ai-neutral";
                    $aiIndicator = "😐";
            }
            
            // High crisis probability gets special styling
            if ($row["ai_crisis_probability"] && $row["ai_crisis_probability"] > 0.7) {
                $recordClass = "record ai-crisis";
                $aiIndicator = "🚨";
            } else {
                $recordClass = "record " . $sentimentClass;
            }
        }

        echo "<div class='$recordClass'>";
        echo "<b> " . $formattedDate . "  </b> "; 
        echo "<b title='" . htmlspecialchars($row["Teema"], ENT_QUOTES, 'UTF-8') . "'> " . $truncatedLuokitus . "</b>  "; 
        
        // Add AI indicator and analysis tooltip if available
        if ($hasAiAnalysis) {
            echo "<span class='ai-indicator' title='" . htmlspecialchars($aiTooltip, ENT_QUOTES, 'UTF-8') . "'>" . $aiIndicator . "</span> ";
        }
        
        echo "<a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $cleanedUutinen . "</a> ";
        echo "</div><br>";
    }
} else {
    echo "Ei tuloksia";
}
$conn->close();
?>