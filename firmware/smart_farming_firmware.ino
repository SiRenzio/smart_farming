#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== SERVER URLs ===================== */
const char* webServerIp = "172.18.0.9";

String verifyDeviceURL = "http://" + String(webServerIp) + "/smart_farming/webServer.php";
String sendDataURL     = "http://" + String(webServerIp) + "/smart_farming/api/sensor_api.php";

/* ===================== WIFI CREDENTIALS ===================== */
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
WebServer server(80); // RE-ADDED: Declaring the server object
bool deviceVerified = false;
bool sendingEnabled = false;

int userID       = -1;
int SoilSensorID = -1;
int locationID   = -1;

unsigned long lastSendTime = 0;
const unsigned long sendInterval = 5000; 

/* ===================== RECEIVE HANDLER (Debug Only) ===================== */
void handleReceive() {
  if (!server.hasArg("plain")) {
    server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"No request body\"}");
    return;
  }

  String body = server.arg("plain");
  Serial.println("\n[DEBUG] Manual data received:");
  Serial.println(body);

  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, body);

  if (error) {
    server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"Invalid JSON\"}");
    return;
  }

  // Update IDs manually via debug if provided
  if (doc.containsKey("SoilSensorID") && doc.containsKey("locationID") && doc.containsKey("userID")) {
    userID         = doc["userID"];
    SoilSensorID   = doc["SoilSensorID"];
    locationID     = doc["locationID"];
    deviceVerified = true;
    sendingEnabled = true;
    Serial.println("[DEBUG] IDs Updated Manually");
    server.send(200, "application/json", "{\"status\":\"debug_assigned\"}");
    return;
  }

  server.send(200, "application/json", "{\"status\":\"received\"}");
}

/* ===================== VERIFY & SYNC (The Pull Method) ===================== */
void verifyDeviceWithServer() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  StaticJsonDocument<200> doc;
  doc["macAddress"] = WiFi.macAddress();
  doc["ipAddress"]  = WiFi.localIP().toString();

  String payload;
  serializeJson(doc, payload);

  http.begin(verifyDeviceURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);

  if (httpCode == 200) {
    String response = http.getString();
    StaticJsonDocument<300> resDoc;
    deserializeJson(resDoc, response);

    String status = resDoc["status"];

    if (status == "success") {
      userID         = resDoc["userID"];
      SoilSensorID   = resDoc["SoilSensorID"];
      locationID     = resDoc["locationID"];
      deviceVerified = true;
      sendingEnabled = true;
      Serial.printf("[SYNC] Active! SensorID: %d, SensorID: %d, LocID: %d\n", userID, SoilSensorID, locationID);
    } 
    else if (status == "disconnected") {
      deviceVerified = true; 
      sendingEnabled = false;

      String message = resDoc["message"].as<String>();
      Serial.print("[SERVER]: ");
      Serial.println(message);
      Serial.println("[SYNC] Idle: Administrative Disconnect (isConnected = 0)");
    }
    else {
      deviceVerified = false;
      sendingEnabled = false;
      Serial.println("[ESP32] UNREGISTERED: Waiting for registration");
    }
  }
  http.end();
}

/* ===================== SEND SENSOR DATA ===================== */
void sendSensorData() {

  if (!deviceVerified || !sendingEnabled) return;

  float soilN  = random(200, 1000) / 10.0;
  float soilP  = random(200, 1000) / 10.0;
  float soilK  = random(200, 1000) / 10.0;
  float soilEC = random(10, 40)   / 10.0;
  float soilpH = random(10, 140)  / 10.0;
  float soilT  = random(100, 1000)/ 10.0;
  float soilM  = random(100, 1000)/ 10.0;

  StaticJsonDocument<400> doc;
  doc["userID"] = userID;
  doc["SoilSensorID"] = SoilSensorID;
  doc["locationID"]   = locationID;
  doc["soilN"]  = soilN;
  doc["soilP"]  = soilP;
  doc["soilK"]  = soilK;
  doc["soilEC"] = soilEC;
  doc["soilpH"] = soilpH;
  doc["soilT"]  = soilT;
  doc["soilM"]  = soilM;

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

void setup() {
  Serial.begin(115200);

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
  Serial.println("[WiFi] Connected! MAC: " + WiFi.macAddress());
  
  server.on("/receive", HTTP_POST, handleReceive);
  server.begin();
  Serial.println("[ESP32]: Sensor is ONLINE . READY TO DEPLOY.");

  randomSeed(analogRead(0));
}

void loop() {
  server.handleClient();

  unsigned long currentMillis = millis();

  if (currentMillis - lastSendTime >= sendInterval) {
    lastSendTime = currentMillis;
    verifyDeviceWithServer();
    if (deviceVerified && sendingEnabled) {
      sendSensorData();
    }
  }
}