#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <Preferences.h>
#include <DNSServer.h>

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
const char* webServerIp = "172.16.0.105";
// const char* webServerIp = "192.168.1.5";
 
String sendWateringURL = "http://" + String(webServerIp) + "/api/watering_api.php";
String sendIntelURL = "http://" + String(webServerIp) + "/api/intel_api.php";
String sendManualURL = "http://" + String(webServerIp) + "/test/manual_api.php";

/* ===================== WIFI AP & PREFERENCES GLOBALS ===================== */
WebServer server(80);
Preferences preferences;
DNSServer dnsServer;

bool inAPMode = false; 
const byte DNS_PORT = 53;

/* ===================== MANUAL GLOBALS ===================== */
bool manualRunning = false;
String manualCommand = "";
unsigned long manualStartTime = 0;
const unsigned long MANUAL_DURATION = 5000;

unsigned long lastManualCheck = 0;
const unsigned long manualInterval = 1500;
String lastManualCommand = "";

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

unsigned long lastSettingsCheck = 0;
const unsigned long settingsInterval = 15000; // 30 seconds

unsigned long lastIntelCheck = 0;
const unsigned long intelInterval = 1000;

unsigned long mixingDuration = 5000;

// Serial watchdog
unsigned long lastSerialReceiveTime = 0;
const unsigned long serialTimeout = 10000;

// 1 - READY, 0 - HOLD, 2 - MIXING, -1 - IDLE/ERROR

// Tank 1 State
int wateringflag1 = -1;  
int wateringstatus1 = -1;
int trig_tsl1 = 0;

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
unsigned long lastFlowPulseCount = 0;
const float calibrationFactor = 450.0; // YF-S201: 450 pulses per liter
float currentVolumeML = 0;
float targetVolumeML = 0;
//Flow Sensor fail safe
unsigned long lastFlowPulseTime = 0;
unsigned long flowTimeout = 60000; // 3 minutes (180,000 ms)

bool wateringActive = false;
int activeTank = 0;
bool cycleRunning = false; // indicates if watering cycle is active
bool intakeLocked = false; // prevents new command from intel if command is active

// Flow sensor debugging
unsigned long lastFlowPrintTime = 0;
const unsigned long flowPrintInterval = 500;

// waiting cycle
unsigned long cycleCompleteTime = 0;
unsigned long cycleWaitInterval = 60000;  // 15 minutes = 900000 ms | 30 minutes = 1800000 ms
bool waitingAfterCycle = false;

// Pre-watering mixing flags (from Intel command)
int premixFlag2 = 0;
int premixFlag3 = 0;
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
  lastFlowPulseTime = millis();
}

/* ===================== CAPTIVE PORTAL (WIFI SETUP) ===================== */
const char* wifiSetupPage = R"rawliteral(
<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:Arial;text-align:center;margin-top:50px;} 
input{margin:10px;padding:10px;width:80%;max-width:300px;border-radius:5px;border:1px solid #ccc;}
button{padding:10px 20px;background:#28a745;color:white;border:none;border-radius:5px;cursor:pointer;}
</style></head><body><h2>TANK CONTROLLER WiFi Setup</h2>
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
    ESP.restart(); 
  } else {
    server.send(400, "text/plain", "Missing SSID or Password");
  }
}

