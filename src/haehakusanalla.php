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
$conn->set_charset("utf8mb4");
// Ensure full Unicode (emojit yms.)
$conn->query("SET NAMES utf8mb4");

$q = $_GET['q'];
$start = isset($_GET['start']) ? intval($_GET['start']) : 0; // Default to 0 if not provided

$sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Hankkeen_luokitus, Url,
              ai_relevance_score, ai_economic_impact, ai_employment_impact, ai_key_sectors, 
              ai_sentiment, ai_crisis_probability, ai_summary, ai_keywords, ai_analysis_status, ai_analyzed_at, ai_processing_time,
              competitive_analysis_status, competitive_score, competitors_mentioned, market_opportunities, competitive_analysis,
              business_relevance, strategic_importance, funding_intelligence, market_intelligence
          FROM catbxjbt_ennakointi.Mediaseuranta
          WHERE Uutinen LIKE '%" . $q . "%'
          ORDER BY uutisen_pvm DESC
          LIMIT $start, 20;";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convert 'aika' to Finnish date format
        $formattedDate = (new DateTime($row["aika"]))->format('d.m.Y');

        // Replace '-?' with '-' in the 'Uutinen' field
        $cleanedUutinen = str_replace('-?', '-', $row["Uutinen"]);

        // Truncate 'Hankkeen_luokitus' to 15 characters
        $truncatedLuokitus = mb_substr($row["Teema"], 0, 18);
        if (mb_strlen($row["Teema"]) > 18) {
            $truncatedLuokitus .= "..."; // Add ellipsis if text is truncated
        }
        
        // Prepare AI analysis data for tooltip (same logic as haeMaakunnalla.php)
        $hasAiAnalysis = ($row["ai_analysis_status"] === 'completed');
        $aiTooltip = "";
        $aiIndicator = "";
        $compTooltip = "";
        $compIndicator = "";
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
            // Extra AI details
            if (!empty($keywords)) {
                $aiTooltip .= "🏷️ Avainsanat: " . implode(", ", array_slice($keywords, 0, 5)) . "\n";
            }
            if (!empty($row["ai_analyzed_at"])) {
                $aiTooltip .= "⏱️ Analysoitu: " . (new DateTime($row["ai_analyzed_at"]))->format('d.m.Y H:i') . "\n";
            }
            if (!empty($row["ai_processing_time"])) {
                $aiTooltip .= "⏳ Käsittelyaika: " . $row["ai_processing_time"] . " s\n";
            }
            if (!empty($row["ai_analysis_status"])) {
                $aiTooltip .= "📌 Tila: " . $row["ai_analysis_status"] . "\n";
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
        // Competitive analysis tooltip
        $hasCompetitive = isset($row["competitive_analysis_status"]) && ($row["competitive_analysis_status"] === 'analyzed' || $row["competitive_analysis_status"] === 'completed');
        if ($hasCompetitive) {
            $competitorsList = [];
            if (!empty($row["competitors_mentioned"])) {
                $decoded = json_decode($row["competitors_mentioned"], true);
                if (is_array($decoded)) {
                    $competitorsList = $decoded;
                } else {
                    $competitorsList = array_map('trim', explode(',', $row["competitors_mentioned"]));
                }
            }
            $compTooltip = "📈 KILPAILUANALYYSI:\n\n";
            if (isset($row["competitive_score"]) && $row["competitive_score"] !== null && $row["competitive_score"] !== '') {
                $compTooltip .= "⭐ Pisteet: " . $row["competitive_score"] . "/10\n";
            }
            if (!empty($competitorsList)) {
                $compTooltip .= "🏢 Mainitut kilpailijat: " . implode(', ', array_slice($competitorsList, 0, 3)) . "\n";
            }
            if (!empty($row["market_opportunities"])) {
                $compTooltip .= "💡 Mahdollisuuksia: " . mb_substr(strip_tags($row["market_opportunities"]), 0, 140) . "…\n";
            }
            if (!empty($row["business_relevance"])) {
                $compTooltip .= "🏷️ Liiketoiminnan relevanssi: " . $row["business_relevance"] . "\n";
            }
            if (isset($row["strategic_importance"]) && $row["strategic_importance"] !== null && $row["strategic_importance"] !== '') {
                $stars = str_repeat('★', max(0, (int)$row["strategic_importance"])) . str_repeat('☆', max(0, 5 - (int)$row["strategic_importance"])) ;
                $compTooltip .= "🎯 Strateginen tärkeys: " . $stars . "\n";
            }
            if (!empty($row["funding_intelligence"])) {
                $compTooltip .= "💶 Rahoitushavainnot: " . mb_substr(strip_tags($row["funding_intelligence"]), 0, 120) . "…\n";
            }
            if (!empty($row["market_intelligence"])) {
                $compTooltip .= "📊 Markkinatieto: " . mb_substr(strip_tags($row["market_intelligence"]), 0, 120) . "…\n";
            }
            if (!empty($row["competitive_analysis"])) {
                $compTooltip .= "\n📝 Yhteenveto: " . mb_substr(strip_tags($row["competitive_analysis"]), 0, 200) . "…";
            }
            $compIndicator = "📊";
            echo "<span class='ai-indicator' title='" . htmlspecialchars($compTooltip, ENT_QUOTES, 'UTF-8') . "'>" . $compIndicator . "</span> ";
        }
        
        echo "<a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $cleanedUutinen . "</a>, ";        
        echo "</div><br>";
    }
} else {
    echo "0 results";
}
$conn->close();

?>