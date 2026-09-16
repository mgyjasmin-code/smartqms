"""Contract tests for the SmartQMS dataset validator and Flask API."""

import unittest

import numpy as np
import pandas as pd

import app as app_module
from data_pipeline import DatasetValidationError, REQUIRED_COLS, validate_dataset


VALID_PAYLOAD = {
    'queue_length': 5,
    'hour_of_day': 9,
    'day_of_week': 1,
    'service_type_encoded': 2,
    'client_type_encoded': 0,
    'active_windows': 3,
    'avg_service_time': 4.5,
}


class FixedModel:
    def __init__(self, prediction=8.25):
        self.prediction = prediction

    def predict(self, _features):
        return np.array([self.prediction])


class DatasetTests(unittest.TestCase):
    def test_valid_dataset(self):
        rows = [{column: 1 for column in REQUIRED_COLS} for _ in range(100)]
        frame = validate_dataset(pd.DataFrame(rows))
        self.assertEqual(len(frame), 100)

    def test_missing_columns_are_rejected(self):
        with self.assertRaises(DatasetValidationError):
            validate_dataset(pd.DataFrame({'queue_length': [1] * 100}))

    def test_out_of_range_values_are_rejected(self):
        rows = [{column: 1 for column in REQUIRED_COLS} for _ in range(100)]
        rows[0]['hour_of_day'] = 25
        with self.assertRaises(DatasetValidationError):
            validate_dataset(pd.DataFrame(rows))


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.previous_model = app_module.MODEL
        self.previous_metadata = app_module.MODEL_METADATA
        app_module.MODEL = FixedModel()
        app_module.MODEL_METADATA = {
            'algorithm': 'Test model',
            'model_version': 'qms-wait-test',
            'metrics': {'mae': 1.0},
        }
        app_module.app.config.update(TESTING=True)
        self.client = app_module.app.test_client()

    def tearDown(self):
        app_module.MODEL = self.previous_model
        app_module.MODEL_METADATA = self.previous_metadata

    def test_health_contract(self):
        response = self.client.get('/health')
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.get_json()['ready'])

    def test_valid_prediction(self):
        response = self.client.post('/predict', json=VALID_PAYLOAD)
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.get_json()['predicted_wait_minutes'], 8.25)
        self.assertEqual(response.get_json()['estimated_wait_minutes'], 8.25)
        self.assertEqual(response.get_json()['model_version'], 'qms-wait-test')
        self.assertGreater(response.get_json()['confidence'], 0)

    def test_model_missing(self):
        app_module.MODEL = None
        response = self.client.post('/predict', json=VALID_PAYLOAD)
        self.assertEqual(response.status_code, 503)

    def test_missing_field(self):
        payload = dict(VALID_PAYLOAD)
        payload.pop('active_windows')
        response = self.client.post('/predict', json=payload)
        self.assertEqual(response.status_code, 400)

    def test_wrong_type(self):
        payload = dict(VALID_PAYLOAD, queue_length='many')
        response = self.client.post('/predict', json=payload)
        self.assertEqual(response.status_code, 400)

    def test_out_of_range(self):
        payload = dict(VALID_PAYLOAD, hour_of_day=27)
        response = self.client.post('/predict', json=payload)
        self.assertEqual(response.status_code, 400)

    def test_malformed_json(self):
        response = self.client.post('/predict', data='{', content_type='application/json')
        self.assertEqual(response.status_code, 400)

    def test_invalid_model_output(self):
        app_module.MODEL = FixedModel(float('nan'))
        response = self.client.post('/predict', json=VALID_PAYLOAD)
        self.assertEqual(response.status_code, 500)


if __name__ == '__main__':
    unittest.main()
