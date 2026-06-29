"""
SmartQMS -- Algorithm Comparison Script
========================================
Compares 4 ML algorithms for waiting time prediction.
Saves results to ml_comparison_logs table.
The best model is saved as model.pkl.

Algorithms compared:
  1. Linear Regression  (baseline -- as panelist requested)
  2. Decision Tree
  3. Gradient Boosting
  4. Random Forest      (expected winner)

Usage:
  python compare_algorithms.py

Requirements:
  pip install scikit-learn pandas numpy pymysql joblib
"""

import pandas as pd
import numpy as np
import joblib
from datetime import date

from sklearn.linear_model  import LinearRegression
from sklearn.tree           import DecisionTreeRegressor
from sklearn.ensemble       import GradientBoostingRegressor, RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics         import mean_absolute_error, mean_squared_error, r2_score

FEATURE_COLS = [
    'queue_length', 'hour_of_day', 'day_of_week',
    'service_type_encoded', 'client_type_encoded',
    'active_windows', 'avg_service_time',
]
TARGET_COL  = 'actual_wait_minutes'
DB_HOST     = 'localhost'
DB_USER     = 'root'
DB_PASS     = ''
DB_NAME     = 'smartqms'

# Load dataset
try:
    df = pd.read_csv('dataset/kaggle_queue_data.csv')
except FileNotFoundError:
    df = pd.read_csv('dataset/queue_logs.csv')

X = df[FEATURE_COLS]
y = df[TARGET_COL]
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

ALGORITHMS = {
    'Linear Regression'  : LinearRegression(),
    'Decision Tree'      : DecisionTreeRegressor(random_state=42),
    'Gradient Boosting'  : GradientBoostingRegressor(random_state=42),
    'Random Forest'      : RandomForestRegressor(n_estimators=100, random_state=42, n_jobs=-1),
}

results     = []
best_model  = None
best_name   = ''
best_rmse   = float('inf')

print()
print("=" * 60)
print("  SmartQMS -- ML Algorithm Comparison")
print("=" * 60)
print(f"  {'Algorithm':<22} {'MAE':>8} {'RMSE':>8} {'R2':>8} {'MAPE':>9}")
print("-" * 60)

for name, model in ALGORITHMS.items():
    model.fit(X_train, y_train)
    pred = model.predict(X_test)

    mae  = mean_absolute_error(y_test, pred)
    rmse = np.sqrt(mean_squared_error(y_test, pred))
    r2   = r2_score(y_test, pred)
    mape = np.mean(np.abs((y_test - pred) / y_test)) * 100

    marker = " <- BEST" if rmse < best_rmse else ""
    print(f"  {name:<22} {mae:>8.4f} {rmse:>8.4f} {r2:>8.4f} {mape:>8.2f}%{marker}")

    if rmse < best_rmse:
        best_rmse  = rmse
        best_name  = name
        best_model = model

    results.append({
        'algorithm' : name,
        'mae'       : round(mae,  4),
        'rmse'      : round(rmse, 4),
        'r2'        : round(r2,   4),
        'mape'      : round(mape, 2),
        'is_best'   : 0,
    })

print("=" * 60)
print(f"  Best algorithm: {best_name}")
print()

# Mark best
for r in results:
    if r['algorithm'] == best_name:
        r['is_best'] = 1

# Save best model
joblib.dump(best_model, 'model.pkl')
print(f"  [OK] {best_name} model saved as model.pkl")

# Save to database
try:
    import pymysql
    conn = pymysql.connect(
        host=DB_HOST, user=DB_USER,
        password=DB_PASS, database=DB_NAME
    )
    cur  = conn.cursor()
    today        = date.today()
    dataset_name = 'kaggle_queue_data.csv'
    sample_size  = len(df)

    for r in results:
        cur.execute("""
            INSERT INTO ml_comparison_logs
              (run_date, algorithm, mae, rmse, r2, mape, is_best,
               dataset_used, sample_size)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
        """, (today, r['algorithm'], r['mae'], r['rmse'],
              r['r2'], r['mape'], r['is_best'],
              dataset_name, sample_size))
    conn.commit()
    conn.close()
    print("  [OK] Results saved to ml_comparison_logs table")
    print("     -> View in Admin -> Reports -> Report 8")
except Exception as e:
    print(f"  [!]️  Could not save to database: {e}")
    print("     Results printed above -- record them manually in Chapter 4")
