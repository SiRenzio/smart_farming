#include <ArduinoJson.h>

#define trigPin1 21
#define echoPin1 19
#define trigPin2 23
#define echoPin2 22

int finaldistance1 = 0;
int finaldistance2 = 0;

/* ===== TIMER SETTINGS ===== */
unsigned long sendInterval = 1000;     // send every 1 second
unsigned long readInterval = 60;       // sensor refresh rate (safe for ultrasonic)

unsigned long previousSendMillis = 0;
unsigned long previousReadMillis = 0;

/* ===== ULTRASONIC FUNCTION ===== */
int readUltrasonic(int trigPin, int echoPin) {

  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH, 5000);

  if (duration == 0) return -1;

  return duration * 0.0343 / 2;
}

void setup() {
  Serial.begin(9600);

  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);
}

void loop() {

  unsigned long currentMillis = millis();

  /* ===== FAST SENSOR READING ===== */
  if (currentMillis - previousReadMillis >= readInterval) {
    previousReadMillis = currentMillis;

    int d1 = readUltrasonic(trigPin1, echoPin1);
    delayMicroseconds(200);  
    int d2 = readUltrasonic(trigPin2, echoPin2);

    if (d1 > 0 && d1 < 400) finaldistance1 = d1;
    if (d2 > 0 && d2 < 400) finaldistance2 = d2;
  }

  if (currentMillis - previousSendMillis >= sendInterval) {
    previousSendMillis = currentMillis;

    StaticJsonDocument<200> doc;
    doc["distance1"] = finaldistance1;
    doc["distance2"] = finaldistance2;

    serializeJson(doc, Serial);
    Serial.println();
  }
}
