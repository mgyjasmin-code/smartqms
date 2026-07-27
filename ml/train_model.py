"""Train and deploy SmartQMS using ml/dataset/queue_data.csv."""

from __future__ import annotations

from datetime import date
import sys

from data_pipeline import (
    DatasetValidationError,
    TRAINING_SOURCE,
    load_dataset,
    open_database_connection,
)
from training import build_artifact, save_artifact_atomic, train_and_compare


def record_comparison_results(comparisons: list[dict], best_name: str, sample_size: int) -> None:
    connection = open_database_connection()
    try:
        with connection.cursor() as cursor:
            today = date.today()
            cursor.execute(
                "DELETE FROM ml_comparison_logs WHERE run_date = %s AND dataset_used = %s",
                (today, TRAINING_SOURCE),
            )
            for row in comparisons:
                cursor.execute(
                    """
                    INSERT INTO ml_comparison_logs
                      (run_date, algorithm, mae, rmse, r2, mape, is_best,
                       dataset_used, sample_size)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """,
                    (
                        today,
                        row["algorithm"],
                        row["mae"],
                        row["rmse"],
                        row["r2"],
                        row["mape"],
                        1 if row["algorithm"] == best_name else 0,
                        TRAINING_SOURCE,
                        sample_size,
                    ),
                )
            cursor.execute(
                """
                INSERT INTO system_settings
                    (setting_key, setting_val, label, section)
                VALUES ('ml_last_trained', %s, 'Model Last Trained', 'ml')
                ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)
                """,
                (today.isoformat(),),
            )
            cursor.execute(
                """
                INSERT INTO system_settings
                    (setting_key, setting_val, label, section)
                VALUES ('ml_dataset_used', %s, 'Training Dataset Used', 'ml')
                ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)
                """,
                (TRAINING_SOURCE,),
            )
        connection.commit()
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


def main() -> int:
    print("=" * 64)
    print("SmartQMS queue_data.csv wait-time model training")
    print("=" * 64)
    try:
        frame = load_dataset()
        model, comparisons, best, train_count, test_count = train_and_compare(frame)
        artifact = build_artifact(
            model,
            best,
            row_count=len(frame),
            train_count=train_count,
            test_count=test_count,
        )
        save_artifact_atomic(artifact)
        record_comparison_results(comparisons, best["algorithm"], len(frame))
    except (DatasetValidationError, ValueError) as error:
        print(f"[STOPPED] {error}", file=sys.stderr)
        return 2
    except Exception as error:
        print(f"[FAILED] Training did not replace the current model: {error}", file=sys.stderr)
        return 1

    for row in comparisons:
        mape = "n/a" if row["mape"] is None else f"{row['mape']:.2f}%"
        r2 = "n/a" if row["r2"] is None else f"{row['r2']:.4f}"
        print(
            f"{row['algorithm']:<22} "
            f"MAE={row['mae']:.4f} RMSE={row['rmse']:.4f} R2={r2} MAPE={mape}"
        )
    print(
        f"[OK] Deployed {best['algorithm']} using {len(frame)} validated queue observations "
        f"({train_count} train / {test_count} chronological holdout)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
