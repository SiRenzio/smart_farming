#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== PIN DEFINITIONS ===================== */
#define RX2 16
#define TX2 17

// Solenoid Valves
#define slIndicator1 21
#define slIndicator2 22
#define slIndicator3 23

// Flow Sensor
#define flowSensor 19

// Tank 1
#define mixermotor1 14
#define pumpmotor1 25
#define switch1 4 

// Tank 2
#define mixermotor2 27 
#define pumpmotor2 33
#define switch2 13

// Tank 3
#define mixermotor3 26
#define pumpmotor3 32
#define switch3 18

/* ===================== SERVER & WIFI ===================== */
const char* webServerIp = "172.18.0.9";
String sendWateringURL = "http://" + String(webServerIp) + "/smart_farming/api/watering_api.php";
String sendIntelURL = "http://" + String(webServerIp) + "/smart_farming/api/intel_api.php";
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
int currentliquidlevel1 = 0; 
int currentliquidlevel2 = 0;
int currentliquidlevel3 = 0; 

int liquidsensorID1 = 1;
int liquidsensorID2 = 2;
int liquidsensorID3 = 3;


unsigned long lastLevelSendTime = 0;
const unsigned long levelSendInterval = 1100;

unsigned long lastHandshakeTime = 0;
const unsigned long handshakeInterval = 2000;

unsigned long lastIntelCheck = 0;
const unsigned long intelInterval = 1000;

const unsigned long mixingDuration = 5000;

// Serial watchdog
unsigned long lastSerialReceiveTime = 0;
const unsigned long serialTimeout = 6000;

// Tank 1 State
int wateringflag1 = -1;  
int wateringstatus1 = -1;
int mixingflag1 = 0;
int trig_tsl1 = 0;     
unsigned long mixStartTime1 = 0;

// Tank 2 State
int wateringflag2 = -1;  
int wateringstatus2 = -1;
int mixingflag2 = 0;
int trig_tsl2 = 0;     
unsigned long mixStartTime2 = 0;

// Tank 3 State
int wateringflag3 = -1;  
int wateringstatus3 = -1;
int mixingflag3 = 0;
int trig_tsl3 = 0;     
unsigned long mixStartTime3 = 0;


// Flow Sensor State
volatile unsigned long flowPulseCount = 0;
float calibrationFactor = 7.5; // typical YF-S201
float currentVolumeML = 0;
float targetVolumeML = 0;

bool wateringActive = false;
int activeTank = 0;

bool apiReady = false;
bool dataValid = false;

String serialBuffer = "";

void IRAM_ATTR flowPulseCounter() {
  flowPulseCount++;
}

/* ===================== API FUNCTIONS ===================== */

bool initialHandshake(int id) {

  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient http;
  StaticJsonDocument<200> doc;

  doc["liquidsensorID"] = id;
  doc["updateType"] = "handshake";

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);

  int httpCode = http.POST(payload);

  if (httpCode <= 0) {
    http.end();
    return false;
  }

  String response = http.getString();
  http.end();

  StaticJsonDocument<200> resDoc;

  if (deserializeJson(resDoc, response) != DeserializationError::Ok)
    return false;

  if (resDoc["success"] == true ||
      resDoc["success"] == 1 ||
      resDoc["status"] == "ok") {
    return true;
  }

  return false;
}

/* ===================== INTEL API ===================== */

void checkIntelConnection() {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.setTimeout(3000);

  bool success = false;

  for (int attempt = 0; attempt < 2; attempt++) {

    http.begin(sendIntelURL);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST("{}");

    if (httpCode > 0) {

      String response = http.getString();

      Serial.print("[INTEL CONNECTED]: ");
      Serial.println(response);

      StaticJsonDocument<200> resDoc;
      DeserializationError error = deserializeJson(resDoc, response);

      if(!error) {
        if(resDoc.containsKey("command") && resDoc.containsKey("liquidVolume")) {
          String command = resDoc["command"];
          targetVolumeML = resDoc["liquidVolume"];

          flowPulseCount = 0;
          wateringActive = true;
          
          Serial.print("[INTEL COMMAND]: ");
          Serial.print(command);

          Serial.print(" | [TARGET VOLUME]: ");
          Serial.print(targetVolumeML);
          Serial.println(" mL");

          if(command == "trig_tsl1") {
            activeTank = 1;
            trig_tsl1 = 1;
            wateringActive = true;
            digitalWrite(slIndicator1, HIGH);
            Serial.println("[SOLENOID] Tank 1 watering. NORMAL WATER");
          }
          else if(command == "trig_tsl2") {
            activeTank = 2;
            trig_tsl2 = 1;
            wateringActive = true;
            digitalWrite(slIndicator2, HIGH);
            Serial.println("[SOLENOID] Tank 2 watering. CALCIUM BASED (NITRABOR)");
          }
          else if(command == "trig_tsl3") {
            activeTank = 3;
            trig_tsl3 = 1;
            wateringActive = true;
            digitalWrite(slIndicator3, HIGH);
            Serial.println("[SOLENOID] Tank 3 watering. POTASSIUM BASED (UNIK16/WINNER)");
          }
        }
      }

      success = true;
      http.end();
      break;
    }

    http.end();
  }

  if (!success) {
    Serial.println("[INTEL API ERROR] Connection failed");
  }
}

