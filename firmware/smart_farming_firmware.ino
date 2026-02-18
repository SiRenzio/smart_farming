#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== SENSOR & MOTOR PINS ===================== */
#define trigPin1 21
#define echoPin1 19
#define switch1 23
#define mixermotor1 22
#define pumpmotor1 15

/* ===================== SERVER URLs ===================== */
const char* webServerIp = "172.18.0.9";
String verifyDeviceURL = "http://" + String(webServerIp) + "/smart_farming/webServer.php";
String sendDataURL     = "http://" + String(webServerIp) + "/smart_farming/api/sensor_api.php";
String sendWateringURL = "http://" + String(webServerIp) + "/smart_farming/api/watering_api.php";

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

unsigned long lastPingTime = 0;
const unsigned long pingInterval = 100; 

unsigned long lastLevelSendTime = 0;
const unsigned long levelSendInterval = 5000; // Send every 5 seconds

/* ===================== SENSOR VARIABLES ===================== */
long duration1;
int distance1;
int finaldistance1;

int wateringstatus = -1;
int wateringflag = -1;  
int mixingflag = 0;     
int mixingstatus = 0;   
unsigned long mixStartTime = 0;
int liquidsensorID = 1; 

/* ===================== RECEIVE HANDLER (Debug Only) ===================== */
void handleReceive() {
  if (!server.hasArg("plain")) {
    server.send(400, "application/json", "{\"status\":\"error\",\"message\":\"No request body\"}");
    return;
  }
  String body = server.arg("plain");
  StaticJsonDocument<200> doc;
  deserializeJson(doc, body);

  if (doc.containsKey("SoilSensorID") && doc.containsKey("locationID") && doc.containsKey("userID")) {
    userID       = doc["userID"];
    SoilSensorID = doc["SoilSensorID"];
    locationID   = doc["locationID"];
    deviceVerified = true;
    sendingEnabled = true;
    server.send(200, "application/json", "{\"status\":\"debug_assigned\"}");
  }
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
      userID       = resDoc["userID"];
      SoilSensorID = resDoc["SoilSensorID"];
      locationID   = resDoc["locationID"];
      deviceVerified = true;
      sendingEnabled = true;
      Serial.printf("[SYNC] Active! SensorID: %d, SensorID: %d, LocID: %d\n", userID, SoilSensorID, locationID);
    } 
    else if (status == "disconnected") {
      deviceVerified = true; 
      sendingEnabled = false;
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

/* ===================== SEND WATERING DATA ===================== */
// Added parameter "type" to differentiate updates
void sendWateringData(String updateType) {
  if (!deviceVerified) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;
  
  doc["userID"] = userID;
  doc["liquidsensorID"] = liquidsensorID;
  doc["currentliquidlevel"] = finaldistance1;
  doc["updateType"] = updateType; // Tells PHP whether to log event or log to liquidlevel
  
  if (wateringstatus == -1) doc["wateringstatus"] = (char*)0;
  else doc["wateringstatus"] = wateringstatus;

  if (wateringflag == -1) doc["wateringFlag"] = (char*)0;
  else doc["wateringFlag"] = wateringflag;

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);
  
  if (httpCode > 0) {
    String response = http.getString();
    Serial.printf("[WATERING API] Status: %d | Response: %s\n", httpCode, response.c_str());
  } else {
    Serial.printf("[WATERING API] Connection Failed: %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
}

/* ===================== SEND SENSOR DATA ===================== */
void sendSensorData() {
  if (!deviceVerified || !sendingEnabled) return;

  StaticJsonDocument<400> doc;
  doc["userID"] = userID;
  doc["SoilSensorID"] = SoilSensorID;
  doc["locationID"]   = locationID;
  doc["soilN"]  = random(200, 1000) / 10.0;
  doc["soilP"]  = random(200, 1000) / 10.0;
  doc["soilK"]  = random(200, 1000) / 10.0;
  doc["soilEC"] = random(10, 40)   / 10.0;
  doc["soilpH"] = random(10, 140)  / 10.0;
  doc["soilT"]  = random(100, 1000)/ 10.0;
  doc["soilM"]  = random(100, 1000)/ 10.0;
  doc["soilLV"] = random(10, 100)  / 10.0;

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
  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(switch1, INPUT_PULLUP);
  pinMode(mixermotor1, OUTPUT);
  pinMode(pumpmotor1, OUTPUT);
  digitalWrite(mixermotor1, LOW);
  digitalWrite(pumpmotor1, LOW);

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) delay(500);
  
  server.on("/receive", HTTP_POST, handleReceive);
  server.begin();
}

void loop() {
  server.handleClient();
  unsigned long currentMillis = millis();

  // --- SERVER SYNC ---
  if (currentMillis - lastSendTime >= sendInterval) {
    lastSendTime = currentMillis;
    verifyDeviceWithServer();
    if (deviceVerified && sendingEnabled) sendSensorData();
  }

  // --- ULTRASONIC READING ---
  if (currentMillis - lastPingTime >= pingInterval) {
    lastPingTime = currentMillis;
    digitalWrite(trigPin1, LOW); delayMicroseconds(2);
    digitalWrite(trigPin1, HIGH); delayMicroseconds(10);
    digitalWrite(trigPin1, LOW);
    duration1 = pulseIn(echoPin1, HIGH, 25000); 
    if (duration1 > 0) {
      distance1 = duration1 * 0.034 / 2;
      if (distance1 > 0 && distance1 < 400) finaldistance1 = distance1;
    }
  }

  // --- CONTINUOUS LEVEL UPDATE (Every 5s) ---
  if (currentMillis - lastLevelSendTime >= levelSendInterval) {
    lastLevelSendTime = currentMillis;
    sendWateringData("continuous"); 
  }

  // --- PUMP LOGIC (Event Driven) ---
  // Pump On
  if (finaldistance1 > 70 && wateringflag != 1) {
    wateringflag = 1;
    wateringstatus = 0;
    digitalWrite(pumpmotor1, HIGH);
    sendWateringData("event"); // Immediate event trigger
  }
  
  // Pump Off
  if (finaldistance1 <= 30 && wateringflag == 1) {
    wateringflag = 0;
    wateringstatus = 0;
    digitalWrite(pumpmotor1, LOW);
    mixingflag = 1; 
    sendWateringData("event"); // Immediate event trigger
  }

  // --- MIXER LOGIC ---
  if (mixingflag == 1 && digitalRead(switch1) == LOW) {
    wateringstatus = -1;
    mixingflag = 2;
    mixingstatus = 1;
    mixStartTime = currentMillis;
    digitalWrite(mixermotor1, HIGH);
  }

  if (mixingflag == 2 && mixingstatus == 1) {
    if (currentMillis - mixStartTime >= 5000) {
      digitalWrite(mixermotor1, LOW);
      mixingflag = 0; mixingstatus = 0; wateringflag = -1; 
      sendWateringData("event"); // End of mixing event
    }
  }
}