-- Create the 'plantinfo' table
CREATE TABLE plantinfo (
    plantID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT(11), -- Foreign key to users table
    plantName VARCHAR(30),
    plantVariety VARCHAR(30),
    FOREIGN KEY (userID) REFERENCES users(userID)
);

-- Create the 'plantnutrionneed' table
CREATE TABLE plantnutrionneed (
    nutritionID INT AUTO_INCREMENT PRIMARY KEY,
    nutritionSetName VARCHAR(30),
    userID INT(11), -- Foreign key to users table
    plantID INT, -- Foreign key to plantinfo table
    soilType VARCHAR(30),
    meanMoistureThreshold INT,
    growthStage VARCHAR(50),
    numberOfPlants INT,
    soilN INT(10),
    soilP INT(10),
    soilK INT(10),
    soilEC INT(10),
    soilPH FLOAT,
    liquidVolume FLOAT,
    isActive TINYINT(1),
    FOREIGN KEY (userID) REFERENCES users(userID),
    FOREIGN KEY (plantID) REFERENCES plantinfo(plantID)
);

-- Create the 'users' table
CREATE TABLE users (
    userID INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Store hashed passwords, never plain text
    email VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE sensorinfo (
    soilSensorID INT(15) AUTO_INCREMENT PRIMARY KEY,
    userID INT(11), -- Foreign key to users table
    sensorName VARCHAR(50),
    sensorMacAddress VARCHAR(30),
    isRegistered TINYINT(1),
    sensorStatus TINYINT(1),
    last_sensor_online DATETIME,
    dateAdded DATETIME,
    isRestart TINYINT(1),
    FOREIGN KEY (userID) REFERENCES users(userID)
);

CREATE TABLE farmlocation (
    locationID INT(15) AUTO_INCREMENT PRIMARY KEY,
    userID INT(11), -- Foreign key to users table
    farmName VARCHAR(30),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    dateAdded TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID)
);

CREATE TABLE sensordata (
    SensorDataID INT(15) AUTO_INCREMENT PRIMARY KEY,
    userID INT(11), -- Foreign key to users table
    SoilSensorID INT(10), -- Foreign key to sensorinfo table
    locationID INT(15), -- Foreign key to farmlocation table
    nutritionID INT(11), -- Foreign key to plantnutrionneed table
    SoilN INT(10),
    SoilP INT(10),
    SoilK INT(10),
    SoilEC INT(10),
    SoilPH FLOAT,
    SoilT FLOAT,
    SoilMois FLOAT,
    DateTime TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID),
    FOREIGN KEY (SoilSensorID) REFERENCES sensorinfo(soilSensorID),
    FOREIGN KEY (locationID) REFERENCES farmlocation(locationID),
    FOREIGN KEY (nutritionID) REFERENCES plantnutrionneed(nutritionID)
);

-- Create the 'liquidsensorinfo' table
CREATE TABLE liquidsensorinfo (
    liquidsensorID INT(15) AUTO_INCREMENT PRIMARY KEY,
    liquidtankname VARCHAR(50),
);

-- Create the 'liquidlevelsensor' table
CREATE TABLE liquidlevelsensor (
    liquidsensorreadID INT(15) AUTO_INCREMENT PRIMARY KEY,
    liquidsensorID INT(15),
    currentliquidlevel INT(15),
    dateandtime TIMESTAMP
);

-- Create the 'tankpumpevent' table
CREATE TABLE tankpumpevent (
    tankpumpventID INT(15) AUTO_INCREMENT PRIMARY KEY,
    liquidsensorID INT(15), -- Foreign key to liquidsensorinfo table
    wateringstatus TINYINT(1),
    wateringvolume FLOAT,
    wateringFlag TINYINT(1),
    isActive TINYINT(1),
    fertFlag TINYINT(1),
    waterlevel INT(15),
    dateandtime TIMESTAMP
    FOREIGN KEY (liquidsensorID) REFERENCES liquidsensorinfo(liquidsensorID)
);

-- Create the 'deployment' table
CREATE TABLE deployment (
    deploymentID INT(11) AUTO_INCREMENT PRIMARY KEY,
    userID INT(11), -- Foreign key to users table
    soilSensorID INT(11), -- Foreign key to sensorinfo table
    locationID INT(11), -- Foreign key to farmlocation table
    nutritionID INT(11), -- Foreign key to plantnutrionneed table
    isConnected TINYINT(1),
    isPrimary TINYINT(1),
    FOREIGN KEY (userID) REFERENCES users(userID),
    FOREIGN KEY (locationID) REFERENCES farmlocation (locationID),
    FOREIGN KEY (soilSensorID) REFERENCES sensorinfo (soilSensorID),
    FOREIGN KEY (nutritionID) REFERENCES plantnutrionneed (nutritionID)
)

-- Create the 'notification' table
CREATE TABLE notification (
    notificationID INT(11) AUTO_INCREMENT PRIMARY KEY,
    message TEXT,
    isRead TINYINT(1) DEFAULT 0,
    createdAt TIMESTAMP
)

-- Create the 'fertilizer' table
CREATE TABLE fertilizer (
    fertilizerID INT(11) AUTO_INCREMENT PRIMARY KEY,
    liquidsensorID INT(15), -- Foreign key to liquidsensorinfo table
    nutritionID INT(11), -- Foreign key to plantnutrionneed table
    fertilizerName VARCHAR(50),
    fertilizerAmount FLOAT,
    FOREIGN KEY (liquidsensorID) REFERENCES liquidsensorinfo(liquidsensorID),
    FOREIGN KEY (nutritionID) REFERENCES plantnutrionneed(nutritionID)
);

-- Create the soilmoisture_samples table
CREATE TABLE soilmoisture_samples (
    sampleID INT(11) AUTO_INCREMENT PRIMARY KEY,
    soilSensorID INT(15), -- Foreign key to sensorinfo table
    SoilMois FLOAT,
    createdAt TIMESTAMP,
    isBaseline TINYINT(1),
    FOREIGN KEY (soilSensorID) REFERENCES sensorinfo(soilSensorID)
)