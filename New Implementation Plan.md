# 🚀 BARANGAY HEALTH QMS - CODE GENERATION BLUEPRINT

This file serves as the definitive reference for the Queue Management System (QMS) with Machine Learning Based Waiting Time Prediction. Follow this exact architecture, database structure, and multi-language routing flow.

---

## 🗄️ 1. DATABASE CONFIGURATION (MySQL)

Create a MySQL database named `barangay_health_qms` and run the following structural schema exactly:

```sql
CREATE DATABASE IF NOT EXISTS barangay_health_qms;
USE barangay_health_qms;

-- Users table handles unified authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    job_title VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Core health services CRUD target
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    fallback_duration_mins INT DEFAULT 15,
    is_hidden TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Physical service desks
CREATE TABLE counters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    counter_number INT NOT NULL UNIQUE,
    counter_label VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Many-to-Many mapping for admin counter configuration
CREATE TABLE counter_services (
    counter_id INT,
    service_id INT,
    PRIMARY KEY (counter_id, service_id),
    FOREIGN KEY (counter_id) REFERENCES counters(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Ticket lifecycle engine
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_token VARCHAR(64) NOT NULL UNIQUE,
    ticket_number VARCHAR(10) NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    classification ENUM('regular', 'senior', 'pwd') NOT NULL,
    entry_type ENUM('walk-in', 'online') NOT NULL,
    status ENUM('scheduled', 'waiting', 'calling', 'in-progress', 'completed', 'void') DEFAULT 'waiting',
    service_id INT,
    assigned_counter_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    called_at TIMESTAMP NULL,       
    started_at TIMESTAMP NULL,      
    completed_at TIMESTAMP NULL,    
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (assigned_counter_id) REFERENCES counters(id)
);

-- Feedback collection system
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNIQUE,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);
```

---

## 🌐 2. APPLICATION ROUTING & BUSINESS LOGIC (PHP)

### 🔑 2.1 Unified Shared Login Route
*   **Path**: `POST /login`
*   **Behavior**: Check `users` table. If credentials match, read `role`. 
    *   If `admin`, redirect to `/admin/dashboard`.
    *   If `staff`, redirect to an intermediate page `/staff/select-counter` forcing them to claim an available row from `counters`, then redirect to `/staff/dashboard`.

### 📱 2.2 Client Intake & QR Token Engine
*   **Path**: `POST /queue/join` (Both remote web traffic and physical kiosk tablet submit here).
*   **Inputs**: `first_name`, `last_name`, `phone_number`, `service_id`, `classification`.
*   **Logic**: 
    1. Generate an alphanumerical token: `$token = bin2hex(random_bytes(16));`.
    2. Auto-generate sequential ticket codes per category (e.g., `G-101` for general, `I-204` for immunization).
    3. If entry type is walk-in, set `status = 'waiting'`. If online booking, set `status = 'scheduled'`.
    4. Save to `tickets`. Redirect to `/queue/ticket?token=$token`.
*   **View**: Display ticket info and render a QR code packing the tracking URL: `https://[yourdomain]/track?token=$token`.

### ⏱️ 2.3 No-Login Real-Time Mobile Tracker
*   **Path**: `GET /track?token=xxx`
*   **Front-end behavior**: Run JavaScript AJAX polling every 5 seconds to read row data from `tickets` via the token.
*   **State-driven mutations**:
    *   `waiting`: Display current position (`COUNT` of tickets ahead with lower `id` or creation time). Fire API query to Python ML microservice to get predicted wait minutes.
    *   `calling`: Trigger loud notification chimes, turn screen green, flash target counter description.
    *   `in-progress`: Display status message "You are currently being attended to."
    *   `completed`: **IMPORTANT:** Block/hide the tracker metadata instantly. Force-render a 1-5 Star Selection Matrix and textual comments area. User *must* complete submission to release the interface. Save parameters to `feedback` table.

### 👩‍⚕️ 2.4 Staff Workstation Dashboard (3-Zone UI Grid)
Build a single-page view split cleanly into three layout areas:
*   **Zone 1: Active Ticket Card**:
    *   Shows the ticket currently being served (`status = 'calling' or 'in-progress'`).
    *   Action Buttons:
        *   `Call Next`: Pulls longest-waiting patient whose `service_id` matches current counter capabilities. Changes status to `calling`, updates `called_at = NOW()`. Triggers panelist-requested automated 10-minute grace timer check: `UPDATE tickets SET status = 'void' WHERE status = 'calling' AND TIMESTAMPDIFF(MINUTE, called_at, NOW()) >= 10;`.
        *   `Recall`: Re-broadcasts current ticket number to `/public-display`.
        *   `Start Service`: Fires when patient sits down. Status to `in-progress`, updates `started_at = NOW()`.
        *   `Complete`: Status to `completed`, updates `completed_at = NOW()`.
        *   `Void / Late`: Manual button to instantly drop a missing appointee. Status to `void`.
