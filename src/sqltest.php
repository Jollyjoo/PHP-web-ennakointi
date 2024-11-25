<?php
    $serverName = "ennakointi-srv.database.windows.net"; // update me
    $connectionOptions = array(
        "Database" => "EnnakointiDB", // update me
        "Uid" => "Christian", // update me
        "PWD" => "Ennakointi24" // update me
    );
    //Establishes the connection
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    $tsql= "SELECT TOP 20 Uuutinen as Uutinen, Teema as Teema
         FROM Mediaseuranta";
    $getResults= sqlsrv_query($conn, $tsql);
    echo ("Reading data from table" . PHP_EOL);
    if ($getResults == FALSE)
        echo (sqlsrv_errors());
    while ($row = sqlsrv_fetch_array($getResults, SQLSRV_FETCH_ASSOC)) {
     echo ($row['Uutinen'] . " " . $row['Teema'] . PHP_EOL);
    }
    sqlsrv_free_stmt($getResults);
?>