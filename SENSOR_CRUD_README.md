# Sensor Management CRUD System

This document describes the new CRUD (Create, Read, Update, Delete) operations for the `sensorinfo` and `sensordata` tables in the Smart Farming application.

## New Files Created

### 1. Sensor Management (`sensorinfo` table)
- **`add_sensor.php`** - Add new soil sensors
- **`sensors.php`** - View and manage all sensors
- **`edit_sensor.php`** - Edit sensor information

### 2. Sensor Data Management (`sensordata` table)
- **`add_sensor_data.php`** - Add new sensor readings
- **`sensor_data.php`** - View all sensor data
- **`edit_sensor_data.php`** - Edit sensor data records
- **`view_sensor_data.php`** - View data from a specific sensor

## Features

### Sensor Management
- **Create**: Add new soil sensors with unique IDs and locations
- **Read**: View all sensors with data count and location information
- **Update**: Edit sensor locations
- **Delete**: Remove sensors (only if they have no associated data)

### Sensor Data Management
- **Create**: Add comprehensive soil readings including:
  - NPK values (Nitrogen, Phosphorus, Potassium)
  - Electrical Conductivity (EC)
  - pH levels
  - Temperature
  - Moisture percentage
  - Flow rate
  - Timestamp
- **Read**: View all sensor data in a comprehensive table
- **Update**: Edit any sensor data field
- **Delete**: Remove individual sensor readings

## Database Schema

### sensorinfo Table
```sql
CREATE TABLE sensorinfo (
    soilSensorID INT(15) AUTO_INCREMENT PRIMARY KEY,
    sensorLocation VARCHAR(50)
);
```

### sensordata Table
```sql
CREATE TABLE sensordata (
    SensorDataID INT(15) AUTO_INCREMENT PRIMARY KEY,
    SoilSensorID INT(10),
    SoilN INT(10),
    SoilP INT(10),
    SoilK INT(10),
    SoilEC INT(10),
    SoilPH FLOAT,
    SoilT FLOAT,
    SoilMois FLOAT,
    FlowRate FLOAT,
    DateTime TIMESTAMP,
    FOREIGN KEY (SoilSensorID) REFERENCES sensorinfo(soilSensorID)
);
```

## Navigation

The dashboard has been updated to include:
- Add New Sensor
- View Sensors
- Add Sensor Data
- View Sensor Data

## Security Features

- Session-based authentication required for all operations
- SQL injection prevention using prepared statements
- Input validation for all numeric fields
- Confirmation dialogs for destructive operations
- Foreign key constraints prevent orphaned data

## Usage Workflow

1. **Add Sensors**: Create sensors using `add_sensor.php` (ID is auto-generated)
2. **Add Data**: Use `add_sensor_data.php` to record readings from sensors
3. **Monitor**: View data using `sensor_data.php` or individual sensor views
4. **Manage**: Edit or delete sensors and data as needed

## Data Validation

- Sensor ID is automatically generated (auto-increment)
- Date/time is required for all readings
- Numeric fields are validated for proper number format
- pH values are constrained to 0-14 range
- Moisture values are constrained to 0-100% range

## Error Handling

- **Detailed Database Errors**: Shows specific MySQL error messages and error codes
- **Validation Errors**: Displays exact values that failed validation
- **Range Validation**: Specific error messages for pH (0-14) and moisture (0-100%) ranges
- **Success Confirmations**: Clear feedback for completed operations
- **Graceful Handling**: User-friendly error display with actionable information
- **Debugging Support**: Error codes and messages for troubleshooting

## Styling

- Consistent with existing application design
- Responsive grid layouts for forms
- Hover effects and visual feedback
- Color-coded buttons for different actions
- Clean, professional appearance
