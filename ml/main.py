"""FastAPI gateway for the verified SmartQMS Random Forest artifact."""

from __future__ import annotations

import hmac
import os
from typing import Optional

from fastapi import FastAPI, Header, HTTPException
import numpy as np
import pandas as pd
from pydantic import BaseModel, Field

import app as model_service
from data_pipeline import FEATURE_COLS


api = FastAPI(title="SmartQMS wait-time prediction", version="2.0")
app = api


class PredictRequest(BaseModel):
    queue_length: int = Field(ge=0, le=500)
    hour_of_day: int = Field(ge=0, le=23)
    day_of_week: int = Field(ge=0, le=6)
    service_type_encoded: int = Field(ge=1, le=255)
    client_type_encoded: int = Field(ge=0, le=2)
    active_windows: int = Field(ge=1, le=100)
    avg_service_time: float = Field(gt=0, le=480)


def authorize(authorization: Optional[str]) -> None:
    expected = os.environ.get("PYTHON_ML_TOKEN", "").strip()
    if not expected:
        raise HTTPException(status_code=503, detail="Prediction service is not configured.")
    prefix = "Bearer "
    supplied = authorization or ""
    if not supplied.startswith(prefix) or not hmac.compare_digest(
        supplied[len(prefix):], expected
    ):
        raise HTTPException(status_code=401, detail="Prediction service authorization failed.")


@api.get("/health")
def health() -> dict:
    return {"status": "ok" if model_service.MODEL is not None else "degraded", "ready": model_service.MODEL is not None}


@api.post("/predict")
def predict(
    data: PredictRequest,
    authorization: Optional[str] = Header(default=None),
) -> dict:
    authorize(authorization)
    if model_service.MODEL is None:
        raise HTTPException(status_code=503, detail="Verified real-data model is not available.")

    features = pd.DataFrame([data.model_dump()], columns=FEATURE_COLS)
    try:
        raw_prediction = float(model_service.MODEL.predict(features)[0])
    except Exception as error:
        raise HTTPException(status_code=500, detail="Prediction could not be completed.") from error
    if not np.isfinite(raw_prediction) or raw_prediction < 0 or raw_prediction > 480:
        raise HTTPException(status_code=500, detail="Model returned an invalid prediction.")

    minutes = round(raw_prediction, 2)
    metadata = model_service.MODEL_METADATA
    return {
        "estimated_wait_minutes": minutes,
        "predicted_wait_minutes": minutes,
        "predicted_wait_time_mins": round(minutes, 1),
        "confidence": model_service.prediction_confidence(minutes),
        "model_version": metadata.get("model_version") or "qms-wait-v2-unversioned",
        "status": "success",
        "model": metadata.get("algorithm", "SmartQMS model"),
    }