*   **Zone 2: Desktop Walk-In Quick Form**: An embedded copy of the client intake form so the staff counter agent can register offline/elderly visitors directly without shifting pages.
*   **Zone 3: Real-Time Queue Grid Monitor**: A scrollable data table tracking all active waiting patients sorted chronologically by arrival time.

### 🛠️ 2.5 Admin Control Panel Modules
*   **Staff CRUD**: Forms to create, edit, or deactivate accounts.
*   **Health Services CRUD**: Create/update services, specify baseline falling durations, and toggle service visibilities (`is_hidden = 1` removes option from client selectors).
*   **Counter Mapping Manager**: Edit counter profiles with multi-select checkboxes linked to the `services` table, populating the `counter_services` pivot rows.

### 📺 2.6 Public TV Display Monitor View
*   **Path**: `GET /public-display`
*   **Properties**: Unauthenticated, read-only full-screen presentation interface.
*   **Behavior**: Constantly checks the database for tickets updated to `status = 'calling'`. When flagged, plays an audible alert chime and updates a high-contrast marquee layout matching: `Ticket Number -> Counter Label`.

---

## 🤖 3. PREDICTIVE MICROSERVICE ENGINE (Python)

To bypass the limitations of Linear Regression as flagged by panelists, build an advanced **Random Forest Regressor** pipeline.

### 🐍 3.1 Model Training Module (`train_model.py`)
Run this standalone script to generate and save your optimized decision tree ensemble model:

```python
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
import joblib

# Load tabular history data logged by your application
df = pd.read_csv('historical_qms_data.csv')

# Features: Active queue length, Time of day, Numerical Service ID, Active counter counts
X = df[['queue_length', 'hour_of_day', 'service_id', 'active_counters']]
y = df['actual_wait_time']

X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# Random Forest effectively maps complex clinic bottlenecks and time non-linear spikes
model = RandomForestRegressor(n_estimators=150, random_state=42)
model.fit(X_train, y_train)

# Output evaluation metrics to present during system defense documentation
print(f"Model Accuracy Score: {model.score(X_test, y_test) * 100:.2f}%")

# Save model parameters as a lightweight static asset binary
joblib.dump(model, 'qms_forest_predictor.pkl')
```

### ⚡ 3.2 Live Prediction REST API Gateway (`main.py`)
Deploy a lightweight server utilizing **FastAPI** to continuously serve predictions to the PHP backend.

```python
from fastapi import FastAPI
import joblib
from pydantic import BaseModel

app = FastAPI()

# Load saved machine learning brain structure into memory
ml_engine = joblib.load('qms_forest_predictor.pkl')

class PredictRequest(BaseModel):
    queue_length: int
    hour_of_day: int
    service_id: int
    active_counters: int

@app.post("/predict")
def get_wait_time(data: PredictRequest):
    # Process structured vector matrix through the forest decision array
    input_features = [[data.queue_length, data.hour_of_day, data.service_id, data.active_counters]]
    prediction = ml_engine.predict(input_features)
    
    return {"predicted_wait_time_mins": round(float(prediction[0]), 1)}
```

---

## 📈 4. ANALYTICS & STATISTICAL REPORTS (PHP)

Implement data rendering pipelines inside the Admin Panel utilizing SQL queries combined with a front-end rendering framework like **Chart.js** to complete your **Customer Satisfaction Report**:

### 📈 4.1 Service Performance & Satisfaction Averages
```sql
SELECT s.service_name, AVG(f.rating) as average_score, COUNT(f.id) as feedback_count
FROM feedback f
JOIN tickets t ON f.ticket_id = t.id
JOIN services s ON t.service_id = s.id
GROUP BY s.id;
```

### 📈 4.2 Machine Learning Evaluation & Variance Tracking
Compare true logged wait gaps against the baseline estimations predicted by your model to prove system precision to the research panel:
```sql
SELECT id, ticket_number, TIMESTAMPDIFF(MINUTE, created_at, started_at) as true_duration
FROM tickets 
WHERE status = 'completed';
```