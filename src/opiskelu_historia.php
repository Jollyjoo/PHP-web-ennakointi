<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'info.php'; // Contains DB connection info

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed."]);
    exit();
}

// Get the last 10 years (descending)
$sql = "SELECT vuosi, toisen_aste, korkea_aste, amk, yo FROM Opiskelu ORDER BY vuosi DESC LIMIT 10";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed."]);
    $conn->close();
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "vuosi" => $row["vuosi"],
        "toisen_aste" => $row["toisen_aste"],
        "korkea_aste" => $row["korkea_aste"],
        "amk" => $row["amk"],
        "yo" => $row["yo"]
    ];
}

$conn->close();
// Return in ascending order for charts
$data = array_reverse($data);
echo json_encode($data);
?>