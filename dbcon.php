<?php

    $username="marfil";
    $dbname="bikonomi_1";
    $password="Marfil@123";

    $dbservername = "5.181.217.90";
    $dbusername = $username;
    $dbpassword = $password;
    $mysqldbname = $dbname;
    // Create connection
    $conn = new mysqli($dbservername, $dbusername, $dbpassword,$mysqldbname);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
    }
    
?>