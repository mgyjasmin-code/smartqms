# SmartQMS ML service

SmartQMS trains from the requested historical queue dataset at
`ml/dataset/queue_data.csv`. The dataset contains arrival, service-start,
service-finish, wait-time, and queue-length observations. Rows with missing,
non-finite, negative, or out-of-range timing data are excluded. The separate
`synthetic_queue_data.csv` file is not read by any supported command.

The CSV does not record service type, client classification, or active-window
count. The pipeline therefore uses neutral constants for those dimensions and
derives hour, weekday, and service duration from the recorded timestamps. This
limitation is retained in the model metadata and must not be interpreted as
evidence of per-service or per-client accuracy.

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
```

`train_model.py` uses a chronological holdout, compares four regressors, writes
a versioned model artifact plus verification metadata atomically, and records
the `queue_data.csv` evaluation metrics in `ml_comparison_logs`. `app.py`
rejects legacy or unverifiable artifacts, so the PHP queue fallback remains
active until a verified model is available.
