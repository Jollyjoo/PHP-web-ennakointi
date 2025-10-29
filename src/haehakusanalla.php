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

$sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Hankkeen_luokitus, Url 
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
        
        echo "<div class='record'>";
        echo "<b> " . $formattedDate . "  </b> "; // Display the formatted date
        echo "<b title='" . htmlspecialchars($row["Teema"], ENT_QUOTES, 'UTF-8') . "'> " . $truncatedLuokitus . "</b>  "; // Display the truncated 'Hankkeen_luokitus' with full text as tooltip
        echo "<a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $cleanedUutinen . "</a>, ";        
        echo "</div><br>";
    }
} else {
    echo "0 results";
}
$conn->close();

?>