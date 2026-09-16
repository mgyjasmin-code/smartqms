"""Reusable, leakage-resistant SmartQMS model training helpers."""

from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path
import json
import os
import tempfile

import joblib
import numpy as np
from sklearn.base import clone
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.tree import DecisionTreeRegressor

from data_pipeline import FEATURE_COLS, TARGET_COL, TRAINING_SOURCE


ARTIFACT_VERSION = 2
MODEL_PATH = Path(__file__).resolve().parent / "model.pkl"
MODEL_METADATA_PATH = Path(__file__).resolve().parent / "model.metadata.json"


def algorithm_candidates() -> dict[str, object]:
    return {
        "Linear Regression": LinearRegression(),
        "Decision Tree": DecisionTreeRegressor(random_state=42, min_samples_leaf=2),
        "Gradient Boosting": GradientBoostingRegressor(random_state=42),
        "Random Forest": RandomForestRegressor(
            n_estimators=200,
            random_state=42,
            min_samples_leaf=2,
            n_jobs=-1,
        ),
    }


def safe_mape(actual, predicted) -> float | None:
    actual_values = np.asarray(actual, dtype=float)
    predicted_values = np.asarray(predicted, dtype=float)
    nonzero = np.abs(actual_values) > np.finfo(float).eps
    if not nonzero.any():
        return None
    return float(
        np.mean(
            np.abs(
                (actual_values[nonzero] - predicted_values[nonzero])
                / actual_values[nonzero]
            )
        )
        * 100
    )


def evaluation_metrics(actual, predicted) -> dict[str, float | None]:
    actual_values = np.asarray(actual, dtype=float)
    predicted_values = np.asarray(predicted, dtype=float)
    r2 = float(r2_score(actual_values, predicted_values)) if len(actual_values) >= 2 else None
    return {
        "mae": float(mean_absolute_error(actual_values, predicted_values)),
        "rmse": float(np.sqrt(mean_squared_error(actual_values, predicted_values))),
        "r2": r2,
        "mape": safe_mape(actual_values, predicted_values),
    }


def train_and_compare(frame):
    """Use oldest observations for training and newest observations for evaluation."""
    test_size = max(5, int(np.ceil(len(frame) * 0.2)))
    if test_size >= len(frame):
        raise ValueError("Not enough observations for a chronological holdout.")

    train_frame = frame.iloc[:-test_size]
    test_frame = frame.iloc[-test_size:]
    train_x = train_frame[FEATURE_COLS]
    train_y = train_frame[TARGET_COL]
    test_x = test_frame[FEATURE_COLS]
    test_y = test_frame[TARGET_COL]

    comparisons = []
    candidates = algorithm_candidates()
    for name, estimator in candidates.items():
        estimator.fit(train_x, train_y)
        predicted = np.clip(estimator.predict(test_x), 0, 480)
        metrics = evaluation_metrics(test_y, predicted)
        comparisons.append({"algorithm": name, **metrics})

    best = min(comparisons, key=lambda row: (row["mae"], row["rmse"]))
    deployed = clone(candidates[best["algorithm"]])
    deployed.fit(frame[FEATURE_COLS], frame[TARGET_COL])
    return deployed, comparisons, best, len(train_frame), len(test_frame)


def build_artifact(model, best, row_count: int, train_count: int, test_count: int) -> dict:
    trained_at = datetime.now(timezone.utc)
    return {
        "artifact_version": ARTIFACT_VERSION,
        "model": model,
        "metadata": {
            "training_source": TRAINING_SOURCE,
            "feature_names": list(FEATURE_COLS),
            "target_name": TARGET_COL,
            "trained_at": trained_at.isoformat(),
            "model_version": f"qms-wait-v2-{trained_at.strftime('%Y%m%d')}",
            "row_count": row_count,
            "train_count": train_count,
            "test_count": test_count,
            "algorithm": best["algorithm"],
            "metrics": {
                key: (None if value is None else round(float(value), 6))
                for key, value in best.items()
                if key != "algorithm"
            },
        },
    }


def save_artifact_atomic(
    artifact: dict,
    destination: Path = MODEL_PATH,
    metadata_destination: Path = MODEL_METADATA_PATH,
) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary_path = None
    temporary_metadata_path = None
    try:
        with tempfile.NamedTemporaryFile(
            prefix="smartqms-model-",
            suffix=".tmp",
            dir=destination.parent,
            delete=False,
        ) as temporary:
            temporary_path = Path(temporary.name)
        joblib.dump(artifact, temporary_path)
        os.replace(temporary_path, destination)
        with tempfile.NamedTemporaryFile(
            mode="w",
            encoding="utf-8",
            prefix="smartqms-model-metadata-",
            suffix=".tmp",
            dir=metadata_destination.parent,
            delete=False,
        ) as temporary_metadata:
            temporary_metadata_path = Path(temporary_metadata.name)
            json.dump(artifact["metadata"], temporary_metadata, indent=2, sort_keys=True)
        os.replace(temporary_metadata_path, metadata_destination)
    finally:
        if temporary_path is not None and temporary_path.exists():
            temporary_path.unlink()
        if temporary_metadata_path is not None and temporary_metadata_path.exists():
            temporary_metadata_path.unlink()
