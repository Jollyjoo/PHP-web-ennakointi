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
    // Fetch all content if 'koko-häme' is selected
    $sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Url 
            FROM catbxjbt_ennakointi.Mediaseuranta
            ORDER BY uutisen_pvm DESC
            LIMIT $start, 20;";
} else {
    // Fetch content filtered by 'Maakunta_ID'
    $sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Url 
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

        echo "<div class='record'>";
        echo "" . $formattedDate . ", "; // Display the formatted date
   
        echo "<a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $cleanedUutinen . "</a>, ";
        echo "</div><br>";
    }
} else {
    echo "Ei tuloksia";
}
$conn->close();
?>