"""
SmartQMS -- Flask ML API Server
================================
Exposes the trained Random Forest model as a REST API.
PHP backend sends queue features -> Flask returns predicted wait time.

Endpoints:
  POST /predict  -> { predicted_wait_minutes: 8.3 }
  GET  /health   -> { status: running, model: loaded }

Usage:
  python app.py
  -> API available at http://localhost:5000

Keep this running in a separate terminal during development.
"""

from pathlib import Path
import os

from flask import Flask, request, jsonify
import joblib
import numpy as np

from data_pipeline import FEATURE_COLS

app   = Flask(__name__)
MODEL = None
MODEL_PATH = Path(__file__).resolve().parent / 'model.pkl'
FEATURE_RANGES = {
    'queue_length': (0, 500, int),
    'hour_of_day': (0, 23, int),
    'day_of_week': (0, 6, int),
    'service_type_encoded': (1, 255, int),
    'client_type_encoded': (0, 2, int),
    'active_windows': (1, 100, int),
    'avg_service_time': (0.1, 480, float),
}

def load_model():
    global MODEL
    if MODEL_PATH.exists():
        MODEL = joblib.load(MODEL_PATH)
        print("[OK] model.pkl loaded successfully")
    else:
        print("[!]️  model.pkl not found -- run train_model.py first")

@app.route('/predict', methods=['POST'])
def predict():
    if MODEL is None:
        return jsonify({'error': 'Model not loaded. Run train_model.py first.'}), 503

    try:
        data = request.get_json(silent=True)
        if not isinstance(data, dict):
            return jsonify({'error': 'Request body must be a JSON object.'}), 400

        parsed = []
        for field in FEATURE_COLS:
            if field not in data:
                return jsonify({'error': f'Missing field: {field}'}), 400
            minimum, maximum, converter = FEATURE_RANGES[field]
            raw_value = data[field]
            if isinstance(raw_value, bool):
                return jsonify({'error': f'Field {field} must be numeric.'}), 400
            try:
                value = converter(raw_value)
            except (TypeError, ValueError, OverflowError):
                return jsonify({'error': f'Field {field} must be numeric.'}), 400
            if not np.isfinite(value) or value < minimum or value > maximum:
                return jsonify({
                    'error': f'Field {field} must be between {minimum} and {maximum}.'
                }), 400
            parsed.append(value)

        features = [parsed]
        prediction = MODEL.predict(features)[0]
        if not np.isfinite(prediction) or prediction < 0 or prediction > 480:
            return jsonify({'error': 'Model returned an invalid prediction.'}), 500
        return jsonify({
            'predicted_wait_minutes': round(float(prediction), 2),
            'status': 'success'
        })
    except Exception as e:
        app.logger.exception('Prediction failed')
        return jsonify({'error': 'Prediction could not be completed.'}), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'running',
        'model': 'loaded' if MODEL is not None else 'not loaded',
        'ready': MODEL is not None,
        'features': FEATURE_COLS,
    })

if __name__ == '__main__':
    load_model()
    print("SmartQMS ML API running at http://localhost:5000")
    debug_mode = os.environ.get('FLASK_DEBUG') == '1'
    app.run(host='127.0.0.1', debug=debug_mode, port=5000)
