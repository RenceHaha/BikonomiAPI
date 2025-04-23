<?php
// Important: Run this script from the command line: php websocket_server.php
// Requires Ratchet: composer require cboden/ratchet

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require dirname(__DIR__) . '/BikonomiAPI/vendor/autoload.php'; // Adjust path if needed

class GpsHandler implements MessageComponentInterface {
    protected $clients;

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

        if ($data && isset($data['latitude']) && isset($data['longitude'])) {
            // Process the GPS data here
            // Example: Store it in the database, associate with a bike_id, etc.
            $latitude = $data['latitude'];
            $longitude = $data['longitude'];
            $bikeId = $data['bike_id'] ?? null; // Optional: Include bike ID from ESP32

            echo "GPS Received: Lat=$latitude, Lon=$longitude" . ($bikeId ? ", BikeID=$bikeId" : "") . "\n";

            // You might want to update a bike's location in your `bike_tbl` or a dedicated location table.
            // Example DB interaction (pseudo-code, needs proper implementation with dbcon.php logic)
            /*
            global $conn; // Need to manage DB connection scope carefully in a long-running process
            if ($bikeId && $conn) {
                $sql = "UPDATE bike_location_tbl SET latitude = ?, longitude = ?, last_updated = NOW() WHERE bike_id = ?";
                // Prepare, bind, execute...
            }
            */

            // Optional: Broadcast the location to other connected clients (e.g., a monitoring dashboard)
            // foreach ($this->clients as $client) {
            //     if ($from !== $client) {
            //         // Don't send the message back to the sender
            //         $client->send($msg);
            //     }
            // }
        } else {
            echo "Received invalid data format.\n";
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