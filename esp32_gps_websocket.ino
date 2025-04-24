#include <WiFi.h>
#include <WebSocketsClient.h>
#include <ArduinoJson.h> // For creating JSON payloads

// Include your GPS library (e.g., TinyGPS++)
#include <TinyGPS++.h>
#include <HardwareSerial.h> // Or SoftwareSerial if needed

// --- WiFi Settings ---
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
// ---------------------

// --- WebSocket Server Settings ---
// Replace with the IP address of the computer running the PHP WebSocket server
const char* websocket_server_host = "192.168.1.100"; // Example: Your PC's local IP
const uint16_t websocket_server_port = 8080; // Must match the port in websocket_server.php
// -------------------------------

// --- GPS Settings ---
// Adjust RX/TX pins based on your ESP32 board and GPS connection
static const int RXPin = 16, TXPin = 17;
static const uint32_t GPSBaud = 9600;
TinyGPSPlus gps;
HardwareSerial gpsSerial(1); // Use Hardware Serial 1 (pins 16, 17 on many ESP32 boards)
// --------------------

// --- Device Identification ---
// You should uniquely identify this ESP32/bike
const int bike_id = 123; // Example Bike ID
// ---------------------------

WebSocketsClient webSocket;
bool isConnected = false;

unsigned long lastSendTime = 0;
const unsigned long sendInterval = 5000; // Send GPS data every 5 seconds (5000 ms)

void webSocketEvent(WStype_t type, uint8_t * payload, size_t length) {
    switch(type) {
        case WStype_DISCONNECTED:
            Serial.printf("[WSc] Disconnected!\n");
            isConnected = false;
            break;
        case WStype_CONNECTED:
            Serial.printf("[WSc] Connected to url: %s\n", payload);
            isConnected = true;
            // Optional: Send an initial identification message
            // webSocket.sendTXT("{\"type\":\"connect\", \"bike_id\":123}");
            break;
        case WStype_TEXT:
            Serial.printf("[WSc] get text: %s\n", payload);
            // Handle any messages received from the server if needed
            break;
        case WStype_BIN:
            Serial.printf("[WSc] get binary length: %u\n", length);
            // Handle binary data if needed
            break;
        case WStype_ERROR:
        case WStype_FRAGMENT_TEXT_START:
        case WStype_FRAGMENT_BIN_START:
        case WStype_FRAGMENT:
        case WStype_FRAGMENT_FIN:
            break;
    }
}

void setup() {
    Serial.begin(115200);
    gpsSerial.begin(GPSBaud, SERIAL_8N1, RXPin, TXPin);
    Serial.println("ESP32 GPS WebSocket Client");

    // Connect to WiFi
    Serial.printf("Connecting to %s ", ssid);
    WiFi.begin(ssid, password);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println(" Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());

    // Configure WebSocket client
    webSocket.begin(websocket_server_host, websocket_server_port, "/"); // Path is usually "/"
    webSocket.onEvent(webSocketEvent);
    webSocket.setReconnectInterval(5000); // Try to reconnect every 5 seconds if connection is lost
}

void loop() {
    webSocket.loop(); // Must be called in loop()

    // Process GPS data
    while (gpsSerial.available() > 0) {
        if (gps.encode(gpsSerial.read())) {
            // GPS sentence received and parsed
        }
    }

    // Check if it's time to send data and if connected
    unsigned long currentTime = millis();
    if (isConnected && (currentTime - lastSendTime >= sendInterval)) {
        if (gps.location.isValid() && gps.location.isUpdated()) {
            float latitude = gps.location.lat();
            float longitude = gps.location.lng();

            Serial.printf("Sending GPS: Lat=%.6f, Lon=%.6f\n", latitude, longitude);

            // Create JSON payload
            StaticJsonDocument<200> doc; // Adjust size as needed
            doc["bike_id"] = bike_id;
            doc["latitude"] = latitude;
            doc["longitude"] = longitude;
            // Add other data if needed (e.g., speed, altitude, timestamp)
            // doc["speed"] = gps.speed.kmph();
            // doc["altitude"] = gps.altitude.meters();
            // doc["timestamp"] = gps.date.value() > 0 ? String(gps.date.year()) + "-" + String(gps.date.month()) + "-" + String(gps.date.day()) + " " + String(gps.time.hour()) + ":" + String(gps.time.minute()) + ":" + String(gps.time.second()) : "";


            String output;
            serializeJson(doc, output);

            // Send JSON data over WebSocket
            webSocket.sendTXT(output);

            lastSendTime = currentTime; // Update last send time
        } else {
             Serial.println("Waiting for valid GPS fix...");
        }
         // Reset GPS update flags if needed by your logic
         // gps.location.isUpdated() might need careful handling depending on how often you check
    }

    // Small delay to prevent busy-waiting (optional)
    delay(10);
}