# SmartQMS ML service

SmartQMS trains from real completed queue history exported to
`ml/dataset/queue_data.csv`. Generate that file from the application database
before training:

```powershell
php scripts/export_ml_history.php
```

The exporter writes only completed, checked-in ticket observations and derives
actual wait from `checked_in_at` to `started_at`. Historical completed rows may
fall back from `checked_in_at` to `issued_at` and from `started_at` to
`served_at`. Scheduled tickets, tickets voided before service, and rows with
missing, non-finite, negative, or out-of-range timing data are excluded. The
separate `synthetic_queue_data.csv` file and the former five-column sample CSV
are not read by any supported command.

The current export includes queue length, hour, weekday, service encoding,
client classification, active-window count, rolling service time, and actual
wait. The loader requires the database-export feature contract and refuses CSV
files that lack the recorded service, client, counter, and timing dimensions.

## Database configuration

The training commands use these optional environment variables to record
verified comparison metrics:

- `SMARTQMS_ML_DB_HOST` (default `127.0.0.1`)
- `SMARTQMS_ML_DB_PORT` (default `3306`)
- `SMARTQMS_ML_DB_NAME` (default `smartqms`)
- `SMARTQMS_ML_DB_USER` (default `root`)
- `SMARTQMS_ML_DB_PASSWORD` (default empty)
- `SMARTQMS_ML_MIN_ROWS` (default `30`, minimum `10`)

Training refuses unusable CSV rows and stops without replacing the current
model when too few valid observations remain.

## Train and serve

```powershell
python ml/train_model.py
python ml/app.py
uvicorn main:app --app-dir ml --host 127.0.0.1 --port 5000
```

Use either the Flask compatibility server (`app.py`) or the FastAPI gateway
(`main.py`), not both on the same port. `train_model.py` uses a chronological
holdout, compares four regressors, writes
a versioned model artifact plus verification metadata atomically, and records
the `queue_data.csv` evaluation metrics in `ml_comparison_logs`. `app.py`
rejects legacy or unverifiable artifacts, so the PHP queue fallback remains
active until a verified model is available.

The inference service returns `estimated_wait_minutes`, `confidence`, and
`model_version` (plus the legacy `predicted_wait_minutes` alias). Set the same
server-only `PYTHON_ML_TOKEN` for PHP and Flask to require bearer-token
authentication; the token is never projected into browser configuration.
