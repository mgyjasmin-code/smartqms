import joblib
from pathlib import Path

# SmartQMS -- Standalone Prediction Test
# Run: python predict.py

model = joblib.load(Path(__file__).resolve().parent / 'model.pkl')

# Sample input -- adjust values to test different scenarios
sample = [[
    5,      # queue_length: 5 people waiting
    9,      # hour_of_day: 9AM (peak hour)
    1,      # day_of_week: Monday
    2,      # service_type_encoded: Vaccination
    0,      # client_type_encoded: 0=regular
    3,      # active_windows: 3 windows open
    4.5,    # avg_service_time: 4.5 minutes average
]]

result = model.predict(sample)[0]
print(f"Predicted waiting time: {result:.2f} minutes")
