#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>

/* ===================== PIN DEFINITIONS ===================== */
#define RX2 16
#define TX2 17

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
const unsigned long levelSendInterval = 1000; 

unsigned long lastHandshakeTime = 0;
const unsigned long handshakeInterval = 2000; 

const unsigned long mixingDuration = 5000; // mixing duration

// Serial watchdog
unsigned long lastSerialReceiveTime = 0;
const unsigned long serialTimeout = 3000;  // 3 sec timeout

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

// Tank 3 State
int wateringflag3 = -1;  
int wateringstatus3 = -1;
int mixingflag3 = 0;     
unsigned long mixStartTime3 = 0;

bool apiReady = false;
bool dataValid = false;

String serialBuffer = "";

/* ===================== API FUNCTIONS ===================== */
bool initialHandshake(int id) {

  HTTPClient http;
  StaticJsonDocument<200> doc;

  doc["liquidsensorID"] = id;
  doc["updateType"] = "handshake";

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(1500);

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    String response = http.getString();
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
  http.setTimeout(1500);

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

  // tank 1
  pinMode(mixermotor1, OUTPUT);
  pinMode(pumpmotor1, OUTPUT);
  pinMode(switch1, INPUT_PULLUP);

  // tank2
  pinMode(mixermotor2, OUTPUT);
  pinMode(pumpmotor2, OUTPUT);
  pinMode(switch2, INPUT_PULLUP);

  // tank 3
  pinMode(mixermotor3, OUTPUT);
  pinMode(pumpmotor3, OUTPUT);
  pinMode(switch3, INPUT_PULLUP);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
  }

  Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
}

