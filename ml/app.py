"""Validated Flask inference API for the SmartQMS wait-time model."""

from pathlib import Path
import hmac
import json
import os

from flask import Flask, jsonify, request
import joblib
import numpy as np
import pandas as pd

from data_pipeline import FEATURE_COLS, TRAINING_SOURCE


app = Flask(__name__)
MODEL = None
MODEL_METADATA = {}
MODEL_LOAD_ERROR = ""
MODEL_PATH = Path(__file__).resolve().parent / "model.pkl"
MODEL_METADATA_PATH = Path(__file__).resolve().parent / "model.metadata.json"
FEATURE_RANGES = {
    "queue_length": (0, 500, int),
    "hour_of_day": (0, 23, int),
    "day_of_week": (0, 6, int),
    "service_type_encoded": (1, 255, int),
    "client_type_encoded": (0, 2, int),
    "active_windows": (1, 100, int),
    "avg_service_time": (0.1, 480, float),
}


def request_is_authorized() -> bool:
    """Fail closed unless the server-only bearer token is configured and supplied."""
    expected = os.environ.get("PYTHON_ML_TOKEN", "").strip()
    if not expected:
        return False
    supplied = request.headers.get("Authorization", "")
    prefix = "Bearer "
    if not supplied.startswith(prefix):
        return False
    return hmac.compare_digest(supplied[len(prefix):], expected)


def prediction_confidence(prediction: float) -> float:
    """Derive a bounded quality score from the artifact holdout MAE."""
    metrics = MODEL_METADATA.get("metrics", {})
    try:
        mae = max(0.0, float(metrics.get("mae", 0.0)))
    except (TypeError, ValueError):
        mae = 0.0
    scale = max(5.0, prediction, mae * 2.0)
    return round(float(np.clip(1.0 - (mae / scale), 0.05, 0.99)), 5)


def load_model(
    path: Path = MODEL_PATH,
    metadata_path: Path = MODEL_METADATA_PATH,
) -> bool:
    """Load only versioned artifacts proven to come from queue_data.csv."""
    global MODEL, MODEL_METADATA, MODEL_LOAD_ERROR
    MODEL = None
    MODEL_METADATA = {}
    MODEL_LOAD_ERROR = ""

    if not path.is_file():
        MODEL_LOAD_ERROR = "Model artifact is missing. Run ml/train_model.py."
        return False

    try:
        if not metadata_path.is_file():
            raise ValueError(
                "Model metadata is missing; legacy or synthetic artifacts are disabled."
            )
        with metadata_path.open("r", encoding="utf-8") as metadata_file:
            sidecar_metadata = json.load(metadata_file)
        if sidecar_metadata.get("training_source") != TRAINING_SOURCE:
            raise ValueError("Model was not trained from ml/dataset/queue_data.csv.")
        if sidecar_metadata.get("feature_names") != FEATURE_COLS:
            raise ValueError("Model feature contract does not match the API.")

        artifact = joblib.load(path)
        if not isinstance(artifact, dict) or "model" not in artifact:
            raise ValueError("Legacy unverified model artifacts are not accepted.")
        metadata = artifact.get("metadata")
        if not isinstance(metadata, dict):
            raise ValueError("Model metadata is missing.")
        if metadata.get("training_source") != TRAINING_SOURCE:
            raise ValueError("Model was not trained from ml/dataset/queue_data.csv.")
        if metadata.get("feature_names") != FEATURE_COLS:
            raise ValueError("Model feature contract does not match the API.")
        if metadata != sidecar_metadata:
            raise ValueError("Model metadata does not match its verification sidecar.")
        model = artifact["model"]
        if not callable(getattr(model, "predict", None)):
            raise ValueError("Model does not expose predict().")
        MODEL = model
        MODEL_METADATA = metadata
        return True
    except Exception as error:
        MODEL_LOAD_ERROR = str(error)
        app.logger.warning("Model was not loaded: %s", error)
        return False


@app.route("/predict", methods=["POST"])
def predict():
    if not request_is_authorized():
        return jsonify({"error": "Prediction service authorization failed."}), 401
    if MODEL is None:
        return jsonify({"error": "Verified real-data model is not available."}), 503

    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({"error": "Request body must be a JSON object."}), 400

    parsed = {}
    for field in FEATURE_COLS:
        if field not in data:
            return jsonify({"error": f"Missing field: {field}"}), 400
        minimum, maximum, converter = FEATURE_RANGES[field]
        raw_value = data[field]
        if isinstance(raw_value, bool):
            return jsonify({"error": f"Field {field} must be numeric."}), 400
        try:
            value = converter(raw_value)
        except (TypeError, ValueError, OverflowError):
            return jsonify({"error": f"Field {field} must be numeric."}), 400
        if not np.isfinite(value) or value < minimum or value > maximum:
            return jsonify(
                {"error": f"Field {field} must be between {minimum} and {maximum}."}
            ), 400
        parsed[field] = value

    try:
        features = pd.DataFrame([parsed], columns=FEATURE_COLS)
        prediction = float(MODEL.predict(features)[0])
    except Exception:
        app.logger.exception("Prediction failed")
        return jsonify({"error": "Prediction could not be completed."}), 500

    if not np.isfinite(prediction) or prediction < 0 or prediction > 480:
        return jsonify({"error": "Model returned an invalid prediction."}), 500
    minutes = round(prediction, 2)
    model_version = MODEL_METADATA.get("model_version") or "qms-wait-v2-unversioned"
    return jsonify({
        "estimated_wait_minutes": minutes,
        "predicted_wait_minutes": minutes,
        "confidence": prediction_confidence(minutes),
        "model_version": model_version,
        "status": "success",
        "model": MODEL_METADATA.get("algorithm", "SmartQMS model"),
    })


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok" if MODEL is not None else "degraded", "ready": MODEL is not None})


load_model()

if __name__ == "__main__":
    print("SmartQMS ML API running at http://localhost:5000")
    app.run(
        host="127.0.0.1",
        debug=os.environ.get("FLASK_DEBUG") == "1",
        port=int(os.environ.get("SMARTQMS_ML_PORT", "5000")),
    )
