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

from flask import Flask, request, jsonify
import joblib, os, numpy as np

app   = Flask(__name__)
MODEL = None

def load_model():
    global MODEL
    if os.path.exists('model.pkl'):
        MODEL = joblib.load('model.pkl')
        print("[OK] model.pkl loaded successfully")
    else:
        print("[!]️  model.pkl not found -- run train_model.py first")

@app.route('/predict', methods=['POST'])
def predict():
    if MODEL is None:
        return jsonify({'error': 'Model not loaded. Run train_model.py first.'}), 503

    try:
        data = request.get_json()
        features = [[
            int(data['queue_length']),
            int(data['hour_of_day']),
            int(data['day_of_week']),
            int(data['service_type_encoded']),
            int(data['client_type_encoded']),
            int(data['active_windows']),
            float(data['avg_service_time']),
        ]]
        prediction = MODEL.predict(features)[0]
        return jsonify({
            'predicted_wait_minutes': round(float(prediction), 2),
            'status': 'success'
        })
    except KeyError as e:
        return jsonify({'error': f'Missing field: {e}'}), 400
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status' : 'running',
        'model'  : 'loaded' if MODEL else 'not loaded',
    })

if __name__ == '__main__':
    load_model()
    print("SmartQMS ML API running at http://localhost:5000")
    debug_mode = os.environ.get('FLASK_DEBUG') == '1'
    app.run(host='127.0.0.1', debug=debug_mode, port=5000)
