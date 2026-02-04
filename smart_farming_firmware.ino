#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

/* ===================== SERVER URLs ===================== */
const char* verifyDeviceURL = "http://172.18.0.11/smart_farming/verify_device.php";
const char* sendDataURL     = "http://172.18.0.11/smart_farming/sensor_api.php";

/* ===================== WIFI CREDENTIALS ===================== */
const char* ssid = "CompDeptWiFiAdmin";
const char* password = "isatu_6134";

/* ===================== GLOBALS ===================== */
bool wifiConnected = false;
bool deviceVerified = false;

int soilSensorID   = -1;
int locationID = -1;

unsigned long lastSendTime = 0;
const unsigned long sendInterval = 5000; // 5 seconds

/* ===================== VERIFY DEVICE ===================== */
void verifyDeviceWithServer() {
  Serial.println("[ESP32] Verifying device with server...");

  HTTPClient http;

  StaticJsonDocument<200> doc;
  doc["ipAddress"]  = WiFi.localIP().toString();
  doc["macAddress"] = WiFi.macAddress();

  String payload;
  serializeJson(doc, payload);

  http.begin(verifyDeviceURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);

  if (httpCode == 200) {
    String response = http.getString();

    StaticJsonDocument<200> resDoc;
    deserializeJson(resDoc, response);

    soilSensorID   = resDoc["soilSensorID"];
    locationID = resDoc["locationID"];

    deviceVerified = true;

    Serial.println("[ESP32] Device verified successfully!");
    Serial.print("[ESP32] SensorID: ");
    Serial.println(soilSensorID);
    Serial.print("[ESP32] LocationID: ");
    Serial.println(locationID);
  } else {
    Serial.print("[ESP32] Verification failed! HTTP code: ");
    Serial.println(httpCode);
  }

  http.end();
}

/* ===================== SEND SENSOR DATA ===================== */
void sendSensorData() {
  if (!deviceVerified) return;

  float soilN  = random(200, 1000) / 10.0;
  float soilP  = random(200, 1000) / 10.0;
  float soilK  = random(200, 1000) / 10.0;
  float soilEC = random(10, 40) / 10.0;
  float soilpH = random(10, 140) / 10.0;
  float soilT  = random(100, 1000) / 10.0;
  float soilM  = random(100, 1000) / 10.0;
  float soilLV  = random(10, 100) / 10.0;

  StaticJsonDocument<400> doc;
  doc["soilSensorID"]   = soilSensorID;
  doc["locationID"] = locationID;
  doc["soilN"]      = soilN;
  doc["soilP"]      = soilP;
  doc["soilK"]      = soilK;
  doc["soilEC"]     = soilEC;
  doc["soilpH"]     = soilpH;
  doc["soilT"]      = soilT;
  doc["soilM"]      = soilM;
  doc["soilLV"]      = soilLV;

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(sendDataURL);
  http.addHeader("Content-Type", "application/json");

  int httpCode = http.POST(payload);

  Serial.print("[ESP32] Data sent, HTTP code: ");
  Serial.println(httpCode);

  http.end();
}

/* ===================== SETUP ===================== */
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.print("[ESP32] Connecting to WiFi SSID: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  wifiConnected = true;
  Serial.println("\n[ESP32] WiFi Connected!");
  Serial.print("[ESP32] IP Address: ");
  Serial.println(WiFi.localIP());
  Serial.print("[ESP32] MAC Address: ");
  Serial.println(WiFi.macAddress());

  // Verify device with server
  verifyDeviceWithServer();

  randomSeed(analogRead(0));

  if (wifiConnected) {
    Serial.println("[ESP32] WiFi is connected.");
  }
  if (deviceVerified) {
    Serial.println("[ESP32] Device already verified, ready to send data.");
  }
}

/* ===================== LOOP ===================== */
void loop() {

  if (wifiConnected && deviceVerified) {
    unsigned long currentMillis = millis();
    if (currentMillis - lastSendTime >= sendInterval) {
      lastSendTime = currentMillis;
      sendSensorData();
    }
  }

  delay(1000);
}
