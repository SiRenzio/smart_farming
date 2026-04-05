#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <SoftwareSerial.h>
#include <Preferences.h>
#include <DNSServer.h>

/* ===================== SENSOR SETUP ===================== */
const uint8_t TX_PIN = 18;
const uint8_t RX_PIN = 5; 
SoftwareSerial mySerial(TX_PIN, RX_PIN);

/* ===================== SERVER URLs ===================== */
// Update this to your current server IP if necessary
const char* webServerIp = "192.168.1.6"; 

String verifyDeviceURL = "http://" + String(webServerIp) + "/smart_farming/webServer.php";
String sendDataURL     = "http://" + String(webServerIp) + "/smart_farming/api/sensor_api.php";

/* ===================== GLOBALS ===================== */
WebServer server(80);
Preferences preferences;
DNSServer dnsServer;

bool deviceVerified = false;
bool sendingEnabled = false;
bool inAPMode       = false; 

int userID       = -1;
int SoilSensorID = -1;
int locationID   = -1;

unsigned long lastSendTime = 0;
const unsigned long sendInterval = 5000; 

const byte DNS_PORT = 53;

/* ===================== CAPTIVE PORTAL (WIFI SETUP) ===================== */
const char* wifiSetupPage = R"rawliteral(
<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:Arial;text-align:center;margin-top:50px;} 
input{margin:10px;padding:10px;width:80%;max-width:300px;border-radius:5px;border:1px solid #ccc;}
button{padding:10px 20px;background:#28a745;color:white;border:none;border-radius:5px;cursor:pointer;}
</style></head><body><h2>ESP32 WiFi Setup</h2>
<form action="/save" method="POST">
<input type="text" name="ssid" placeholder="WiFi SSID" required><br>
<input type="password" name="pass" placeholder="WiFi Password"><br>
<button type="submit">Enter Credential</button>
</form></body></html>
)rawliteral";

void handleRoot() {
  server.send(200, "text/html", wifiSetupPage);
}

void handleSave() {
  if (server.hasArg("ssid") && server.hasArg("pass")) {
    String newSSID = server.arg("ssid");
    String newPass = server.arg("pass");

    preferences.begin("wifi_creds", false);
    preferences.putString("ssid", newSSID);
    preferences.putString("pass", newPass);
    preferences.end();

    server.send(200, "text/html", "<h2>Credentials Saved!</h2><p>ESP32 is restarting to connect...</p>");
    delay(2000);
    
    // AP to STA
    ESP.restart(); 
  } else {
    server.send(400, "text/plain", "Missing SSID or Password");
  }
}

void startAPMode() {
  Serial.println("\n[WiFi] Starting Access Point Mode...");
  WiFi.mode(WIFI_AP);
  WiFi.softAP("ESP32-SmartFarming-Setup"); 

  dnsServer.start(DNS_PORT, "*", WiFi.softAPIP());

  server.on("/", HTTP_GET, handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.onNotFound(handleRoot); 
  server.begin();

  inAPMode = true;
  Serial.println("[WiFi] AP Mode Active. Connect to 'ESP32-SmartFarming-Setup' to configure.");
  Serial.print("[WiFi] AP IP address: ");
  Serial.println(WiFi.softAPIP());
}

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

/* ===================== VERIFY & SYNC ===================== */
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

    // restart the esp32
    if (resDoc.containsKey("isRestart") && resDoc["isRestart"] == 1) {
      Serial.println("[SYSTEM] Restart command received from server. Rebooting...");
      delay(1500); // Give serial monitor a moment to print
      ESP.restart(); 
    }

    String status = resDoc["status"];

    if (status == "success") {
      userID         = resDoc["userID"];
      SoilSensorID   = resDoc["SoilSensorID"];
      locationID     = resDoc["locationID"];
      deviceVerified = true;
      sendingEnabled = true;
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

/* ===================== MAIN SETUP ===================== */
void setup() {
  Serial.begin(115200);
  mySerial.begin(4800);

  preferences.begin("wifi_creds", true); 
  String savedSSID = preferences.getString("ssid", "");
  String savedPass = preferences.getString("pass", "");
  preferences.end();

  if (savedSSID != "") {
    Serial.println("\n[WiFi] Found saved credentials. Connecting to: " + savedSSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(savedSSID.c_str(), savedPass.c_str());

    int timeout = 20;
    while (WiFi.status() != WL_CONNECTED && timeout > 0) {
      delay(500);
      Serial.print(".");
      timeout--;
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
      Serial.println("[WiFi] Connected! MAC: " + WiFi.macAddress());
      
      server.on("/receive", HTTP_POST, handleReceive);
      server.begin();
      Serial.println("[ESP32]: Sensor is ONLINE . READY TO DEPLOY.");
      inAPMode = false;
    } else {
      Serial.println("\n[WiFi] Failed to connect to saved network.");
      startAPMode(); 
    }
  } else {
    Serial.println("\n[WiFi] No credentials saved.");
    startAPMode(); 
  }
}

/* ===================== MAIN LOOP ===================== */
void loop() {
  if (inAPMode) {
    // AP Mode 
    dnsServer.processNextRequest();
    server.handleClient();
  } else {
    server.handleClient();

    // change to AP Mode
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("\n[WiFi] Network lost! Restarting to switch to AP mode...");
      delay(3000);
      ESP.restart(); 
    }

    unsigned long currentMillis = millis();

    // Trigger every 5 seconds
    if (currentMillis - lastSendTime >= sendInterval) {
      lastSendTime = currentMillis;
      
      verifyDeviceWithServer();
      
      // Activate sensor it connected 
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

           // forward data to payload
           sendSensorData(soilNitrogen, soilPhosphorous, soilPotassium, soilEC, soilPHf, soilTempf, soilMoisturef);
        } else {
           Serial.println("[ESP32] Error: Sensor did not reply in time or incomplete data.");
        }
      }
    }
  }
}