<?php
/**
 * JSON endpoint adapter for report builders.
 */

function outputReportJson(mysqli $conn, string $defaultKey): void {
    requireLogin(ROLE_ADMIN);
    header('Content-Type: application/json');

    try {
        $range = reportDateRange($_GET);
        $key = normalizeReportKey((string) ($_GET['report'] ?? $defaultKey));
        jsonResponse(true, ['data' => buildReport($conn, $key, $range)]);
    } catch (InvalidArgumentException $e) {
        jsonResponse(false, [
            'error' => $e->getMessage(),
            'field_errors' => ['report' => $e->getMessage()],
        ], 422);
    } catch (Throwable $e) {
        jsonResponse(false, ['error' => 'Could not build report.'], 500);
    }
}
