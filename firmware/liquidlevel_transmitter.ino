#include <ArduinoJson.h>

// tank 1
#define trigPin1 21 // RX
#define echoPin1 19 // TX

// tank 2
#define trigPin2 23   
#define echoPin2 22

// tank 3
#define trigPin3 32
#define echoPin3 33   

int finaldistance1 = 0;
int finaldistance2 = 0;
int finaldistance3 = 0;

/* ===== TIMER SETTINGS ===== */
unsigned long sendInterval = 1000;     // send every 1 second
unsigned long readInterval = 60;      // FIX: Increased to accommodate the echo fade delays

unsigned long previousSendMillis = 0;
unsigned long previousReadMillis = 0;

/* ===== ULTRASONIC FUNCTION ===== */
int readUltrasonic(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 25000);

  if (duration == 0) return -1;

  return duration * 0.036 / 2;
}

void setup() {
  Serial.begin(115200);

  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);
  pinMode(trigPin3, OUTPUT);
  pinMode(echoPin3, INPUT);
}

void loop() {
  unsigned long currentMillis = millis();

  /* ===== SENSOR READING ===== */
  if (currentMillis - previousReadMillis >= readInterval) {
    previousReadMillis = currentMillis;

    int d1 = readUltrasonic(trigPin1, echoPin1);
    delay(40);
    int d2 = readUltrasonic(trigPin2, echoPin2);
    delay(40);
    int d3 = readUltrasonic(trigPin3, echoPin3);

    // Filter out 0s and outliers
    if (d1 > 0 && d1 < 100) finaldistance1 = d1;
    if (d2 > 0 && d2 < 100) finaldistance2 = d2;
    if (d3 > 0 && d3 < 100) finaldistance3 = d3;
  }

  /* ===== SEND DATA ===== */
  if (currentMillis - previousSendMillis >= sendInterval) {
    previousSendMillis = currentMillis;

    StaticJsonDocument<200> doc;
    doc["distance1"] = finaldistance1;
    doc["distance2"] = finaldistance2;
    doc["distance3"] = finaldistance3;

    serializeJson(doc, Serial);
    Serial.println();
    delay(20);
  }
}