#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== SERVER URLs ===================== */
const char* verifyDeviceURL = "http://172.18.0.9/smart_farming/check_if_online_offline.php";
const char* sendDataURL     = "http://172.18.0.9/smart_farming/sensor_api.php";

/* ===================== WIFI CREDENTIALS ===================== */
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
WebServer server(80); // Initialize WebServer on port 80
bool wifiConnected = false;

bool deviceVerified = false;
bool sendingEnabled = false;

int SoilSensorID = -1;
int locationID   = -1;

unsigned long lastSendTime = 0;
const unsigned long sendInterval = 5000;

/* ===================== RECEIVE HANDLER (For PHP sending.php) ===================== */
void handleReceive() {

  if (!server.hasArg("plain")) {
    server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"No request body\"}");
    return;
  }

  String body = server.arg("plain");

  Serial.println("\n[ESP32] Data received from PHP:");
  Serial.println(body);

  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, body);

  if (error) {
    server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"Invalid JSON\"}");
    return;
  }

  // disconnection 
  if (doc.containsKey("command")) {

    String command = doc["command"];

    if (command == "disconnect") {
      deviceVerified = false;
      sendingEnabled = false;
      SoilSensorID   = -1;
      locationID     = -1;

      Serial.println("[ESP32] DISCONNECT command received");
      Serial.println("[ESP32] Data sending paused and IDs cleared");

      server.send(200, "application/json", "{\"status\":\"disconnected\"}");
      return;
    }
  }

  // send sensor and location ID to connect
  if (doc.containsKey("SoilSensorID") && doc.containsKey("locationID")) {

    SoilSensorID   = doc["SoilSensorID"];
    locationID     = doc["locationID"];
    deviceVerified = true;
    sendingEnabled = true;

    Serial.println("[ESP32] Sensor and Location IDs received");
    Serial.println("[ESP32] Data sending resumed");

    server.send(200, "application/json", "{\"status\":\"assigned\"}");
    return;
  }

  server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"Invalid payload\"}");
}

/* ===================== VERIFY DEVICE (Client Mode) ===================== */
void verifyDeviceWithServer() {

  HTTPClient http;

  StaticJsonDocument<200> doc;
  doc["ipAddress"]  = WiFi.localIP().toString();
  doc["macAddress"] = WiFi.macAddress();

  String payload;
  serializeJson(doc, payload);

  http.begin(verifyDeviceURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);

  if (httpCode > 0) {

    String response = http.getString();
    Serial.print("[Server Response]: ");
    Serial.println(response);

    if (httpCode == 200) {
      StaticJsonDocument<200> resDoc;
      deserializeJson(resDoc, response);

      if (resDoc.containsKey("SoilSensorID")) {
        SoilSensorID   = resDoc["SoilSensorID"];
        locationID     = resDoc["locationID"];
        deviceVerified = true;
        sendingEnabled = true;
      }
    }
  }

  http.end();
}

/* ===================== SEND SENSOR DATA (Dummy Data) ===================== */
void sendSensorData() {

  if (!deviceVerified || !sendingEnabled) return;

  float soilN  = random(200, 1000) / 10.0;
  float soilP  = random(200, 1000) / 10.0;
  float soilK  = random(200, 1000) / 10.0;
  float soilEC = random(10, 40)   / 10.0;
  float soilpH = random(10, 140)  / 10.0;
  float soilT  = random(100, 1000)/ 10.0;
  float soilM  = random(100, 1000)/ 10.0;
  float soilLV = random(10, 100)  / 10.0;

  StaticJsonDocument<400> doc;
  doc["SoilSensorID"] = SoilSensorID;
  doc["locationID"]   = locationID;
  doc["soilN"]  = soilN;
  doc["soilP"]  = soilP;
  doc["soilK"]  = soilK;
  doc["soilEC"] = soilEC;
  doc["soilpH"] = soilpH;
  doc["soilT"]  = soilT;
  doc["soilM"]  = soilM;
  doc["soilLV"] = soilLV;

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(sendDataURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);
  if (httpCode > 0) {
    Serial.print("[ESP32] Data sent. Server says: ");
    Serial.println(http.getString());
  }

  http.end();
}

/* ===================== SETUP ===================== */
void setup() {

  Serial.begin(115200);

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  wifiConnected = true;
  Serial.println("\n[ESP32] Connected! IP: " + WiFi.localIP().toString());

  // server
  server.on("/receive", HTTP_POST, handleReceive);
  server.begin();
  Serial.println("[ESP32] Listener server started.");

  randomSeed(analogRead(0));

  Serial.print("[ESP32] MAC Address: ");
  Serial.println(WiFi.macAddress());

  Serial.println("[ESP32] Waiting for sensor and location ID's");
}

/* ===================== LOOP ===================== */
void loop() {

  server.handleClient();

  if (wifiConnected) {

    unsigned long currentMillis = millis();

    if (currentMillis - lastSendTime >= sendInterval) {
      lastSendTime = currentMillis;

      // 
      verifyDeviceWithServer();

      // send data if connected
      if (deviceVerified && sendingEnabled) {
        sendSensorData();
      }
    }
  }

  delay(100);
}
