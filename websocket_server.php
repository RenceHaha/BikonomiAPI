<?php
// Important: Run this script from the command line: php websocket_server.php
// Requires Ratchet: composer require cboden/ratchet

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Include db connection setup *once* initially, but be aware of potential staleness
// A better approach might involve injecting a connection manager into GpsHandler
require_once 'dbcon.php'; // Use require_once for db connection setup

require dirname(__DIR__) . '/BikonomiAPI/vendor/autoload.php';

class GpsHandler implements MessageComponentInterface {
    protected $clients;
    // Optional: Inject DB connection here for better management
    // protected $dbConn;
    // public function __construct($dbConn = null) {
    //     $this->clients = new \SplObjectStorage;
    //     $this->dbConn = $dbConn; // Store injected connection
    //     echo "WebSocket Server Started...\n";
    // }

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "WebSocket Server Started...\n";
    }


    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo sprintf('Received message from %d: %s' . "\n", $from->resourceId, $msg);

        // Decode the message (assuming JSON from ESP32)
        $data = json_decode($msg, true);

        // Check if data is valid and contains required fields
        if ($data && isset($data['latitude']) && isset($data['longitude']) && isset($data['bike_serial_gps'])) {

            $latitude = $data['latitude'];
            $longitude = $data['longitude'];
            $bike_serial_gps = $data['bike_serial_gps']; // varchar(100)

            echo "GPS Received: Lat=$latitude, Lon=$longitude, BikeSerialGps=$bike_serial_gps\n";

            // --- Database Update Logic ---
            global $conn; // Access the global connection variable from dbcon.php

            // Basic check/reconnect attempt (not ideal, but simple)
            if (!$conn || !$conn->ping()) {
                echo "Database connection lost. Attempting to reconnect...\n";
                // Close existing potentially broken connection
                if ($conn) {
                    $conn->close();
                }
                // Re-include dbcon to re-establish connection
                // Note: This assumes dbcon.php sets the global $conn variable
                require 'dbcon.php';
                if (!$conn || !$conn->ping()) {
                     echo "Failed to reconnect to database. Skipping update.\n";
                     // Optional: Broadcast the location anyway, or handle error differently
                     // foreach ($this->clients as $client) { ... }
                     return; // Exit onMessage if DB connection failed
                }
                 echo "Database reconnected.\n";
            }


            // Prepare the SQL statement to update the bike location
            $sql = "UPDATE bike_location SET latitude = ?, longitude = ?, last_updated = NOW() WHERE bike_serial_gps = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                // Bind parameters: 'd' for double (latitude/longitude), 's' for string (bike_serial_gps)
                $stmt->bind_param("dds", $latitude, $longitude, $bike_serial_gps);

                // Execute the statement
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo "Successfully updated location for BikeSerialGps: $bike_serial_gps\n";
                    } else {
                        // This means the bike_serial_gps was not found in the table
                        echo "Warning: No location record found to update for BikeSerialGps: $bike_serial_gps. Consider INSERTING if it should exist.\n";
                        // Optional: Insert a new record if it doesn't exist
                        // $insertSql = "INSERT INTO bike_location (bike_serial_gps, latitude, longitude, last_updated) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude), last_updated=NOW()";
                        // $insertStmt = $conn->prepare($insertSql);
                        // ... bind and execute insert ...
                    }
                } else {
                    echo "Error executing database update for BikeSerialGps: $bike_serial_gps - " . $stmt->error . "\n";
                }
                // Close the statement
                $stmt->close();
            } else {
                 echo "Error preparing database statement: " . $conn->error . "\n";
            }
            // --- End Database Update Logic ---


            // Optional: Broadcast the location to other connected clients (e.g., a monitoring dashboard)
            // Only broadcast if the update was successful or if you want to broadcast regardless of DB status
            foreach ($this->clients as $client) {
                if ($from !== $client) {
                    // Don't send the message back to the sender
                    $client->send($msg);
                }
            }
        } else {
            echo "Received invalid or incomplete data format: $msg\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Run the server application on port 8080
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new GpsHandler()
        )
    ),
    8080,
    '5.181.217.90'
);
echo "Starting WebSocket server on port 8080...\n";
$server->run();
?>