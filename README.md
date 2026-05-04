# Smart Farming Application

A comprehensive IoT-based agricultural management system for monitoring soil sensors, managing plant nutrition requirements, and controlling irrigation systems through a web-based dashboard.

---

## Table of Contents
1. [Dashboard Page](#dashboard-page)
2. [Sensors Page](#sensors-page)
3. [Manage Sensors Page](#manage-sensors-page)
4. [Plants Management](#plants-management)
5. [Nutrition Management](#nutrition-management)
6. [Tank Data Management](#tank-data-management)
7. [Authentication Pages](#authentication-pages)
8. [Notifications System](#notifications-system)

---

## Dashboard Page

### File: `pages/dashboard.php` + `assets/js/dashboard.js`

**Purpose**: Main landing page for authenticated users displaying an overview of the farm system.

#### How It Works:

**Backend (PHP)**:
- Requires user session authentication (redirects to login if not authenticated)
- Fetches three liquid sensor tank names from the `liquidsensorinfo` table (Tank IDs 1, 2, 3)
- Displays welcome message with logged-in username
- Renders multiple navigation cards linking to different farm management features
- Includes three animated water tank visualizations using SVG

**Frontend (HTML/CSS)**:
- Responsive grid layout with dashboard cards
- Each card represents a feature section (Plant Management, Tank Overview, etc.)
- Water tanks display with animated wave effects using SVG
- Each tank links to detailed tank data view
- Navigation buttons for quick access to different modules

**JavaScript (`dashboard.js`)**:
```javascript
// Fetches liquid sensor levels from API endpoint
function fetchLiquidLevel()
// Updates tank visual height based on sensor reading
function updateTank(sensorID, liters, percent)
```

**Flow**:
1. Page loads → PHP fetches tank names from database
2. JavaScript executes on DOMContentLoaded
3. Calls `fetchLiquidLevel()` to get current tank levels from `api/fetch_liquidlevel_data.php`
4. Calculates liquid liters based on tank dimensions (220L barrel tank)
5. Updates tank visualization every 1000ms with `setInterval()`
6. User can click on tank cards to view detailed tank data

**Database Tables Used**:
- `users` - Session validation
- `liquidsensorinfo` - Tank names
- `liquidlevelsensor` - Current liquid levels (via API)

---

## Sensors Page

### File: `pages/sensors.php` + `assets/js/sensors.js`

**Purpose**: Display and manage all sensor data readings with filtering and pagination options.

#### How It Works:

**Backend (PHP)**:
- Requires user authentication
- Implements pagination (15 records per page)
- Supports advanced filtering:
  - By Sensor ID
  - By Location
  - By Date Range (from/to)
- Fetches sensor data with related sensor names and farm locations via JOIN queries
- Handles POST requests for deleting sensor data records
- Generates sensor and location dropdown lists for filtering

**Frontend (HTML/CSS)**:
- Filter form with dropdown selectors for sensors and locations
- Date picker inputs for date range filtering
- Displays sensor data in a table format
- Pagination controls at bottom
- Delete buttons for each record with confirmation

**JavaScript (`sensors.js`)**:
```javascript
// Auto-reload sensor data every 5 seconds on first page
function reloadSensorData()
// Fetches updated data from API
fetch('../api/fetch_sensor_data.php?' + params.toString())
```

**Flow**:
1. Page loads with initial 15 sensor records
2. User applies filters (optional)
3. Page refreshes with filtered results and pagination
4. If on page 1, JavaScript auto-refreshes table data every 5 seconds
5. `reloadSensorData()` fetches fresh data from `fetch_sensor_data.php` API
6. Updates table body with new measurements
7. User can delete individual sensor readings (cascades with confirmation)

**Database Tables Used**:
- `sensordata` - Main sensor readings table
- `sensorinfo` - Sensor details (JOINed for names)
- `farmlocation` - Location names (JOINed)

**API Endpoint**: `api/fetch_sensor_data.php`
- Accepts: sensor, location, dateFrom, dateTo query parameters
- Returns: JSON array of sensor records with pagination

---

## Manage Sensors Page

### File: `pages/manage_sensors.php` + `assets/js/manage_sensors.js`

**Purpose**: Register new sensors, configure sensor deployments, and monitor sensor connectivity status.

#### How It Works:

**Backend (PHP)**:
- Fetches all sensors with their deployment information
- Queries farm locations for deployment dropdown options
- Handles POST requests for sensor registration/naming
- Updates `sensorinfo` table with sensor name and registration status
- Displays success/error messages

**Frontend (HTML/CSS)**:
- Displays sensor boxes for each registered IoT device
- Shows sensor status indicators (Offline/Online/Configured/Unregistered)
- Location selection dropdown for each sensor
- Register button for unregistered sensors (opens modal)
- Disconnect button for configured sensors
- Conditionally displayed UI elements based on sensor state

**JavaScript (`manage_sensors.js`)**:
```javascript
// Define UI states
const UI_STATES = {
    OFFLINE: 'offline',
    ONLINE_IDLE: 'online_idle',
    CONFIGURED: 'configured',
    UNREGISTERED: 'unregistered'
}

// Fetch all sensors and their current status
function updateSensors()

// Render UI based on sensor state
function renderState(box, state)

// Handle location selection toggle
function toggleLocation(cb)

// Get elements within sensor box
function getBoxEls(box)
```

**Flow**:
1. Page loads → PHP fetches sensors with deployment status
2. JavaScript calls `updateSensors()` function
3. Makes AJAX request to `api/fetch_sensor.php`
4. For each sensor, determines current state based on:
   - `isRegistered` status
   - `sensorStatus` (online/offline)
   - `deployment.isConnected` status
5. Calls `renderState()` to show appropriate UI elements for that state
6. User interactions:
   - **Unregistered sensor**: Click "Register" → Modal form → Enter name → POST to register
   - **Online sensors**: Select location from dropdown → Click "Send" → Deploy sensor
   - **Configured sensors**: Shows location label and "Disconnect" button
   - **Offline sensors**: Shows status only, no options
7. Real-time status updates via polling

**States Explained**:
- **OFFLINE**: Sensor not connected, registered or not
- **UNREGISTERED**: Sensor online but not yet registered (MAC address discovered but not named)
- **ONLINE_IDLE**: Sensor registered but not deployed to a location
- **CONFIGURED**: Sensor deployed and connected to a location

**Database Tables Used**:
- `sensorinfo` - Sensor master data
- `deployment` - Sensor-to-location mappings
- `farmlocation` - Available farm locations

**API Endpoint**: `api/fetch_sensor.php`
- Returns: JSON array of sensors with their current status and deployment info

---

## Plants Management

### File: `pages/plants.php`

**Purpose**: Display all registered plant species and varieties in the system.

#### How It Works:

**Backend (PHP)**:
- Requires user authentication
- Queries `plantinfo` table for all plants
- Orders results alphabetically by plant name
- No direct JavaScript interaction (static page)

**Frontend (HTML/CSS)**:
- Displays plants in a responsive grid layout
- Each plant card shows:
  - Plant name
  - Plant variety
  - Two action buttons
- "Add Nutrition" link → navigates to `add_nutrition.php` with plant ID
- "View Nutrition" link → navigates to `view_nutrition.php` with plant ID
- "Add New Plant" button → navigates to `add_plant.php`
- Empty state message if no plants exist

**Database Tables Used**:
- `plantinfo` - Plant master data

---

### File: `pages/add_plant.php`

**Purpose**: Register a new plant species/variety in the system.

#### How It Works:

**Backend (PHP)**:
- Requires user authentication
- Handles POST form submission
- Validates that plant name is provided
- Inserts new record into `plantinfo` table
- Returns auto-generated plant ID
- Shows success message with navigation links

**Frontend (HTML/CSS)**:
- Simple form with two input fields:
  - Plant Name (required)
  - Plant Variety (optional)
- Submit button inserts data
- After success, offers links to add nutrition or view all plants

**Database Tables Used**:
- `plantinfo` - Stores new plant records

---

## Nutrition Management

### File: `pages/add_nutrition.php`

**Purpose**: Define optimal soil conditions and nutrient requirements for specific plants.

#### How It Works:

**Backend (PHP)**:
- Requires plant ID in GET parameter
- Validates plant exists, redirects to plants list if not
- Fetches plant name for display
- Handles POST form submission with comprehensive soil parameter inputs
- Inserts record into `plantnutrionneed` table with:
  - Nutrition set name (custom profile name)
  - NPK values (Nitrogen, Phosphorus, Potassium)
  - EC level (Electrical Conductivity)
  - pH level
  - Temperature
  - Moisture percentage
  - Flow rate for irrigation

**Frontend (HTML/CSS)**:
- Displays selected plant name at top
- Form fields for all nutrition parameters:
  - Text input for profile name
  - Numeric inputs for NPK, EC, Temperature, Moisture
  - Decimal inputs for pH, Flow Rate
- Shows associated plant clearly
- Error/success messaging

**Database Tables Used**:
- `plantinfo` - Plant validation
- `plantnutrionneed` - Nutrition profile storage

**Typical Values**:
- NPK: 5-100 (ppm or percentage)
- EC: 800-2000
- pH: 5.0-8.0
- Temperature: 15-35 Celsius
- Moisture: 20-80 percent
- Flow Rate: 0.1-10 liters/hour

---

### File: `pages/view_nutrition.php`

**Purpose**: Display all nutrition profiles defined for a specific plant.

#### How It Works:

**Backend (PHP)**:
- Requires plant ID in GET parameter
- Fetches plant name for context
- Queries `plantnutrionneed` table for all profiles for that plant
- Orders by nutrition set name
- No JavaScript interaction (static display)

**Frontend (HTML/CSS)**:
- Shows plant name at top
- Navigation links to add new nutrition set
- Displays all nutrition profiles in card/table format
- Shows all soil parameters for each profile
- Allows editing/deleting nutrition sets

**Database Tables Used**:
- `plantinfo` - Plant context
- `plantnutrionneed` - Nutrition profile data

---

## Tank Data Management

### File: `pages/view_tank_data.php` (No paired JS file)

**Purpose**: Monitor water tank levels, pump events, and control tank operations.

#### How It Works:

**Backend (PHP)**:
- Requires tank ID in GET parameter
- Validates tank exists in `liquidsensorinfo` table
- Fetches tank name
- Handles POST requests for three actions:
  1. **Watering**: Insert pump event with watering status = 1
  2. **Mixing**: Set wateringFlag = 1 (mixing solution mode)
  3. **Reset**: Set wateringFlag = 0 (return to normal operation)
- Implements pagination for historical pump events (15 per page)
- Supports date range filtering for pump events
- Shows current mixing state and disables buttons accordingly

**Frontend (HTML/CSS)**:
- Tank visualization with current liquid level
- Current state display (Idle/Mixing)
- Action buttons for watering, mixing, reset operations
- Pump event history table with pagination
- Date range filter for historical data
- Shows watering volume, status, and timestamps for each event

**Database Tables Used**:
- `liquidsensorinfo` - Tank master data
- `liquidlevelsensor` - Current liquid levels
- `tankpumpevent` - Historical pump/watering events

**API Flow** (No real-time JS updates):
- Page is server-rendered with database data
- Forms POST back to same page for actions
- Page redirects after action completion

---

## Authentication Pages

### File: `pages/login.php`

**Purpose**: User authentication and session creation.

#### How It Works:

**Backend (PHP)**:
- Handles POST form submission
- Validates username/email and password
- Queries `users` table for matching username or email
- Uses `password_verify()` to check hashed password
- Creates session variables on successful login:
  - `$_SESSION['userID']`
  - `$_SESSION['username']`
- Redirects to dashboard on success
- Shows error message on failure

**Frontend (HTML/CSS)**:
- Glassmorphism design with gradient background
- Two input fields: Username/Email and Password
- Submit button for login
- Link to registration page
- Error message display area

---

### File: `pages/register.php`

**Purpose**: New user account creation.

#### How It Works:

**Backend (PHP)**:
- Handles POST form submission
- Validates:
  - All fields provided
  - Valid email format
  - Passwords match
  - Username/email not already registered
- Hashes password using `password_hash()` with bcrypt
- Inserts new user into `users` table
- Shows success message with login link

**Frontend (HTML/CSS)**:
- Form with fields:
  - Username input
  - Email input
  - Password input
  - Confirm password input
- Real-time validation messages
- Link to login page for existing users

**Database Tables Used**:
- `users` - User account storage

**Security Features**:
- Password hashing with PASSWORD_DEFAULT (bcrypt)
- Unique constraint on username and email
- Email format validation

---

### File: `pages/logout.php`

**Purpose**: Destroy user session and logout.

#### How It Works:

**Backend (PHP)**:
- Starts session
- Calls `session_unset()` to clear session variables
- Calls `session_destroy()` to destroy session
- Redirects to login page
- No HTML output

---

## Notifications System

### File: `includes/notification.php` + `assets/js/notifications.js`

**Purpose**: Real-time system-wide notifications for alerts and status updates.

#### How It Works:

**Backend (PHP)** (`includes/notification.php`):
- Renders HTML structure for notification component:
  - Bell icon with counter badge
  - Dropdown notification list
- No backend logic (mostly HTML/CSS)
- Included in pages that need notification support

**Frontend (HTML/CSS)** (`includes/notification.css`):
- Bell icon styled with Font Awesome
- Badge counter positioned on bell
- Dropdown menu with notification list
- Notification items with type-based styling

**JavaScript (`notification.js`)**:
```javascript
// Handle bell click
bell.addEventListener("click", (e) => {
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
    markAsRead();
});

// Fetch notifications from API
function loadNotifications()
// Make notifications as read
function markAsRead()
```

**Flow**:
1. Bell icon appears on page header
2. User clicks bell → Dropdown toggles open
3. JavaScript calls `markAsRead()` → POST to `update_notification_indicator.php`
4. Calls `loadNotifications()` → GET from `fetch_notifications.php`
5. Renders notification list in dropdown
6. Shows unread count badge
7. Auto-refreshes every 5 seconds with `setInterval()`
8. Notification types: alert (red), warning (orange), info (blue), success (green)

**Related API Endpoints**:
- `api/fetch_notifications.php` - Returns user's notifications
- `api/update_notification_indicator.php` - Marks notifications as read

---

## Database Schema Overview

```
users (Authentication)
├── userID
├── username
├── password_hash
├── email
└── timestamps

plantinfo (Plant Master Data)
├── plantID
├── plantName
└── plantVariety

plantnutrionneed (Nutrition Profiles)
├── nutritionID
├── plantID (FK)
├── nutritionSetName
├── NPK values
├── EC, pH, Temperature, Moisture
└── flowRate

sensorinfo (IoT Sensor Data)
├── soilSensorID
├── sensorName
├── sensorMacAddress
├── isRegistered
├── sensorStatus
├── last_sensor_online
└── dateAdded

sensordata (Sensor Readings)
├── SensorDataID
├── SoilSensorID (FK)
├── locationID (FK)
├── NPK, EC, pH, Temperature, Moisture
├── liquidVolume
└── DateTime

farmlocation (Farm Locations)
├── locationID
├── farmName
└── dateAdded

deployment (Sensor Deployment)
├── deploymentID
├── soilSensorID (FK)
├── locationID (FK)
└── isConnected

liquidsensorinfo (Tank Master)
├── liquidsensorID
└── liquidtankname

liquidlevelsensor (Tank Readings)
├── liquidsensorreadID
├── liquidsensorID (FK)
├── currentliquidlevel
└── dateandtime

tankpumpevent (Pump Events)
├── tankpumpventID
├── liquidsensorID (FK)
├── wateringstatus
├── wateringvolume
├── wateringFlag
└── dateandtime

notification (Notify)
├── notificationID
├── userID (FK)
├── message
├── isRead
└── createdAT
```

---

## Key Features Summary

### Real-Time Updates
- **Dashboard**: Tank levels update every 1 second
- **Sensors Page**: Data refreshes every 5 seconds on page 1
- **Manage Sensors**: Sensor status updates continuously
- **Notifications**: Check for new notifications every 5 seconds

### Session Management
- All pages require user authentication (except login/register)
- Session hijacking prevented with session validation
- Automatic logout on session expire (redirect to login)

### Data Filtering
- **Sensors Page**: By sensor, location, date range
- **Tank Data**: By date range
- All filters preserve pagination state

### Security
- Prepared statements for SQL injection prevention
- Password hashing with bcrypt
- HTML escaping to prevent XSS
- CSRF protection through POST method
- Email validation on registration

### Responsive Design
- Mobile-friendly layouts
- Glassmorphism design aesthetic
- Gradient backgrounds and smooth transitions
- Touch-friendly buttons and inputs

---

## API Endpoints Used

```
GET  /api/fetch_sensor_data.php      - Get sensor readings with filters
POST /api/receive_sensor_data.php    - Accept IoT sensor data
GET  /api/fetch_sensor.php           - Get sensor list with status
GET  /api/fetch_liquidlevel_data.php - Get tank liquid levels
GET  /api/fetch_notifications.php    - Get user notifications
POST /api/update_notification_indicator.php - Mark notifications as read
POST /api/connect_sensor.php         - Register/connect sensor
POST /api/disconnect_sensor.php      - Deregister/disconnect sensor
POST /api/webServer.php              - IoT device heartbeat/registration
```

---

## Installation & Setup

1. **Database**: Import `schema.sql` into MySQL
2. **Configuration**: Update credentials in `db.php`
3. **Web Server**: Place files in web root (e.g., `/xampp/htdocs/smart_farming/`)
4. **Access**: Navigate to `http://localhost/smart_farming/`
5. **Register**: Create user account via registration page
6. **Configure**: Add plants, farm locations, and register IoT sensors

---

**Version**: 1.0  
**Last Updated**: February 2026  
**Status**: Active Development

