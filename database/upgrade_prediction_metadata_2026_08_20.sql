-- SmartQMS wait-prediction provenance upgrade.
-- Apply once to existing MySQL installations before enabling ML metadata.
ALTER TABLE wait_time_logs
  ADD COLUMN IF NOT EXISTS prediction_confidence DECIMAL(6,5) DEFAULT NULL AFTER predicted_wait_min,
  ADD COLUMN IF NOT EXISTS model_version VARCHAR(100) DEFAULT NULL AFTER prediction_confidence;
