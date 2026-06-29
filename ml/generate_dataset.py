"""
SmartQMS -- Synthetic Dataset Generator
========================================
Generates a realistic synthetic queue dataset for model training.
Use this ONLY if the Kaggle dataset is unavailable.

Simulates a typical Barangay Health Center queue pattern:
  - Peak hours: 8AM–11AM
  - 2–4 service windows
  - 8 service types
  - Priority clients (senior/PWD) ~15% of traffic
  - Operating hours: 7AM–5PM, Monday–Friday

Usage:
  python generate_dataset.py
  -> Creates dataset/synthetic_queue_data.csv (5000 rows)
"""

import pandas as pd
import numpy as np

np.random.seed(42)
N = 5000

hours = np.random.choice(
    range(7, 17),
    size=N,
    p=[0.05, 0.15, 0.20, 0.18, 0.12, 0.10, 0.08, 0.05, 0.04, 0.03]
)
days            = np.random.randint(1, 6, N)       # Mon–Fri
service_types   = np.random.randint(1, 9, N)       # SVC-001 to SVC-008
client_types    = np.random.choice([0, 1, 2], N, p=[0.85, 0.10, 0.05])
active_windows  = np.random.randint(2, 5, N)
queue_lengths   = np.random.randint(0, 20, N)
avg_svc_time    = np.round(np.random.uniform(3.0, 8.0, N), 2)

# Simulate actual wait time with realistic patterns
base_wait = (queue_lengths / active_windows) * avg_svc_time
peak_mult = np.where((hours >= 8) & (hours <= 11), 1.3, 1.0)
prio_mult = np.where(client_types > 0, 0.6, 1.0)   # priority clients wait less
noise     = np.random.normal(0, 1.5, N)

actual_wait = np.clip(base_wait * peak_mult * prio_mult + noise, 1, 60)
actual_wait = np.round(actual_wait, 2)

df = pd.DataFrame({
    'queue_length'        : queue_lengths,
    'hour_of_day'         : hours,
    'day_of_week'         : days,
    'service_type_encoded': service_types,
    'client_type_encoded' : client_types,
    'active_windows'      : active_windows,
    'avg_service_time'    : avg_svc_time,
    'actual_wait_minutes' : actual_wait,
})

df.to_csv('dataset/synthetic_queue_data.csv', index=False)
print(f"Generated {N} rows -> dataset/synthetic_queue_data.csv")
print(f"   Avg wait time: {df['actual_wait_minutes'].mean():.2f} minutes")
print(f"   Peak hour rows (8-11AM): {((df['hour_of_day']>=8)&(df['hour_of_day']<=11)).sum()}")
