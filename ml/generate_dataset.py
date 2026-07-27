"""Retired compatibility command.

SmartQMS no longer generates synthetic CSV training data. The canonical
historical source is the existing ml/dataset/queue_data.csv file.
"""

raise SystemExit(
    "Synthetic dataset generation is disabled. "
    "Run train_model.py to train from ml/dataset/queue_data.csv."
)