void startAPMode() {
  Serial.println("\n[WiFi] Starting Access Point Mode...");
  WiFi.mode(WIFI_AP);
  WiFi.softAP("SmartFarming-Controller"); 

  dnsServer.start(DNS_PORT, "*", WiFi.softAPIP());

  server.on("/", HTTP_GET, handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.onNotFound(handleRoot); 
  server.begin();

  inAPMode = true;
  Serial.println("[WiFi] AP Mode Active. Connect to 'SmartFarming-Controller' to configure.");
  Serial.print("[WiFi] AP IP address: ");
  Serial.println(WiFi.softAPIP());
}

/* ===================== Manual FUNCTIONS ===================== */
void manualTesting() {
  if (manualRunning) {
    // If 5 seconds have passed, turn off the device
    if (millis() - manualStartTime >= MANUAL_DURATION) {
      Serial.println("[MANUAL] Actuators Stop!.....");

      // Turn off based on the active command
      if (manualCommand == "pump1") digitalWrite(pumpmotor1, LOW);
      else if (manualCommand == "pump2") digitalWrite(pumpmotor2, LOW);
      else if (manualCommand == "pump3") digitalWrite(pumpmotor3, LOW);
      else if (manualCommand == "mixer2") digitalWrite(mixermotor2, LOW);
      else if (manualCommand == "mixer3") digitalWrite(mixermotor3, LOW);

      manualRunning = false;
      manualCommand = "";
      Serial.println("[MANUAL] Execution done");
    }
    return; 
  }

  // get command
  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.setTimeout(3000);

  http.begin(sendManualURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST("{}");

  if (httpCode > 0) {
    String response = http.getString();
    StaticJsonDocument<200> doc;
    DeserializationError error = deserializeJson(doc, response);

    if (!error) {
      String command = doc["command"];

      // debugging: prevent repeating command
      if (command == lastManualCommand) {
        http.end();
        return;
      }
      lastManualCommand = command;

      if (command != "none") {
        Serial.print("[MANUAL] Test Command: ");
        Serial.println(command);

        // Turn ON the specific device
        if (command == "pump1") digitalWrite(pumpmotor1, HIGH);
        else if (command == "pump2") digitalWrite(pumpmotor2, HIGH);
        else if (command == "pump3") digitalWrite(pumpmotor3, HIGH);
        else if (command == "mixer2") digitalWrite(mixermotor2, HIGH);
        else if (command == "mixer3") digitalWrite(mixermotor3, HIGH);
        else {
          http.end();
          return;
        }

        // Save state to trigger the 5-second timer
        manualRunning = true;
        manualCommand = command;
        manualStartTime = millis();

        Serial.println("[MANUAL] Actuators running......)");
      }
    } 
    else {
      Serial.println("[MANUAL ERROR] JSON parse failed");
    }
  }

  http.end();
}

/* ===================== API FUNCTIONS ===================== */

bool initialHandshake(int id) {

  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient http;
  StaticJsonDocument<200> doc; // For sending

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

  // Increased size to 512 to safely hold the added settings payload
  StaticJsonDocument<512> resDoc; 

  if (deserializeJson(resDoc, response) != DeserializationError::Ok)
    return false;

  if (resDoc["success"] == true ||
      resDoc["success"] == 1 ||
      resDoc["status"] == "ok") {
        
    // --- FETCH AND APPLY SETTINGS FROM API ---
    if (resDoc["data"]["settings"].is<JsonObject>()) {
      JsonObject settings = resDoc["data"]["settings"];
      
      if (settings.containsKey("wateringTime")) {
        flowTimeout = settings["wateringTime"].as<unsigned long>();
      }
      if (settings.containsKey("backOffTime")) {
        cycleWaitInterval = settings["backOffTime"].as<unsigned long>();
      }
      if (settings.containsKey("mixingTime")) {
        mixingDuration = settings["mixingTime"].as<unsigned long>();
      }
      
      Serial.print("[HANDSHAKE] Updated timings for system -> ");
      Serial.print("Flow Timeout: "); Serial.print(flowTimeout);
      Serial.print(" ms | Wait Interval: "); Serial.print(cycleWaitInterval);
      Serial.print(" ms | Mix Duration: "); Serial.print(mixingDuration);
      Serial.println(" ms");
    }

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
              bool isPremixing = (premixFlag2 == 1 || premixFlag3 == 1);
              bool isBusy = wateringActive || isPremixing;
              
              int currentBusyTank = activeTank;
              if (premixFlag2 == 1) currentBusyTank = 2;
              if (premixFlag3 == 1) currentBusyTank = 3;

              if (!isBusy && !cycleRunning) {
                // Not busy: start immediately
                cycleRunning = true;
                targetVolumeML = intelVolume;
                
                if (cmdTank == 1) {
                  activeTank = 1; 
                  lastCommand1 = command; 
                  
                  // Immediately start watering process
                  wateringActive = true; 
                  flowPulseCount = 0; 
                  lastFlowPulseTime = millis();
                  wateringstatus1 = 1;
                  trig_tsl1 = 1;
                  
                  digitalWrite(slIndicator1, HIGH); // Open solenoid
                  Serial.println("[INTEL] Tank 1: Opening solenoid and starting flow (NO PRE-MIX)");
                } else if (cmdTank == 2) {
                  activeTank = 2; premixFlag2 = 1; premixStartTime2 = millis(); flowPulseCount = 0; lastCommand2 = command; digitalWrite(mixermotor2, HIGH);
                  Serial.println("[INTEL] Tank 2: Starting pre-watering mix (CALCIUM BASED)");
                } else if (cmdTank == 3) {
                  activeTank = 3; premixFlag3 = 1; premixStartTime3 = millis(); flowPulseCount = 0; lastCommand3 = command; digitalWrite(mixermotor3, HIGH);
                  Serial.println("[INTEL] Tank 3: Starting pre-watering mix (POTASSIUM BASED)");
                }
              } 
              else {
                // queue the second command of alternating tanks
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
  doc["wateringvolume"] = round(dispensedML);

  // send current level of the tank
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

/* ===================== CHECK SETTINGS (EVERY 30s) ===================== */
void checkSettingsUpdate() {
  if (!apiReady || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  StaticJsonDocument<200> doc;

  // We only need to check Tank 1, since settings apply to the whole system
  doc["liquidsensorID"] = liquidsensorID1;
  doc["updateType"] = "handshake"; 

  String payload;
  serializeJson(doc, payload);

  http.begin(sendWateringURL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(3000);

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    String response = http.getString();
    StaticJsonDocument<512> resDoc; 

    if (deserializeJson(resDoc, response) == DeserializationError::Ok) {
      if (resDoc["success"] == true || resDoc["success"] == 1 || resDoc["status"] == "ok") {
        
        if (resDoc["data"]["settings"].is<JsonObject>()) {
          JsonObject settings = resDoc["data"]["settings"];
          bool isChanged = false; // Flag to track if anything is different

          // Check Flow Timeout
          if (settings.containsKey("wateringTime")) {
            unsigned long newFlowTimeout = settings["wateringTime"].as<unsigned long>();
            if (newFlowTimeout != flowTimeout && newFlowTimeout > 0) {
              flowTimeout = newFlowTimeout;
              isChanged = true;
            }
          }
          
          // Check Wait Interval
          if (settings.containsKey("backOffTime")) {
            unsigned long newWaitInterval = settings["backOffTime"].as<unsigned long>();
            if (newWaitInterval != cycleWaitInterval && newWaitInterval > 0) {
              cycleWaitInterval = newWaitInterval;
              isChanged = true;
            }
          }
          
          // Check Mixing Duration
          if (settings.containsKey("mixingTime")) {
            unsigned long newMixDuration = settings["mixingTime"].as<unsigned long>();
            if (newMixDuration != mixingDuration && newMixDuration > 0) {
              mixingDuration = newMixDuration;
              isChanged = true;
            }
          }

          // Only print to serial if changes actually happened
          if (isChanged) {
            Serial.print("[SETTINGS BACKGROUND UPDATE] New timings applied -> ");
            Serial.print("Flow: "); Serial.print(flowTimeout);
            Serial.print("ms | Wait: "); Serial.print(cycleWaitInterval);
            Serial.print("ms | Mix: "); Serial.print(mixingDuration);
            Serial.println("ms");
          }
        }
      }
    }
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

  // Initialize Preferences to read saved WiFi Credentials
  preferences.begin("wifi_creds", true); 
  String savedSSID = preferences.getString("ssid", "");
  String savedPass = preferences.getString("pass", "");
  preferences.end();

  if (savedSSID != "") {
    Serial.println("\n[WiFi] Found saved credentials. Connecting to: " + savedSSID);
    WiFi.mode(WIFI_STA);
    WiFi.setHostname("SmartFarm-Controller");
    WiFi.begin(savedSSID.c_str(), savedPass.c_str());

    int timeout = 20; // 10 seconds total wait
    while (WiFi.status() != WL_CONNECTED && timeout > 0) {
      delay(500);
      Serial.print(".");
      timeout--;
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
      WiFi.setSleep(false);
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

/* ===================== LOOP ===================== */

void loop() {
  
  if (inAPMode) {
    // AP MODE
    dnsServer.processNextRequest();
    server.handleClient();
    return; // Skip the rest of the loop while in AP Setup mode
  }

  // farming operations
  unsigned long currentMillis = millis();

  // serial wathchdog
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WIFI] Network lost! Restarting to switch to AP mode...");
    delay(3000);
    ESP.restart(); // Will reboot, fail to connect, and open the AP portal
    return;
  }

  // API initial handshake
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

  /* ================= SETTINGS UPDATE HEARTBEAT ================= */
  if (apiReady && currentMillis - lastSettingsCheck >= settingsInterval) {
    lastSettingsCheck = currentMillis;
    checkSettingsUpdate();
  }

/* ================= MANUAL API HEARTBEAT & TIMER ================= */
  if (apiReady) {
    if (manualRunning) {
      manualTesting(); 
    } 
    else if (millis() - lastManualCheck >= manualInterval) {
      lastManualCheck = millis();
      manualTesting();
    }
  }

  // Receive data from the serial (from the transmitter)
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
          if (doc.containsKey("distance1") && doc.containsKey("distance2") && doc.containsKey("distance3")) {
            currentliquidlevel1 = doc["distance1"];
            currentliquidlevel2 = doc["distance2"];
            currentliquidlevel3 = doc["distance3"];

            dataValid = true;
            lastSerialReceiveTime = millis();
          } 
          else {
            Serial.println("[REJECTED] JSON parsed, but keys are missing.");
            dataValid = false;
          }
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

  // Transmitter serial timeout watchdog
  if (millis() - lastSerialReceiveTime > serialTimeout) {
    if (dataValid) { 
      Serial.println("[TIMEOUT] Transmitter disconnected! Forcing actuators OFF.");
      dataValid = false; // Toggle to false so it doesn't spam the serial monitor
    }

    digitalWrite(pumpmotor1, LOW);
    digitalWrite(pumpmotor2, LOW);
    digitalWrite(pumpmotor3, LOW);

    digitalWrite(mixermotor1, LOW);
    digitalWrite(mixermotor2, LOW);
    digitalWrite(mixermotor3, LOW);

    wateringflag1 = wateringflag2 = wateringflag3 = -1;
    wateringstatus1 = wateringstatus2 = wateringstatus3 = -1;
    mixingflag2 = mixingflag3 = 0;
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

  if(!manualRunning) {
    if (currentliquidlevel1 <= 25) digitalWrite(pumpmotor1, LOW);
    if (currentliquidlevel2 <= 25) digitalWrite(pumpmotor2, LOW);
    if (currentliquidlevel3 <= 25) digitalWrite(pumpmotor3, LOW);
  }

  // Tank 1
  if (apiReady && dataValid) {
    // START Refilling: Level drops below threshold, pump is idle, and tank isn't currently watering.
    if (currentliquidlevel1 > 60 && wateringflag1 != 1 && activeTank != 1) {
      wateringflag1 = 1;
      wateringstatus1 = 0;
      digitalWrite(pumpmotor1, HIGH);
      sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
    }
    
    if (currentliquidlevel1 <= 25 && wateringflag1 == 1 && wateringstatus1 == 0) {
      digitalWrite(pumpmotor1, LOW); 
      wateringflag1 = -1;
      wateringstatus1 = -1;
      sendWateringData("event", liquidsensorID1, currentliquidlevel1, wateringstatus1, wateringflag1, 0);
    }
  }

  // Tank 2
  if (apiReady && dataValid) {
    if (currentliquidlevel2 > 60 && wateringflag2 != 1 && activeTank != 2) {
      wateringflag2 = 1;
      wateringstatus2 = 0;
      digitalWrite(pumpmotor2, HIGH);
      sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
    }

    // FIX: Removed 'activeTank != 2'
    if (currentliquidlevel2 <= 25 && wateringflag2 == 1 && wateringstatus2 == 0) {
      digitalWrite(pumpmotor2, LOW);
      wateringflag2 = 0;
      wateringstatus2 = 0;
      mixingflag2 = 1;
      sendWateringData("event", liquidsensorID2, currentliquidlevel2, wateringstatus2, wateringflag2, 0);
    }
  }

  // Tank 2 Mixing
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
  if (apiReady && dataValid) {
    if (currentliquidlevel3 > 60 && wateringflag3 != 1 && activeTank != 3) {
      wateringflag3 = 1;
      wateringstatus3 = 0;
      digitalWrite(pumpmotor3, HIGH);
      sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
    }

    // FIX: Removed 'activeTank != 3'
    if (currentliquidlevel3 <= 25 && wateringflag3 == 1 && wateringstatus3 == 0) {
      digitalWrite(pumpmotor3, LOW);
      wateringflag3 = 0;
      wateringstatus3 = 0;
      mixingflag3 = 1;
      sendWateringData("event", liquidsensorID3, currentliquidlevel3, wateringstatus3, wateringflag3, 0);
    }
  }

  // Tank 3 Mixing
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
      lastFlowPulseTime = millis();
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
      lastFlowPulseTime = millis();
      wateringstatus3 = 1;

      digitalWrite(slIndicator3, HIGH); // solenoid open | start watering
      Serial.println("[INTEL SEQUENCE] Tank 3: Pre-mix complete, opening solenoid and starting flow count");
    }
  }


  /* ================= SOLENOID FLOW CONTROL ================= */

  if (wateringActive && targetVolumeML > 0) {
    currentVolumeML = (flowPulseCount / calibrationFactor) * 1000;  // Convert pulses to mL
    
    // reset the flow timeout if there's a pulse count
    if (flowPulseCount != lastFlowPulseCount) {
      lastFlowPulseTime = currentMillis;
      lastFlowPulseCount = flowPulseCount; 
    }

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

    // FAIL SAFE: No flow detected or flow stopped (tank empty)
    if (millis() - lastFlowPulseTime >= flowTimeout) {
      Serial.println("[FAILSAFE] Flow stopped or not detected! Tank might be empty. Closing solenoid...");

      // send failsafe notice
      sendWateringData("failsafe", activeTank, 0, -1, -1, 0);

      // Close the active solenoid
      if (activeTank == 1) {
        digitalWrite(slIndicator1, LOW);
        wateringstatus1 = -1;
      }
      else if (activeTank == 2) {
        digitalWrite(slIndicator2, LOW);
        wateringstatus2 = -1;
      }
      else if (activeTank == 3) {
        digitalWrite(slIndicator3, LOW);
        wateringstatus3 = -1;
      }

      // Stop watering process
      wateringActive = false;
      flowPulseCount = 0;
      lastFlowPulseCount = 0; // Reset tracking variable
      currentVolumeML = 0;

      // Clear if there was a queued command so it doesn't try to run a bad sequence
      queuedTank = 0;
      queuedVolumeML = 0;

      // proceed to wait cycle after failsafe
      waitingAfterCycle = true;
      cycleCompleteTime = currentMillis;
      activeTank = 0;
      
      Serial.println("[INTEL] Failsafe triggered. Starting wait period before system reset...");

      return; // EXIT early to prevent further execution
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
        wateringstatus1 = -1; 
        wateringflag1 = -1;
      }
      if (activeTank == 2) {
        digitalWrite(slIndicator2, LOW);
        trig_tsl2 = 0;
        wateringstatus2 = -1; 
        wateringflag2 = -1;
      }
      if (activeTank == 3) {
        digitalWrite(slIndicator3, LOW);
        trig_tsl3 = 0;
        wateringstatus3 = -1; 
        wateringflag3 = -1;
      }
      
      wateringActive = false;
      flowPulseCount = 0;
      lastFlowPulseCount = 0; // Reset tracking variable
      currentVolumeML = 0;

      // --- QUEUE CHECK: START NEXT TANK IF QUEUED ---
      if (queuedTank > 0) {
        Serial.print("[QUEUE] Starting queued process for Tank ");
        Serial.println(queuedTank);

        targetVolumeML = queuedVolumeML;
        intakeLocked = true;

        if (queuedTank == 2) {
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
    
    // Send reset signal
    sendResetToWatering(1);
    sendResetToWatering(2);
    sendResetToWatering(3);

    cycleRunning = false;
    intakeLocked = false;
    
    Serial.println("[READY] Wait cycle complete. Sent reset signal to Watering API (isActive=0)");
  }
}