/* ===================== LOOP ===================== */
void loop() {

  unsigned long currentMillis = millis();

  /* ================= HANDSHAKE ================= */
  if (!apiReady && currentMillis - lastHandshakeTime >= handshakeInterval) {
    lastHandshakeTime = currentMillis;

    if (initialHandshake(liquidsensorID1) && initialHandshake(liquidsensorID2) && initialHandshake(liquidsensorID3)) {
      apiReady = true;
    }
  }

  /* ================= RECEIVE DATA ================= */
  while (Serial2.available()) {
    char c = Serial2.read();

    if (c == '\n') {

      StaticJsonDocument<200> doc;
      DeserializationError error = deserializeJson(doc, serialBuffer);

      if (!error &&
          doc.containsKey("distance1") &&
          doc.containsKey("distance2") &&
          doc.containsKey("distance3")) {

        int d1 = doc["distance1"];
        int d2 = doc["distance2"];
        int d3 = doc["distance3"];

        // validation range
        if (d1 > 5 && d1 < 100 &&
            d2 > 5 && d2 < 100 &&
            d3 > 5 && d3 < 100) {

          currentliquidlevel1 = d1;
          currentliquidlevel2 = d2;
          currentliquidlevel3 = d3;

          dataValid = true;
          lastSerialReceiveTime = currentMillis;

        } else {
          dataValid = false;
          Serial.println("[REJECTED] Out of range");
        }

      } else {
        dataValid = false;
        Serial.println("[REJECTED] Invalid JSON");
      }

      serialBuffer = "";

    } else {
      serialBuffer += c;
    }
  }

  /* ================= SERIAL TIMEOUT WATCHDOG ================= */
  if (dataValid && (currentMillis - lastSerialReceiveTime > serialTimeout)) {

    Serial.println("[TIMEOUT] Transmitter disconnected");

    dataValid = false;

    // Safety shutdown
    digitalWrite(pumpmotor1, LOW);
    digitalWrite(pumpmotor2, LOW);
    digitalWrite(pumpmotor3, LOW);
    digitalWrite(mixermotor1, LOW);
    digitalWrite(mixermotor2, LOW);
    digitalWrite(mixermotor3, LOW);

    // watering
    wateringflag1 = -1;
    wateringflag2 = -1;
    wateringflag3 = -1;

    // status
    wateringstatus1 = -1;
    wateringstatus2 = -1;
    wateringstatus3 = -1;

    // mixing
    mixingflag1 = 0;
    mixingflag2 = 0;
    mixingflag3 = 0;
  }

  /* ================= CONTINUOUS UPDATE ================= */
  if (apiReady && dataValid &&
      currentMillis - lastLevelSendTime >= levelSendInterval) {

    lastLevelSendTime = currentMillis;

    sendWateringData("continuous", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
    sendWateringData("continuous", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
    sendWateringData("continuous", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  /* ================= TANK 1 PUMP ================= */
  if (apiReady && dataValid && currentliquidlevel1 > 70 && wateringflag1 != 1) {
    wateringflag1 = 1;
    wateringstatus1 = 0;
    digitalWrite(pumpmotor1, HIGH);
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  if (apiReady && dataValid && currentliquidlevel1 <= 30 && wateringflag1 == 1) {
    wateringflag1 = 0;
    wateringstatus1 = 0;
    digitalWrite(pumpmotor1, LOW);
    mixingflag1 = 1;
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }

  /* ================= TANK MIXING 1 ================= */
  if (mixingflag1 == 1 && digitalRead(switch1) == LOW) {
    wateringstatus1 = -1;
    mixingflag1 = 2; // mixing
    mixStartTime1 = currentMillis;
    digitalWrite(mixermotor1, HIGH);
  }

  if (mixingflag1 == 2 && currentMillis - mixStartTime1 >= mixingDuration) {
    digitalWrite(mixermotor1, LOW);
    mixingflag1 = 0;
    wateringflag1 = -1;
    wateringstatus1 = -1;
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1);
  }


  /* ================= TANK 2 PUMP ================= */
  if (apiReady && dataValid && currentliquidlevel2 > 70 && wateringflag2 != 1) {
    wateringflag2 = 1;
    wateringstatus2 = 0;
    digitalWrite(pumpmotor2, HIGH);
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  if (apiReady && dataValid && currentliquidlevel2 <= 30 && wateringflag2 == 1) {
    wateringflag2 = 0;
    wateringstatus2 = 0;
    digitalWrite(pumpmotor2, LOW);
    mixingflag2 = 1;
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }

  /* ================= TANK MIXING 2 ================= */
  if (mixingflag2 == 1 && digitalRead(switch2) == LOW) {
    wateringstatus2 = -1;
    mixingflag2 = 2; // mixing
    mixStartTime2 = currentMillis;
    digitalWrite(mixermotor2, HIGH);
  }

  if (mixingflag2 == 2 && currentMillis - mixStartTime2 >= mixingDuration) {
    digitalWrite(mixermotor2, LOW);
    mixingflag2 = 0;
    wateringflag2 = -1;
    wateringstatus2 = -1;
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2);
  }


  /* ================= TANK 3 PUMPING ================= */
  if (apiReady && dataValid && currentliquidlevel3 > 70 && wateringflag3 != 1) {
    wateringflag3 = 1;
    wateringstatus3 = 0;
    digitalWrite(pumpmotor3, HIGH);
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  if (apiReady && dataValid && currentliquidlevel3 <= 30 && wateringflag3 == 1) {
    wateringflag3 = 0;
    wateringstatus3 = 0;
    digitalWrite(pumpmotor3, LOW);
    mixingflag3 = 1;
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }

  /* ================= TANK MIXING 3 ================= */
  if (mixingflag3 == 1 && digitalRead(switch3) == LOW) {
    wateringstatus3 = -1;
    mixingflag3 = 2; // mixing
    mixStartTime3 = currentMillis;
    digitalWrite(mixermotor3, HIGH);
  }

  if (mixingflag3 == 2 && currentMillis - mixStartTime3 >= mixingDuration) {
    digitalWrite(mixermotor3, LOW);
    mixingflag3 = 0;
    wateringflag3 = -1;
    wateringstatus3 = -1;
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3);
  }
}
