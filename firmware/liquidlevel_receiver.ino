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
const char* webServerIp = "192.168.1.3"; //"172.18.0.9"; 
String sendWateringURL = "http://" + String(webServerIp) + "/smart_farming/api/watering_api.php";
String sendIntelURL = "http://" + String(webServerIp) + "/smart_farming/api/intel_api.php";

const char* ssid = "ZTE_2.4G_cYFH3D";
const char* password = "hyperblade";
// const char* ssid = "CompDeptWiFiAdmin";
// const char* password = "isatu_6134";

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
const unsigned long serialTimeout = 10000;

// 1 - READY, 0 - HOLD, 2 - MIXING, -1 - IDLE/ERROR

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
const float calibrationFactor = 450.0; // YF-S201: 450 pulses per liter
float currentVolumeML = 0;
float targetVolumeML = 0;

bool wateringActive = false;
int activeTank = 0;
bool cycleRunning = false; // indicates if watering cycle is active
bool intakeLocked = false; // prevents new command from intel if command is active

// Flow sensor printing throttle
unsigned long lastFlowPrintTime = 0;
const unsigned long flowPrintInterval = 500; // Print every 500ms

// 15-minute wait timer
unsigned long cycleCompleteTime = 0;
const unsigned long cycleWaitInterval = 60000;  // 15 minutes = 900000 ms | 30 minutes = 1800000 ms
bool waitingAfterCycle = false;

// Pre-watering mixing flags (from Intel command)
int premixFlag1 = 0;
int premixFlag2 = 0;
int premixFlag3 = 0;
unsigned long premixStartTime1 = 0;
unsigned long premixStartTime2 = 0;
unsigned long premixStartTime3 = 0;

// Command deduplication & QUEUE SYSTEM
String lastCommand1 = "";
String lastCommand2 = "";
String lastCommand3 = "";

