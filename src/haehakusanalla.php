<?php


$servername = "tulevaisuusluotain.fi";
$username = "catbxjbt_Christian";
$password = "Juustonaksu5";
$dbname = "catbxjbt_ennakointi";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$q = $_GET['q'];

/* $sql = "SELECT CONVERT(CHAR(8),[uutisen_pvm],112) as aika, Maakunta_ID, Teema, Uutinen, Url 
                FROM Mediaseuranta
                where Uutinen LIKE '%" . $q . "%'
                order by uutisen_pvm DESC;"; */

            $sql = "SELECT uutisen_pvm as aika, Maakunta_ID, Teema, Uutinen, Url 
                FROM catbxjbt_ennakointi.Mediaseuranta
                where Uutinen LIKE '%" . $q . "%'
                order by uutisen_pvm DESC;";
                

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Result: " . $row["your_column"];
    }
} else {
    echo "0 results";
}
$conn->close();

function FormatErrors( $errors )
{
    /* Display errors. */
    echo "Error information: ";

    foreach ( $errors as $error )
    {
        echo "SQLSTATE: ".$error['SQLSTATE']."";
        echo "Code: ".$error['code']."";
        echo "Message: ".$error['message']."";
    }
}

?>