<?php
/**
 * Characterization inventory of existing public request contracts.
 * It is descriptive: tests verify that every listed entry point still exists.
 */

$contract = static function (
    string $path,
    string $method,
    string $role,
    array $requestFields,
    array $success,
    array $error,
    array $sessionKeys = []
): array {
    return [
        'path' => $path,
        'method' => $method,
        'role' => $role,
        'request_fields' => $requestFields,
        'success' => $success,
        'error' => $error,
        'session_keys' => $sessionKeys,
    ];
};

return [
    $contract('index.php', 'GET', 'guest', [], ['html', 'role_redirect'], ['form_feedback'], ['user_id', 'role', 'form_feedback']),
    $contract('modules/auth/login.php', 'POST', 'guest', ['csrf_token', 'login_id', 'password'], ['role_redirect', 'OTP_redirect'], ['form_feedback'], ['pending_user_id', 'pending_login_user_id', 'otp_flow', 'otp_user_id', 'otp_last_sent_at']),
    $contract('modules/auth/register.php', 'POST', 'guest', ['csrf_token', 'first_name', 'last_name', 'email', 'password'], ['verify_otp_redirect'], ['form_feedback'], ['pending_user_id', 'otp_flow', 'otp_user_id', 'otp_last_sent_at']),
    $contract('modules/auth/verify_otp.php', 'POST', 'guest', ['csrf_token', 'otp_code'], ['role_redirect'], ['form_feedback'], ['otp_flow', 'otp_user_id', 'user_id', 'role']),
    $contract('modules/auth/resend_otp.php', 'POST', 'guest', ['csrf_token'], ['OTP_redirect'], ['form_feedback'], ['otp_flow', 'otp_user_id', 'otp_last_sent_at']),
    $contract('modules/auth/forgot_password.php', 'POST', 'guest', ['csrf_token', 'action', 'email', 'otp_code', 'password', 'confirm_password'], ['reset_flow_redirect'], ['form_feedback'], ['reset_user_id', 'reset_verified_user_id', 'otp_flow', 'otp_user_id']),
    $contract('modules/auth/logout.php', 'POST', 'authenticated', ['csrf_token'], ['index_redirect'], ['index_redirect'], ['user_id', 'role', 'csrf_token']),
    $contract('modules/queue/join_queue.php', 'POST', 'client', ['csrf_token', 'service_id', 'client_type'], ['ticket_redirect'], ['form_feedback'], ['user_id', 'role']),
    $contract('modules/queue/ticket.php', 'GET', 'client', [], ['success', 'data'], ['success', 'error'], ['user_id', 'role']),
    $contract('modules/queue/status.php', 'GET', 'authenticated_or_display_token', ['token'], ['success', 'data.windows', 'data.next', 'data.viewer_ticket'], ['success', 'error'], ['user_id', 'role']),
    $contract('modules/queue/get_prediction.php', 'GET_OR_POST', 'authenticated', ['ticket_id', 'service_id', 'client_type', 'prediction_features'], ['success', 'predicted_wait_minutes', 'source'], ['success', 'message'], ['user_id', 'role']),
    $contract('modules/queue/void_checker.php', 'POST', 'staff', ['csrf_token'], ['success', 'voided', 'data'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/notifications/get_notifications.php', 'POST', 'client', ['csrf_token'], ['success', 'data', 'unread_count'], ['success', 'error'], ['user_id', 'role']),
    $contract('modules/notifications/mark_read.php', 'POST', 'client', ['csrf_token', 'notification_ids[]'], ['success', 'marked', 'unread_count'], ['success', 'error'], ['user_id', 'role']),
    $contract('modules/notifications/dispatch_email_jobs.php', 'POST', 'OTP_session', ['csrf_token'], ['success', 'processed', 'sent', 'failed', 'skipped'], ['success', 'message'], ['otp_user_id', 'pending_user_id', 'pending_login_user_id', 'reset_user_id']),
    $contract('modules/feedback/submit_feedback.php', 'POST', 'client', ['csrf_token', 'ticket_id', 'rating', 'comment'], ['success'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/service_window/call_next.php', 'POST', 'staff', ['csrf_token'], ['success', 'data'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/service_window/complete_ticket.php', 'POST', 'staff', ['csrf_token', 'ticket_id'], ['success', 'data'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/service_window/skip_ticket.php', 'POST', 'staff', ['csrf_token', 'ticket_id'], ['success'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/service_window/void_ticket.php', 'POST', 'staff', ['csrf_token', 'ticket_id'], ['success', 'data'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/service_window/window_status.php', 'POST', 'staff', ['csrf_token', 'status'], ['success', 'data'], ['success', 'error'], ['user_id', 'role', 'staff_id']),
    $contract('modules/settings/health_services_mgmt.php', 'GET_OR_POST', 'admin', ['csrf_token', 'action', 'service_id', 'service_code', 'service_name', 'service_encoded', 'description', 'priority_only', 'display_order', 'is_active'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/queue_summary.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/predicted_vs_actual.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/peak_hour.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/counter_performance.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/turnaround_time.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/no_show.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/staff_productivity.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/ml_accuracy.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/daily_monthly_stats.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/satisfaction.php', 'GET', 'admin', ['from', 'to', 'report'], ['success', 'data'], ['success', 'error', 'field_errors'], ['user_id', 'role']),
    $contract('modules/reports/export_csv.php', 'GET', 'admin', ['from', 'to', 'report'], ['text/csv', 'Content-Disposition'], ['text/plain'], ['user_id', 'role']),
    $contract('views/client/ticket_lookup.php', 'GET', 'public', ['ref'], ['html'], ['ticket_not_found_html']),
    $contract('display.php', 'GET', 'display_token', ['token'], ['html'], ['403_html']),
];