// Queueng the command for alternating 
int queuedTank = 0;
float queuedVolumeML = 0;

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

      StaticJsonDocument<200> resDoc;
      DeserializationError error = deserializeJson(resDoc, response);

      if(!error) {
        if(resDoc.containsKey("message") && resDoc.containsKey("command") && resDoc.containsKey("liquidVolume")) {
          String command = resDoc["command"];
          float intelVolume = resDoc["liquidVolume"];

          if(command != "none" && intelVolume > 0 && !waitingAfterCycle ) {
            
            int cmdTank = 0;
            if (command == "trig_tsl1") cmdTank = 1;
            else if (command == "trig_tsl2") cmdTank = 2;
            else if (command == "trig_tsl3") cmdTank = 3;

            if (cmdTank > 0) {
              // Determine if system is currently busy
              bool isPremixing = (premixFlag1 == 1 || premixFlag2 == 1 || premixFlag3 == 1);
              bool isBusy = wateringActive || isPremixing;
              
              int currentBusyTank = activeTank;
              if (premixFlag1 == 1) currentBusyTank = 1;
              if (premixFlag2 == 1) currentBusyTank = 2;
              if (premixFlag3 == 1) currentBusyTank = 3;

              if (!isBusy && !cycleRunning) {
                // Not busy: start immediately
                cycleRunning = true;
                targetVolumeML = intelVolume;
                
                if (cmdTank == 1) {
                  activeTank = 1; premixFlag1 = 1; premixStartTime1 = millis(); flowPulseCount = 0; wateringflag1 = 1; wateringstatus1 = 2; lastCommand1 = command; digitalWrite(mixermotor1, HIGH);
                  Serial.println("[INTEL] Tank 1: Starting pre-watering mix (NORMAL WATER)");
                } else if (cmdTank == 2) {
                  activeTank = 2; premixFlag2 = 1; premixStartTime2 = millis(); flowPulseCount = 0; wateringflag2 = 1; wateringstatus2 = 2; lastCommand2 = command; digitalWrite(mixermotor2, HIGH);
                  Serial.println("[INTEL] Tank 2: Starting pre-watering mix (CALCIUM BASED)");
                } else if (cmdTank == 3) {
                  activeTank = 3; premixFlag3 = 1; premixStartTime3 = millis(); flowPulseCount = 0; wateringflag3 = 1; wateringstatus3 = 2; lastCommand3 = command; digitalWrite(mixermotor3, HIGH);
                  Serial.println("[INTEL] Tank 3: Starting pre-watering mix (POTASSIUM BASED)");
                }
              } 
              else {
                // Busy: If command is for a different tank, queue it up
                if (cycleRunning && currentBusyTank != cmdTank && queuedTank == 0) {
                  queuedTank = cmdTank;
                  queuedVolumeML = intelVolume;
                  Serial.print("[QUEUE] System busy with Tank ");
                  Serial.print(currentBusyTank);
                  Serial.print(". Queuing Tank ");
                  Serial.println(queuedTank);
                }
              }
            }
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

/* ===================== SEND RESET TO WATERING API ===================== */

void sendResetToWatering(int sensorID) {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  StaticJsonDocument<200> doc;

  doc["liquidsensorID"] = sensorID;
  doc["updateType"] = "reset";  

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    Serial.print("[WATERING RESET] Tank ");
    Serial.print(sensorID);
    Serial.print(" reset sent: ");
    Serial.println(http.getString());
  }

  http.end();
}

/* ===================== SEND DATA ===================== */

void sendWateringData(String updateType, int sensorID, int currentLevel, int wStatus, int wFlag, int isActive) {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;
  if (!dataValid) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;

  doc["liquidsensorID"] = sensorID;
  doc["currentliquidlevel"] = currentLevel;
  doc["updateType"] = updateType;
  doc["isActive"] = isActive;

  if (wStatus == -1) doc["wateringstatus"] = nullptr;
  else doc["wateringstatus"] = wStatus;

  if (wFlag == -1) doc["wateringFlag"] = nullptr;
  else doc["wateringFlag"] = wFlag;

  doc["fertFlag"] = 0;
  doc["wateringvolume"] = 0;

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

/* ===================== LOG SOLENOID EVENT ===================== */

void solenoidWateringEvent(int tank, float dispensedML) {

  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  StaticJsonDocument<300> doc;

  doc["liquidsensorID"] = tank;
  doc["updateType"] = "watering";

  doc["wateringstatus"] = 1;
  doc["wateringFlag"] = nullptr;

  doc["isActive"] = 1;

  // volume from flow sensor
  doc["wateringvolume"] = round(dispensedML);

  // optional: send last known level
  if (tank == 1) doc["currentliquidlevel"] = currentliquidlevel1;
  if (tank == 2) doc["currentliquidlevel"] = currentliquidlevel2;
  if (tank == 3) doc["currentliquidlevel"] = currentliquidlevel3;

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    Serial.println("[LOG] Solenoid watering event sent");
    Serial.println(http.getString());
  }

  http.end();
}

/* ===================== SETUP ===================== */

void setup() {

  Serial.begin(115200);
  Serial2.begin(115200, SERIAL_8N1, RX2, TX2);

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

  if (apiReady && 
      !intakeLocked &&
      !waitingAfterCycle && 
      currentMillis - lastIntelCheck >= intelInterval) {

    lastIntelCheck = currentMillis;
    checkIntelConnection();
  }

  /* ================= RECEIVE DATA ================= */

  while (Serial2.available()) {
    char c = Serial2.read();
    if (c == '\r') continue;

    if (c == '\n') {
      serialBuffer.trim();

      int start = serialBuffer.indexOf('{');
      int end = serialBuffer.lastIndexOf('}');

      if (start != -1 && end != -1 && end > start) {
        String cleanJson = serialBuffer.substring(start, end + 1);

        StaticJsonDocument<200> doc;
        DeserializationError error = deserializeJson(doc, cleanJson);

        if (error) {
          Serial.print("[JSON ERROR] ");
          Serial.println(error.c_str());
          dataValid = false;
        } 
        else {
          int d1 = doc["distance1"];
          int d2 = doc["distance2"];
          int d3 = doc["distance3"];

          currentliquidlevel1 = d1;
          currentliquidlevel2 = d2;
          currentliquidlevel3 = d3;

          dataValid = true;
          lastSerialReceiveTime = millis();
        }
      } 
      else {
        Serial.println("[REJECTED] No valid JSON structure");
        dataValid = false;
      }

      serialBuffer = "";
    }
    else {
      if (serialBuffer.length() < 200) {
        serialBuffer += c;
      } else {
        serialBuffer = "";
      }
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

    sendWateringData("continuous", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
    sendWateringData("continuous", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
    sendWateringData("continuous", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
  }

  /* ================= TANK LOGIC ================= */

  // Tank 1
  if (apiReady && dataValid && currentliquidlevel1 > 60 && wateringflag1 != 1) {
    wateringflag1 = 1;
    wateringstatus1 = 0;
    digitalWrite(pumpmotor1, HIGH);
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
  }

  if (apiReady && dataValid && currentliquidlevel1 <= 25 && wateringflag1 == 1) {
    wateringflag1 = 0;
    wateringstatus1 = 0;
    digitalWrite(pumpmotor1, LOW);
    mixingflag1 = 1;
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
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
    sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
  }

  // Tank 2
  if (apiReady && dataValid && currentliquidlevel2 > 60 && wateringflag2 != 1) {
    wateringflag2 = 1;
    wateringstatus2 = 0;
    digitalWrite(pumpmotor2, HIGH);
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
  }

  if (apiReady && dataValid && currentliquidlevel2 <= 25 && wateringflag2 == 1) {
    wateringflag2 = 0;
    wateringstatus2 = 0;
    digitalWrite(pumpmotor2, LOW);
    mixingflag2 = 1;
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
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
    sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
  }

  // Tank 3
  if (apiReady && dataValid && currentliquidlevel3 > 60 && wateringflag3 != 1) {
    wateringflag3 = 1;
    wateringstatus3 = 0;
    digitalWrite(pumpmotor3, HIGH);
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
  }

  if (apiReady && dataValid && currentliquidlevel3 <= 25 && wateringflag3 == 1) {
    wateringflag3 = 0;
    wateringstatus3 = 0;
    digitalWrite(pumpmotor3, LOW);
    mixingflag3 = 1;
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
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
    sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
  }


  /* ================= PRE-WATERING MIX SEQUENCE (FROM INTEL) ================= */
  
  // Tank 1 Pre-watering Mix
  if (premixFlag1 == 1) {
    unsigned long premixElapsed = millis() - premixStartTime1;

    if (premixElapsed >= mixingDuration) {
      digitalWrite(mixermotor1, LOW);
      premixFlag1 = 0;
      trig_tsl1 = 1;

      wateringActive = true;  
      activeTank = 1;
      flowPulseCount = 0;
      wateringstatus1 = 1;

      digitalWrite(slIndicator1, HIGH); // solenoid open | start watering
      Serial.println("[INTEL SEQUENCE] Tank 1: Pre-mix complete, opening solenoid and starting flow count");
    }
  }

  // Tank 2 Pre-watering Mix
  if (premixFlag2 == 1) {
    unsigned long premixElapsed = millis() - premixStartTime2;

    if (premixElapsed >= mixingDuration) {
      digitalWrite(mixermotor2, LOW);
      premixFlag2 = 0;
      trig_tsl2 = 1;

      wateringActive = true;
      activeTank = 2;
      flowPulseCount = 0;
      wateringstatus2 = 1;

      digitalWrite(slIndicator2, HIGH); // solenoid open | start watering
      Serial.println("[INTEL SEQUENCE] Tank 2: Pre-mix complete, opening solenoid and starting flow count");
    }
  }

  // Tank 3 Pre-watering Mix
  if (premixFlag3 == 1) {
    unsigned long premixElapsed = millis() - premixStartTime3;

    if (premixElapsed >= mixingDuration) {
      digitalWrite(mixermotor3, LOW);
      premixFlag3 = 0;
      trig_tsl3 = 1;

      wateringActive = true;
      activeTank = 3;
      flowPulseCount = 0;
      wateringstatus3 = 1;

      digitalWrite(slIndicator3, HIGH); // solenoid open | start watering
      Serial.println("[INTEL SEQUENCE] Tank 3: Pre-mix complete, opening solenoid and starting flow count");
    }
  }


  /* ================= SOLENOID FLOW CONTROL ================= */

  if (wateringActive && targetVolumeML > 0) {
    currentVolumeML = (flowPulseCount / calibrationFactor) * 1000;  // Convert pulses to mL
    
    if (currentMillis - lastFlowPrintTime >= flowPrintInterval) {
      lastFlowPrintTime = currentMillis;
      
      int solenoidState = 0;
      if (activeTank == 1) solenoidState = digitalRead(slIndicator1);
      else if (activeTank == 2) solenoidState = digitalRead(slIndicator2);
      else if (activeTank == 3) solenoidState = digitalRead(slIndicator3);
      
      Serial.print("[FLOW COUNTING] Tank ");
      Serial.print(activeTank);
      Serial.print(" | Solenoid: ");
      Serial.print(solenoidState == HIGH ? "OPEN" : "CLOSED");
      Serial.print(" | Pulses: ");
      Serial.print(flowPulseCount);
      Serial.print(" | Volume: ");
      Serial.print(currentVolumeML);
      Serial.print(" / ");
      Serial.print(targetVolumeML);
      Serial.print(" mL | Progress: ");
      Serial.print((currentVolumeML / targetVolumeML) * 100);
      Serial.println("%");
    }

    // Check if target volume reached
    if (currentVolumeML >= targetVolumeML && currentVolumeML > 0) {
      Serial.println("\n[FLOW TARGET REACHED] Closing solenoid valve...");

    // LOG THE ACTUAL DISPENSED WATER
    solenoidWateringEvent(activeTank, currentVolumeML);

      Serial.print("[FINAL] Volume: ");
      Serial.print(currentVolumeML);
      Serial.print(" mL >= Target: ");
      Serial.println(targetVolumeML);

      if (activeTank == 1) {
        digitalWrite(slIndicator1, LOW);
        trig_tsl1 = 0;
        wateringstatus1 = 3; 
      }
      if (activeTank == 2) {
        digitalWrite(slIndicator2, LOW);
        trig_tsl2 = 0;
        wateringstatus2 = 3; 
      }
      if (activeTank == 3) {
        digitalWrite(slIndicator3, LOW);
        trig_tsl3 = 0;
        wateringstatus3 = 3; 
      }
      
      wateringActive = false;
      flowPulseCount = 0;
      currentVolumeML = 0;

      // --- QUEUE CHECK: START NEXT TANK IF QUEUED ---
      if (queuedTank > 0) {
        Serial.print("[QUEUE] Starting queued process for Tank ");
        Serial.println(queuedTank);

        targetVolumeML = queuedVolumeML;
        intakeLocked = true;

        if (queuedTank == 1) {
          activeTank = 1; premixFlag1 = 1; premixStartTime1 = currentMillis; wateringflag1 = 1; wateringstatus1 = 2; digitalWrite(mixermotor1, HIGH);
        } else if (queuedTank == 2) {
          activeTank = 2; premixFlag2 = 1; premixStartTime2 = currentMillis; wateringflag2 = 1; wateringstatus2 = 2; digitalWrite(mixermotor2, HIGH);
        } else if (queuedTank == 3) {
          activeTank = 3; premixFlag3 = 1; premixStartTime3 = currentMillis; wateringflag3 = 1; wateringstatus3 = 2; digitalWrite(mixermotor3, HIGH);
        }

        // Clear the queue slot
        queuedTank = 0;
        queuedVolumeML = 0;
      } 
      // --- NO QUEUE: ENTER WAIT CYCLE ---
      else {
        waitingAfterCycle = true;
        cycleCompleteTime = currentMillis;
        activeTank = 0;
        Serial.println("[INTEL] Starting 15-minute wait period before reset...");
      }
    }
  }

  /* ================= 15-MINUTE CYCLE WAIT TIMER ================= */
  
  if (waitingAfterCycle && currentMillis - cycleCompleteTime >= cycleWaitInterval) {
    waitingAfterCycle = false;
    
    // Reset all tanks to idle state
    wateringflag1 = -1;
    wateringstatus1 = -1;
    wateringflag2 = -1;
    wateringstatus2 = -1;
    wateringflag3 = -1;
    wateringstatus3 = -1;
    
    // Send reset signal to Watering API to set isActive=0
    sendResetToWatering(1);
    sendResetToWatering(2);
    sendResetToWatering(3);

    cycleRunning = false;
    intakeLocked = false;
    
    Serial.println("[READY] Wait cycle complete. Sent reset signal to Watering API (isActive=0)");
  }
}