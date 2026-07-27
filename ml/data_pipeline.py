"""Validated queue_data.csv contract for SmartQMS wait-time models."""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

import numpy as np
import pandas as pd
import pymysql


FEATURE_COLS = [
    "queue_length",
    "hour_of_day",
    "day_of_week",
    "service_type_encoded",
    "client_type_encoded",
    "active_windows",
    "avg_service_time",
]
TARGET_COL = "actual_wait_minutes"
REQUIRED_COLS = FEATURE_COLS + [TARGET_COL]
SOURCE_COLS = [
    "arrival_time",
    "start_time",
    "finish_time",
    "wait_time",
    "queue_length",
]
DATASET_PATH = Path(__file__).resolve().parent / "dataset" / "queue_data.csv"
TRAINING_SOURCE = "ml/dataset/queue_data.csv"
DEFAULT_MINIMUM_ROWS = 30

RANGES = {
    "queue_length": (0, 500),
    "hour_of_day": (0, 23),
    "day_of_week": (0, 6),
    "service_type_encoded": (1, 255),
    "client_type_encoded": (0, 2),
    "active_windows": (1, 100),
    "avg_service_time": (0.1, 480),
    "actual_wait_minutes": (0, 480),
}


class DatasetValidationError(ValueError):
    """Raised when the configured historical queue dataset is not trustworthy."""


def minimum_training_rows() -> int:
    raw_value = os.environ.get("SMARTQMS_ML_MIN_ROWS", str(DEFAULT_MINIMUM_ROWS))
    try:
        value = int(raw_value)
    except ValueError as error:
        raise DatasetValidationError("SMARTQMS_ML_MIN_ROWS must be an integer.") from error
    if value < 10:
        raise DatasetValidationError("SMARTQMS_ML_MIN_ROWS must be at least 10.")
    return value


def database_config() -> dict[str, Any]:
    """Return settings used only to persist verified comparison results."""
    return {
        "host": os.environ.get("SMARTQMS_ML_DB_HOST", "127.0.0.1"),
        "port": int(os.environ.get("SMARTQMS_ML_DB_PORT", "3306")),
        "user": os.environ.get("SMARTQMS_ML_DB_USER", "root"),
        "password": os.environ.get("SMARTQMS_ML_DB_PASSWORD", ""),
        "database": os.environ.get("SMARTQMS_ML_DB_NAME", "smartqms"),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": False,
    }


def open_database_connection():
    try:
        return pymysql.connect(**database_config())
    except (pymysql.MySQLError, ValueError) as error:
        raise DatasetValidationError(
            "Could not connect to the SmartQMS database. Check SMARTQMS_ML_DB_* settings."
        ) from error


def build_model_frame(source: pd.DataFrame) -> pd.DataFrame:
    """Map the supplied historical queue fields onto the prediction contract."""
    missing = [column for column in SOURCE_COLS if column not in source.columns]
    if missing:
        raise DatasetValidationError(
            "queue_data.csv is missing required columns: " + ", ".join(missing)
        )

    arrival = pd.to_datetime(
        source["arrival_time"],
        format="%d-%m-%Y %H.%M",
        errors="coerce",
    )
    start = pd.to_datetime(
        source["start_time"],
        format="%d-%m-%Y %H.%M",
        errors="coerce",
    )
    finish = pd.to_datetime(source["finish_time"], errors="coerce")
    return pd.DataFrame(
        {
            "queue_length": pd.to_numeric(source["queue_length"], errors="coerce"),
            "hour_of_day": arrival.dt.hour,
            "day_of_week": arrival.dt.dayofweek,
            # These dimensions were not recorded in the historical CSV.
            # Neutral constants preserve the inference feature contract without
            # inventing service, client, or window categories.
            "service_type_encoded": 1,
            "client_type_encoded": 0,
            "active_windows": 1,
            "avg_service_time": (finish - start).dt.total_seconds() / 60,
            "actual_wait_minutes": pd.to_numeric(source["wait_time"], errors="coerce"),
        }
    )


def validate_dataset(frame: pd.DataFrame, minimum_rows: int = DEFAULT_MINIMUM_ROWS) -> pd.DataFrame:
    missing = [column for column in REQUIRED_COLS if column not in frame.columns]
    if missing:
        raise DatasetValidationError(
            "Dataset is missing required columns: " + ", ".join(missing)
        )

    clean = frame.loc[:, REQUIRED_COLS].copy()
    for column in REQUIRED_COLS:
        clean[column] = pd.to_numeric(clean[column], errors="coerce")

    values = clean.to_numpy(dtype=float)
    valid_rows = np.isfinite(values).all(axis=1)
    for column, (minimum, maximum) in RANGES.items():
        valid_rows &= clean[column].between(
            minimum,
            maximum,
            inclusive="both",
        ).to_numpy()
    clean = clean.loc[valid_rows].reset_index(drop=True)

    if len(clean) < minimum_rows:
        raise DatasetValidationError(
            f"queue_data.csv has {len(clean)} usable rows; "
            f"at least {minimum_rows} are required."
        )
    return clean


def load_dataset(
    minimum_rows: int | None = None,
    dataset_path: Path | str = DATASET_PATH,
) -> pd.DataFrame:
    """Load, derive, and validate the requested historical queue dataset."""
    required_rows = minimum_training_rows() if minimum_rows is None else minimum_rows
    path = Path(dataset_path)
    if not path.is_file():
        raise DatasetValidationError(f"Training dataset is missing: {path}")
    try:
        source = pd.read_csv(path)
    except (OSError, ValueError, pd.errors.ParserError) as error:
        raise DatasetValidationError("queue_data.csv could not be read.") from error
    return validate_dataset(build_model_frame(source), minimum_rows=required_rows)
