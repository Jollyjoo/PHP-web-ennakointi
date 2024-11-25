<?php

    $q = $_REQUEST["q"];
    

    $serverName = "ennakointi-srv.database.windows.net"; // update me
    $connectionOptions = array(
        "Database" => "EnnakointiDB", // update me
        "Uid" => "Christian", // update me
        "PWD" => "Ennakointi24" // update me
    );
    //Establishes the connection
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if( $conn === false ) {
        die( print_r( sqlsrv_errors(), true));
    }
    else {
        echo"Connection Success: connected!";
    }

    $tsql= "SELECT CONVERT(CHAR(8),[uutisen_pvm],112) as aika, Maakunta_ID, Teema, Uutinen, Url 
                FROM dbo.Mediaseuranta
                where Maakunta_ID = (SELECT maakunta_id from dbo.maakunnat where maakunta LIKE '%" . $q . "%')
                order by uutisen_pvm;";
    $getResults= sqlsrv_query($conn, $tsql);
    echo ("Reading data from table <br>" . PHP_EOL);
    if ($getResults == FALSE)
        die(FormatErrors(sqlsrv_errors()));
    while ($row = sqlsrv_fetch_array($getResults, SQLSRV_FETCH_ASSOC)) {
        echo ("<b>" . $row['aika'] . " " . $row['Teema'] . "</b> " . $row['Uutinen'] . " " . $row['Url'] . "<br>" . PHP_EOL);
    }
    sqlsrv_free_stmt($getResults);



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