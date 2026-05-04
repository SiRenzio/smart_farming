#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <Preferences.h>
#include <DNSServer.h>

/* ===================== SERVER URLs ===================== */
const char* webServerIp = "192.168.1.2";

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
    
    // RESTART 1: Changing from AP Mode to Station Mode
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

    String status = resDoc["status"];

    if (status == "success") {
      userID         = resDoc["userID"];
      SoilSensorID   = resDoc["SoilSensorID"];
      locationID     = resDoc["locationID"];
      deviceVerified = true;
      sendingEnabled = true;
      Serial.printf("[SYNC] Active! UserID: %d, SensorID: %d, LocID: %d\n", userID, SoilSensorID, locationID);
    } 
    else if (status == "disconnected") {
      deviceVerified = true; 
      sendingEnabled = false;

      String message = resDoc["message"].as<String>();
      Serial.print("[SERVER]: ");
      Serial.println(message);
      Serial.println("[SYNC] Idle: Administrative Disconnect");
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
  }

  http.end();
}

/* ===================== MAIN SETUP ===================== */
void setup() {
  Serial.begin(115200);

  preferences.begin("wifi_creds", true); 
  String savedSSID = preferences.getString("ssid", "");
  String savedPass = preferences.getString("pass", "");
  preferences.end();

  if (savedSSID != "") {
    Serial.println("\n[WiFi] Found saved network. Connecting to: " + savedSSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(savedSSID.c_str(), savedPass.c_str());

    int timeout = 20; // 10 seconds total wait
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

  randomSeed(analogRead(0));
}

/* ===================== MAIN LOOP ===================== */
void loop() {
  if (inAPMode) {
    // AP MODE: Handle Captive Portal setup
    dnsServer.processNextRequest();
    server.handleClient();
  } else {
    // NORMAL MODE: Running standard sensor operations
    server.handleClient();

    // RESTART 2: Changing from Station Mode back to AP Mode if network is lost
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("\n[WiFi] Network lost! Restarting to switch to AP mode...");
      delay(3000);
      ESP.restart(); 
    }

    unsigned long currentMillis = millis();
    if (currentMillis - lastSendTime >= sendInterval) {
      lastSendTime = currentMillis;
      verifyDeviceWithServer();
      if (deviceVerified && sendingEnabled) {
        sendSensorData();
      }
    }
  }
}