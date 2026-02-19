#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== PIN DEFINITIONS ===================== */
#define RX2 16
#define TX2 17

// Tank 1
#define mixermotor1 4
#define pumpmotor1 19
#define switch1 34 

// Tank 2
#define mixermotor2 5 
#define pumpmotor2 21
#define switch2 35

/* ===================== SERVER & WIFI ===================== */
const char* webServerIp = "172.18.0.9";
String sendWateringURL = "http://" + String(webServerIp) + "/smart_farming/api/watering_api.php";
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
int currentliquidlevel1 = 0; 
int currentliquidlevel2 = 0; 

int liquidsensorID1 = 1;
int liquidsensorID2 = 2;

unsigned long lastLevelSendTime = 0;
const unsigned long levelSendInterval = 1000; 

unsigned long lastHandshakeTime = 0;
const unsigned long handshakeInterval = 2000; 

// Tank 1 State
int wateringflag1 = -1;  
int wateringstatus1 = -1;
int mixingflag1 = 0;     
unsigned long mixStartTime1 = 0;

// Tank 2 State
int wateringflag2 = -1;  
int wateringstatus2 = -1;
int mixingflag2 = 0;     
unsigned long mixStartTime2 = 0;

bool apiReady = false;

String serialBuffer = "";

/* ===================== API FUNCTIONS ===================== */
bool initialHandshake(int id) {

  HTTPClient http;
  StaticJsonDocument<200> doc;

  doc["liquidsensorID"] = id;
  doc["updateType"] = "handshake";

  String payload;
  serializeJson(doc, payload);

  Serial.println("===== HANDSHAKE REQUEST =====");
  Serial.println(payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(1500);

  int httpCode = http.POST(payload);

  Serial.print("HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("Response:");
    Serial.println(response);

    StaticJsonDocument<200> resDoc;
    if (deserializeJson(resDoc, response) == DeserializationError::Ok) {
      if (resDoc["success"] == true) {
        Serial.println("Handshake SUCCESS");
        http.end();
        return true;
      }
    }
  }

  Serial.println("Handshake FAILED");
  http.end();
  return false;
}

/* ===================== SENDING TO API ===================== */

void sendWateringData(String updateType, int sensorID, int currentLevel, int wStatus, int wFlag) {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;

  doc["liquidsensorID"] = sensorID;
  doc["currentliquidlevel"] = currentLevel;
  doc["updateType"] = updateType;
  
  if (wStatus == -1) doc["wateringstatus"] = (char*)0;
  else doc["wateringstatus"] = wStatus;

  if (wFlag == -1) doc["wateringFlag"] = (char*)0;
  else doc["wateringFlag"] = wFlag;

  String payload;
  serializeJson(doc, payload);

  Serial.println("===== API REQUEST =====");
  Serial.println(payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(1500);

  int httpCode = http.POST(payload);

  Serial.print("HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("Response:");
    Serial.println(response);
  } else {
    Serial.println("HTTP POST FAILED");
  }

  http.end();
}

/* ===================== SETUP ===================== */
void setup() {

  Serial.begin(115200);
  Serial2.begin(9600, SERIAL_8N1, RX2, TX2);

  pinMode(mixermotor1, OUTPUT);
  pinMode(pumpmotor1, OUTPUT);
  pinMode(switch1, INPUT_PULLUP);

  pinMode(mixermotor2, OUTPUT);
  pinMode(pumpmotor2, OUTPUT);
  pinMode(switch2, INPUT_PULLUP);

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
    Serial.print(".");
  }

  Serial.println("\nWiFi Connected");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());
}

/* ===================== LOOP ===================== */
void loop() {

  unsigned long currentMillis = millis();

  /* ================= HANDSHAKE ================= */
  if (!apiReady && currentMillis - lastHandshakeTime >= handshakeInterval) {
    lastHandshakeTime = currentMillis;

    if (initialHandshake(liquidsensorID1) && initialHandshake(liquidsensorID2)) {
      apiReady = true;
      Serial.println("API READY");
    }
  }

  /* ================= RECEIVE DATA (NON BLOCKING) ================= */
  while (Serial2.available()) {
    char c = Serial2.read();

    if (c == '\n') {
      StaticJsonDocument<200> doc;
      if (deserializeJson(doc, serialBuffer) == DeserializationError::Ok) {
        currentliquidlevel1 = doc["distance1"];
        currentliquidlevel2 = doc["distance2"];

        if(currentliquidlevel1 != 0 && currentliquidlevel2 != 0){
            Serial.print("Level1: ");
            Serial.print(currentliquidlevel1);
            Serial.print(" | Level2: ");
            Serial.println(currentliquidlevel2);
        } else {
            Serial.println("No transimitter connected");
        }
        
      }
      serialBuffer = "";
    } else {
      serialBuffer += c;
    }
  }

  /* ================= CONTINUOUS UPDATE ================= */
  if (apiReady && currentMillis - lastLevelSendTime >= levelSendInterval) {
    lastLevelSendTime = currentMillis;

    sendWateringData("continuous", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
    sendWateringData("continuous", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  /* ================= TANK 1 LOGIC ================= */
  if (apiReady && currentliquidlevel1 > 70 && wateringflag1 != 1) {
    wateringflag1 = 1; wateringstatus1 = 0;
    digitalWrite(pumpmotor1, HIGH);
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  if (apiReady && currentliquidlevel1 <= 30 && wateringflag1 == 1) {
    wateringflag1 = 0; wateringstatus1 = 0;
    digitalWrite(pumpmotor1, LOW);
    mixingflag1 = 1;
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  /* ===================== MIXING ===================== */

  if (mixingflag1 == 1 && digitalRead(switch1) == LOW) {
    wateringstatus1 = -1;
    mixingflag1 = 2;
    mixStartTime1 = currentMillis;
    digitalWrite(mixermotor1, HIGH);
  }

  if (mixingflag1 == 2 && (currentMillis - mixStartTime1 >= 5000)) {
    digitalWrite(mixermotor1, LOW);
    mixingflag1 = 0; wateringflag1 = -1; wateringstatus1 = -1;
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  /* ================= TANK 2 LOGIC ================= */
  if (apiReady && currentliquidlevel2 > 70 && wateringflag2 != 1) {
    wateringflag2 = 1; wateringstatus2 = 0;
    digitalWrite(pumpmotor2, HIGH);
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  if (apiReady && currentliquidlevel2 <= 30 && wateringflag2 == 1) {
    wateringflag2 = 0; wateringstatus2 = 0;
    digitalWrite(pumpmotor2, LOW);
    mixingflag2 = 1;
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  /* ===================== MIXING ===================== */

  if (mixingflag2 == 1 && digitalRead(switch2) == LOW) {
    wateringstatus2 = -1;
    mixingflag2 = 2;
    mixStartTime2 = currentMillis;
    digitalWrite(mixermotor2, HIGH);
  }

  if (mixingflag2 == 2 && (currentMillis - mixStartTime2 >= 5000)) {
    digitalWrite(mixermotor2, LOW);
    mixingflag2 = 0; wateringflag2 = -1; wateringstatus2 = -1;
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }
}
