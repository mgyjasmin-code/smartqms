"""
SmartQMS -- Random Forest Model Training Script
==============================================
Trains the waiting time prediction model on queue data.

Dataset columns required:
  queue_length, hour_of_day, day_of_week, service_type_encoded,
  client_type_encoded, active_windows, avg_service_time,
  actual_wait_minutes

Usage:
  python train_model.py

Output:
  model.pkl             -- saved trained model
  Prints evaluation metrics: MAE, RMSE, R2, MAPE, CV score
"""

import pandas as pd
import numpy as np
import joblib
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

FEATURE_COLS = [
    'queue_length',
    'hour_of_day',
    'day_of_week',
    'service_type_encoded',
    'client_type_encoded',   # 0=regular, 1=senior, 2=pwd
    'active_windows',
    'avg_service_time',
]
TARGET_COL = 'actual_wait_minutes'

print("=" * 50)
print("  SmartQMS -- Random Forest Training")
print("=" * 50)

# Load dataset (Kaggle or system-generated)
try:
    df = pd.read_csv('dataset/kaggle_queue_data.csv')
    print(f"  Dataset loaded: {len(df)} rows")
except FileNotFoundError:
    print("  kaggle_queue_data.csv not found, trying queue_logs.csv...")
    df = pd.read_csv('dataset/queue_logs.csv')
    print(f"  Dataset loaded: {len(df)} rows")

# Feature and target
X = df[FEATURE_COLS]
y = df[TARGET_COL]

# Train/test split (80/20)
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

# Train Random Forest
model = RandomForestRegressor(
    n_estimators=100,
    random_state=42,
    n_jobs=-1,
)
model.fit(X_train, y_train)

# Predictions
y_pred = model.predict(X_test)

# Evaluation metrics
mae  = mean_absolute_error(y_test, y_pred)
rmse = np.sqrt(mean_squared_error(y_test, y_pred))
r2   = r2_score(y_test, y_pred)
mape = np.mean(np.abs((y_test - y_pred) / y_test)) * 100

# K-Fold Cross Validation (k=10)
cv_scores = cross_val_score(
    model, X, y, cv=10, scoring='neg_mean_absolute_error', n_jobs=-1
)
cv_mae = -cv_scores.mean()

print()
print(f"  MAE   : {mae:.4f} minutes")
print(f"  RMSE  : {rmse:.4f} minutes")
print(f"  R²    : {r2:.4f}")
print(f"  MAPE  : {mape:.2f}%")
print(f"  CV MAE (k=10): {cv_mae:.4f} minutes")
print()

# Feature importance
print("  Feature Importance:")
for feat, imp in sorted(
    zip(FEATURE_COLS, model.feature_importances_),
    key=lambda x: -x[1]
):
    print(f"    {feat:<28} {imp:.4f}")

# Save model
joblib.dump(model, 'model.pkl')
print()
print("  [OK] Model saved as model.pkl")
print("=" * 50)
