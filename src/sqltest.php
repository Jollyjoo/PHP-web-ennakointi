<?php
/* 
$q = $_REQUEST["q"];
 */

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

$sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Url 
FROM catbxjbt_ennakointi.Mediaseuranta
where Maakunta_ID = (SELECT maakunta_id from dbo.maakunnat where maakunta LIKE '%" . $q . "%')
                order by uutisen_pvm DESC;";


$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Aika: " . $row["aika"] . ", ";
        echo "Maakunta ID: " . $row["Maakunta_ID"] . ", ";
        echo "Teema: " . $row["Teema"] . ", ";
        echo "Uutinen: " . $row["Uutinen"] . ", ";
        echo "Url: " . $row["Url"] . "<br>";
    }
} else {
    echo "0 results";
}
$conn->close();



function FormatErrors($errors)
{
    /* Display errors. */
    echo "Error information: ";

    foreach ($errors as $error) {
        echo "SQLSTATE: " . $error['SQLSTATE'] . "";
        echo "Code: " . $error['code'] . "";
        echo "Message: " . $error['message'] . "";
    }
}




?>