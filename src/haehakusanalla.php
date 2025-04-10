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

$sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Url 
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

        echo "<div class='record'>";
        echo "" . $formattedDate . ", "; // Display the formatted date
        /*   echo "Maakunta ID: " . $row["Maakunta_ID"] . ", ";
          echo "Teema: " . $row["Teema"] . ", "; */
    /*     echo "Uutinen: " . $row["Uutinen"] . ", "; */
        echo "<a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $cleanedUutinen . "</a>, ";        
/*         echo "Url: <a href='" . $row["Url"] . "' target='_blank' class='styled-link'>" . $row["Url"] . "</a>";
 */        echo "</div><br>";
    }
} else {
    echo "0 results";
}
$conn->close();

?>