/* ===================== SEND DATA ===================== */

void sendWateringData(String updateType, int sensorID, int currentLevel, int wStatus, int wFlag) {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;
  if (!dataValid) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;

  doc["liquidsensorID"] = sensorID;
  doc["currentliquidlevel"] = currentLevel;
  doc["updateType"] = updateType;

  if (wStatus == -1) doc["wateringstatus"] = nullptr;
  else doc["wateringstatus"] = wStatus;

  if (wFlag == -1) doc["wateringFlag"] = nullptr;
  else doc["wateringFlag"] = wFlag;

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);

  int httpCode = http.POST(payload);

  if (httpCode > 0) {

    Serial.print("[ESP32] Data sent: ");
    Serial.println(http.getString());

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

  pinMode(mixermotor3, OUTPUT);
  pinMode(pumpmotor3, OUTPUT);
  pinMode(switch3, INPUT_PULLUP);

  pinMode(slIndicator1, OUTPUT);
  pinMode(slIndicator2, OUTPUT);
  pinMode(slIndicator3, OUTPUT);

  pinMode(flowSensor, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(flowSensor), flowPulseCounter, FALLING);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
  }

  WiFi.setSleep(false);

  Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
}

/* ===================== LOOP ===================== */

void loop() {

  unsigned long currentMillis = millis();

  /* ================= WIFI WATCHDOG ================= */

  if (WiFi.status() != WL_CONNECTED) {

    Serial.println("[WIFI] Reconnecting...");

    WiFi.disconnect();
    WiFi.begin(ssid, password);

    return;
  }

  /* ================= HANDSHAKE ================= */

  if (!apiReady && currentMillis - lastHandshakeTime >= handshakeInterval) {

    lastHandshakeTime = currentMillis;

    bool h1 = initialHandshake(liquidsensorID1);
    bool h2 = initialHandshake(liquidsensorID2);
    bool h3 = initialHandshake(liquidsensorID3);

    if (h1 && h2 && h3) {
      apiReady = true;
    }
  }

  /* ================= INTEL API HEARTBEAT ================= */

  if (apiReady && currentMillis - lastIntelCheck >= intelInterval) {

    lastIntelCheck = currentMillis;

    checkIntelConnection();
  }

  /* ================= RECEIVE DATA ================= */

  while (Serial2.available()) {

    char c = Serial2.read();

    if (c == '\n') {

      StaticJsonDocument<200> doc;

      DeserializationError error = deserializeJson(doc, serialBuffer);

      if (error) {

        dataValid = false;

      } 
      else if (doc.containsKey("distance1") &&
               doc.containsKey("distance2") &&
               doc.containsKey("distance3")) {

        int d1 = doc["distance1"];
        int d2 = doc["distance2"];
        int d3 = doc["distance3"];

        if (d1 > 5 && d1 < 100 &&
            d2 > 5 && d2 < 100 &&
            d3 > 5 && d3 < 100) {

          currentliquidlevel1 = d1;
          currentliquidlevel2 = d2;
          currentliquidlevel3 = d3;

          dataValid = true;

          lastSerialReceiveTime = currentMillis;

        } 
        else {

          dataValid = false;
          Serial.println("[REJECTED] Out of range");

        }

      } 
      else {

        dataValid = false;
        Serial.println("[REJECTED] Invalid JSON");

      }

      serialBuffer = "";

    } 
    else {

      serialBuffer += c;

    }
  }

  /* ================= SERIAL TIMEOUT WATCHDOG ================= */

  if (dataValid && millis() - lastSerialReceiveTime > serialTimeout) {

    Serial.println("[TIMEOUT] Transmitter disconnected");

    dataValid = false;

    digitalWrite(pumpmotor1, LOW);
    digitalWrite(pumpmotor2, LOW);
    digitalWrite(pumpmotor3, LOW);

    digitalWrite(mixermotor1, LOW);
    digitalWrite(mixermotor2, LOW);
    digitalWrite(mixermotor3, LOW);

    wateringflag1 = wateringflag2 = wateringflag3 = -1;
    wateringstatus1 = wateringstatus2 = wateringstatus3 = -1;

    mixingflag1 = mixingflag2 = mixingflag3 = 0;
  }

  /* ================= CONTINUOUS UPDATE ================= */

  if (apiReady && dataValid &&
      currentMillis - lastLevelSendTime >= levelSendInterval) {

    lastLevelSendTime = currentMillis;

    sendWateringData("continuous", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
    sendWateringData("continuous", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
    sendWateringData("continuous", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  /* ================= TANK LOGIC ================= */

  // Tank 1
  if (apiReady && dataValid && currentliquidlevel1 > 60 && wateringflag1 != 1) {

    wateringflag1 = 1;
    wateringstatus1 = 0;

    digitalWrite(pumpmotor1, HIGH);

    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  if (apiReady && dataValid && currentliquidlevel1 <= 25 && wateringflag1 == 1) {

    wateringflag1 = 0;
    wateringstatus1 = 0;

    digitalWrite(pumpmotor1, LOW);

    mixingflag1 = 1;

    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  if (mixingflag1 == 1 && digitalRead(switch1) == LOW) {

    digitalWrite(mixermotor1, HIGH);

    wateringstatus1 = -1;

    mixingflag1 = 2;

    mixStartTime1 = currentMillis;
  }

  if (mixingflag1 == 2 && currentMillis - mixStartTime1 >= mixingDuration) {

    digitalWrite(mixermotor1, LOW);

    mixingflag1 = 0;

    wateringflag1 = -1;
    wateringstatus1 = -1;

    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  // Tank 2
  if (apiReady && dataValid && currentliquidlevel2 > 60 && wateringflag2 != 1) {

    wateringflag2 = 1;
    wateringstatus2 = 0;

    digitalWrite(pumpmotor2, HIGH);

    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  if (apiReady && dataValid && currentliquidlevel2 <= 25 && wateringflag2 == 1) {

    wateringflag2 = 0;
    wateringstatus2 = 0;

    digitalWrite(pumpmotor2, LOW);

    mixingflag2 = 1;

    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  if (mixingflag2 == 1 && digitalRead(switch2) == LOW) {

    digitalWrite(mixermotor2, HIGH);

    wateringstatus2 = -1;

    mixingflag2 = 2;

    mixStartTime2 = currentMillis;
  }

  if (mixingflag2 == 2 && currentMillis - mixStartTime2 >= mixingDuration) {

    digitalWrite(mixermotor2, LOW);

    mixingflag2 = 0;

    wateringflag2 = -1;
    wateringstatus2 = -1;

    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  // Tank 3
  if (apiReady && dataValid && currentliquidlevel3 > 60 && wateringflag3 != 1) {

    wateringflag3 = 1;
    wateringstatus3 = 0;

    digitalWrite(pumpmotor3, HIGH);

    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  if (apiReady && dataValid && currentliquidlevel3 <= 25 && wateringflag3 == 1) {

    wateringflag3 = 0;
    wateringstatus3 = 0;

    digitalWrite(pumpmotor3, LOW);

    mixingflag3 = 1;

    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  if (mixingflag3 == 1 && digitalRead(switch3) == LOW) {

    digitalWrite(mixermotor3, HIGH);

    wateringstatus3 = -1;

    mixingflag3 = 2;

    mixStartTime3 = currentMillis;
  }

  if (mixingflag3 == 2 && currentMillis - mixStartTime3 >= mixingDuration) {

    digitalWrite(mixermotor3, LOW);

    mixingflag3 = 0;

    wateringflag3 = -1;
    wateringstatus3 = -1;

    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }


  /* ================= SOLENOID FLOW CONTROL ================= */

  if (wateringActive) {
    currentVolumeML = (flowPulseCount / calibrationFactor) * 1000;

    if (currentVolumeML >= targetVolumeML) {

      if (activeTank == 1) {
        digitalWrite(slIndicator1, LOW);
        trig_tsl1 = 0;
      }

      if (activeTank == 2) {
        digitalWrite(slIndicator2, LOW);
        trig_tsl2 = 0;
      }

      if (activeTank == 3) {
        digitalWrite(slIndicator3, LOW);
        trig_tsl3 = 0;
      }
      wateringActive = false;
      activeTank = 0;

      flowPulseCount = 0;
      currentVolumeML = 0;

      Serial.println("[FLOW] Target volume reached. Valve closed.");
    }
  }
}