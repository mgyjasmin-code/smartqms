"""Run one local prediction against the verified SmartQMS model artifact."""

import pandas as pd

from app import MODEL, MODEL_METADATA
from data_pipeline import FEATURE_COLS


if MODEL is None:
    raise SystemExit("No verified real-data model is available. Run train_model.py first.")

sample = {
    "queue_length": 5,
    "hour_of_day": 9,
    "day_of_week": 1,
    "service_type_encoded": 2,
    "client_type_encoded": 0,
    "active_windows": 3,
    "avg_service_time": 4.5,
}
result = MODEL.predict(pd.DataFrame([sample], columns=FEATURE_COLS))[0]
print(f"Model: {MODEL_METADATA.get('algorithm', 'SmartQMS model')}")
print(f"Predicted waiting time: {float(result):.2f} minutes")
