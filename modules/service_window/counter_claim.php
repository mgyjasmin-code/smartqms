<?php
/**
 * Transaction-safe staff ownership of physical service counters.
 */

function staffEligibleCounters(mysqli $conn, int $staffId): array {
    if (!smartqmsTableExists($conn, 'counter_services')) {
        $stmt = $conn->prepare("
            SELECT sw.*, hs.service_name AS mapped_services
            FROM service_windows sw
            LEFT JOIN health_services hs ON hs.service_id = sw.service_id
            WHERE sw.is_active = 1 AND (sw.staff_id IS NULL OR sw.staff_id = ?)
            ORDER BY sw.window_name
        ");
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $counterOrder = smartqmsTableHasColumn($conn, 'service_windows', 'counter_number')
        ? 'sw.counter_number, sw.window_name'
        : 'sw.window_name';
    $stmt = $conn->prepare("
        SELECT sw.*,
               GROUP_CONCAT(DISTINCT hs.service_name ORDER BY hs.display_order SEPARATOR ', ') AS mapped_services
        FROM service_windows sw
        LEFT JOIN counter_services cs ON cs.counter_id = sw.window_id
        LEFT JOIN health_services hs ON hs.service_id = cs.service_id AND hs.is_active = 1
        WHERE sw.is_active = 1
          AND (sw.staff_id IS NULL OR sw.staff_id = ?)
          AND (
            sw.window_type = 'shared'
            OR EXISTS (
              SELECT 1
              FROM staff_service_capabilities capability
              WHERE capability.staff_id = ?
                AND capability.service_id = sw.service_id
                AND capability.is_active = 1
            )
          )
        GROUP BY sw.window_id
        ORDER BY {$counterOrder}
    ");
    $stmt->bind_param('ii', $staffId, $staffId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function claimCounterForStaff(mysqli $conn, int $staffId, int $counterId): array {
    $conn->begin_transaction();
    try {
        $current = getStaffWindowForUpdate($conn, $staffId);
        if ($current && (int) $current['window_id'] === $counterId) {
            $conn->commit();
            return ['status' => 'success', 'window_id' => $counterId, 'already_owned' => true];
        }
        if ($current && getCurrentServingTicketForWindow($conn, (int) $current['window_id'], true)) {
            $conn->rollback();
            return ['status' => 'active_ticket'];
        }

        $target = $conn->prepare('SELECT * FROM service_windows WHERE window_id = ? AND is_active = 1 LIMIT 1 FOR UPDATE');
        $target->bind_param('i', $counterId);
        $target->execute();
        $window = $target->get_result()->fetch_assoc();
        if (!$window) {
            $conn->rollback();
            return ['status' => 'not_found'];
        }
        if ($window['staff_id'] !== null && (int) $window['staff_id'] !== $staffId) {
            $conn->rollback();
            return ['status' => 'unavailable'];
        }
        if (($window['window_type'] ?? 'shared') === 'specialized') {
            $serviceId = (int) ($window['service_id'] ?? 0);
            $capability = $conn->prepare("
                SELECT 1 FROM staff_service_capabilities
                WHERE staff_id = ? AND service_id = ? AND is_active = 1
                LIMIT 1
            ");
            $capability->bind_param('ii', $staffId, $serviceId);
            $capability->execute();
            if (!$capability->get_result()->fetch_row()) {
                $conn->rollback();
                return ['status' => 'not_eligible'];
            }
        }

        if ($current) {
            $releaseId = (int) $current['window_id'];
            $release = $conn->prepare("UPDATE service_windows SET staff_id = NULL, status = 'closed' WHERE window_id = ? AND staff_id = ?");
            $release->bind_param('ii', $releaseId, $staffId);
            $release->execute();
        }

        $claim = $conn->prepare("
            UPDATE service_windows
            SET staff_id = ?, status = 'open'
            WHERE window_id = ? AND (staff_id IS NULL OR staff_id = ?)
        ");
        $claim->bind_param('iii', $staffId, $counterId, $staffId);
        $claim->execute();
        if ($claim->affected_rows !== 1) {
            $conn->rollback();
            return ['status' => 'unavailable'];
        }

        logActivity($conn, 'counter_claimed', 'Claimed ' . $window['window_name']);
        $conn->commit();
        return ['status' => 'success', 'window_id' => $counterId, 'already_owned' => false];
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function releaseCounterForStaff(mysqli $conn, int $staffId): array {
    $conn->begin_transaction();
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            $conn->commit();
            return ['status' => 'success', 'released' => false];
        }
        $windowId = (int) $window['window_id'];
        if (getCurrentServingTicketForWindow($conn, $windowId, true)) {
            $conn->rollback();
            return ['status' => 'active_ticket'];
        }
        $update = $conn->prepare("UPDATE service_windows SET staff_id = NULL, status = 'closed' WHERE window_id = ? AND staff_id = ?");
        $update->bind_param('ii', $windowId, $staffId);
        $update->execute();
        logActivity($conn, 'counter_released', 'Released ' . $window['window_name']);
        $conn->commit();
        return ['status' => 'success', 'released' => true];
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}
