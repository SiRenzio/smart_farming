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
String sendWateringURL = "http://" + String(webServerIp) + "/smart_farming/api/watering_api.php";

/* ===================== WIFI CREDENTIALS ===================== */
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
WebServer server(80); 

unsigned long lastPingTime = 0;
const unsigned long pingInterval = 100; 

unsigned long lastLevelSendTime = 0;
const unsigned long levelSendInterval = 1000;   // ✅ NOW 1 SECOND

unsigned long lastHandshakeTime = 0;
const unsigned long handshakeInterval = 1000;   // ✅ Retry every 1s

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

bool apiReady = false;

/* ===================== INITIAL HANDSHAKE ===================== */
bool initialHandshake() {

  HTTPClient http;
  StaticJsonDocument<200> doc;

  doc["liquidsensorID"] = liquidsensorID;
  doc["updateType"] = "handshake";

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.printf("[HANDSHAKE] %d | %s\n", httpCode, response.c_str());

    StaticJsonDocument<200> resDoc;
    if (deserializeJson(resDoc, response) == DeserializationError::Ok) {
      if (resDoc["success"] == true) {
        http.end();
        return true;
      }
    }
  }

  http.end();
  return false;
}

/* ===================== SEND WATERING DATA ===================== */
void sendWateringData(String updateType) {

  if (!apiReady) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;

  doc["liquidsensorID"] = liquidsensorID;
  doc["currentliquidlevel"] = finaldistance1;
  doc["updateType"] = updateType;
  
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
    Serial.printf("[WATERING API] %d | %s\n", httpCode, response.c_str());
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
  while (WiFi.status() != WL_CONNECTED);

  server.begin();
}

void loop() {

  server.handleClient();
  unsigned long currentMillis = millis();

  /* ================= HANDSHAKE NON-BLOCKING ================= */
  if (!apiReady && currentMillis - lastHandshakeTime >= handshakeInterval) {
    lastHandshakeTime = currentMillis;
    apiReady = initialHandshake();
  }

  /* ================= ULTRASONIC ================= */
  if (currentMillis - lastPingTime >= pingInterval) {
    lastPingTime = currentMillis;

    digitalWrite(trigPin1, LOW);
    delayMicroseconds(2);
    digitalWrite(trigPin1, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin1, LOW);

    duration1 = pulseIn(echoPin1, HIGH, 25000);

    if (duration1 > 0) {
      distance1 = duration1 * 0.034 / 2;
      if (distance1 > 0 && distance1 < 400)
        finaldistance1 = distance1;
    }
  }

  /* ================= CONTINUOUS UPDATE EVERY 1s ================= */
  if (apiReady && currentMillis - lastLevelSendTime >= levelSendInterval) {
    lastLevelSendTime = currentMillis;
    sendWateringData("continuous");
  }

  /* ================= PUMP LOGIC ================= */
  if (apiReady && finaldistance1 > 70 && wateringflag != 1) {
    wateringflag = 1;
    wateringstatus = 0;
    digitalWrite(pumpmotor1, HIGH);
    sendWateringData("event");
  }

  if (apiReady && finaldistance1 <= 30 && wateringflag == 1) {
    wateringflag = 0;
    wateringstatus = 0;
    digitalWrite(pumpmotor1, LOW);
    mixingflag = 1;
    sendWateringData("event");
  }

  /* ================= MIXER LOGIC ================= */
  if (apiReady && mixingflag == 1 && digitalRead(switch1) == LOW) {
    wateringstatus = -1;
    mixingflag = 2;
    mixingstatus = 1;
    mixStartTime = currentMillis;
    digitalWrite(mixermotor1, HIGH);
  }

  if (apiReady && mixingflag == 2 && mixingstatus == 1) {
    if (currentMillis - mixStartTime >= 5000) {
      digitalWrite(mixermotor1, LOW);
      mixingflag = 0;
      mixingstatus = 0;
      wateringflag = -1;
      sendWateringData("event");
    }
  }
}
