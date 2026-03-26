#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <SoftwareSerial.h>

const uint8_t TX_PIN = 18;
const uint8_t RX_PIN = 5; 

SoftwareSerial mySerial(TX_PIN, RX_PIN);

/* ===================== SERVER URLs ===================== */
const char* webServerIp = "172.18.0.9";

String verifyDeviceURL = "http://" + String(webServerIp) + "/smart_farming/webServer.php";
String sendDataURL     = "http://" + String(webServerIp) + "/smart_farming/api/sensor_api.php";

/* ===================== WIFI CREDENTIALS ===================== */
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
WebServer server(80);
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
      // Added a print to confirm sync only when it changes or just keep it for debugging
      // Serial.printf("[SYNC] Active! UserID: %d, SensorID: %d, LocID: %d\n", userID, SoilSensorID, locationID);
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
// Updated to accept real sensor parameters
void sendSensorData(float soilN, float soilP, float soilK, float soilEC, float soilpH, float soilT, float soilM) {

  if (!deviceVerified || !sendingEnabled) return;

  StaticJsonDocument<400> doc;
  doc["userID"]       = userID;
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
  } else {
    Serial.printf("[ESP32] Failed to send data, error: %s\n", http.errorToString(httpCode).c_str());
  }

  http.end();
}

void setup() {
  Serial.begin(115200);
  mySerial.begin(4800);

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
}

void loop() {
  server.handleClient();

  unsigned long currentMillis = millis();

  // Trigger every 5 seconds
  if (currentMillis - lastSendTime >= sendInterval) {
    lastSendTime = currentMillis;
    
    verifyDeviceWithServer();
    
    // Read sensor and send data ONLY if verified and enabled
    if (deviceVerified && sendingEnabled) {
      
      byte queryData[] = {0x01, 0x03, 0x00, 0x00, 0x00, 0x07, 0x04, 0x08};
      byte receivedData[19];
      
      // Request data from sensor
      mySerial.write(queryData, sizeof(queryData));
      
      // Brief delay to allow the sensor to respond over RS485/Modbus
      delay(100); 
      
      if (mySerial.available() >= sizeof(receivedData)) {
         mySerial.readBytes(receivedData, sizeof(receivedData));

         // Parse raw Modbus data
         unsigned int soilMoisture    = (receivedData[3] << 8) | receivedData[4];
         unsigned int soilTemperature = (receivedData[5] << 8) | receivedData[6];
         unsigned int soilEC          = (receivedData[7] << 8) | receivedData[8];
         unsigned int soilPH          = (receivedData[9] << 8) | receivedData[10];
         unsigned int soilNitrogen    = (receivedData[11] << 8) | receivedData[12];
         unsigned int soilPhosphorous = (receivedData[13] << 8) | receivedData[14];
         unsigned int soilPotassium   = (receivedData[15] << 8) | receivedData[16];

         // Convert required fields to floats
         float soilMoisturef = soilMoisture / 10.0;
         float soilPHf       = soilPH / 10.0;
         float soilTempf     = soilTemperature / 10.0;
         
         Serial.printf("\n[SENSOR] M:%.1f T:%.1f EC:%d pH:%.1f N:%d P:%d K:%d\n", 
                        soilMoisturef, soilTempf, soilEC, soilPHf, soilNitrogen, soilPhosphorous, soilPotassium);

         // send the data
         sendSensorData(soilNitrogen, soilPhosphorous, soilPotassium, soilEC, soilPHf, soilTempf, soilMoisturef);
      } else {
         Serial.println("[ESP32] Error: Sensor did not reply in time or incomplete data.");
      }
    }
  }
}