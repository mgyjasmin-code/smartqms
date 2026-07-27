<?php
/**
 * Shared HTTP, redirect, request, and form-feedback helpers.
 */

function jsonResponse(bool $success, array $payload = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success], $payload));
    exit();
}

function requirePostRequest(
    bool $json = false,
    string $redirectPath = 'index.php',
    string $formKey = 'login',
    array $oldInput = [],
    string $message = 'Please submit the form again.'
): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return;
    }

    if ($json) {
        jsonResponse(false, ['error' => $message], 405);
    }

    redirectWithFormFeedback($redirectPath, $formKey, [], $oldInput, $message);
}

function redirectTo(string $path, array $params = []): void {
    $url = APP_URL . '/' . ltrim($path, '/');
    if ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    header('Location: ' . $url);
    exit();
}

function flashFormFeedback(string $formKey, array $fieldErrors = [], array $oldInput = [], string $formError = ''): void {
    $_SESSION['form_feedback'][$formKey] = [
        'field_errors' => array_filter($fieldErrors, static fn($message) => trim((string) $message) !== ''),
        'old' => array_map(static function ($value): string {
            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return '';
        }, $oldInput),
        'form_error' => trim($formError),
    ];
}

function consumeFormFeedback(string $formKey): array {
    $empty = [
        'field_errors' => [],
        'old' => [],
        'form_error' => '',
    ];

    $feedback = $_SESSION['form_feedback'][$formKey] ?? [];
    unset($_SESSION['form_feedback'][$formKey]);

    if (empty($_SESSION['form_feedback'])) {
        unset($_SESSION['form_feedback']);
    }

    return array_merge($empty, is_array($feedback) ? $feedback : []);
}

function redirectWithFormFeedback(
    string $path,
    string $formKey,
    array $fieldErrors = [],
    array $oldInput = [],
    string $formError = '',
    array $params = []
): void {
    flashFormFeedback($formKey, $fieldErrors, $oldInput, $formError);
    redirectTo($path, $params);
}

function fieldError(array $feedback, string $field): string {
    return (string) ($feedback['field_errors'][$field] ?? '');
}

function oldFormValue(array $feedback, string $field, string $default = ''): string {
    return (string) ($feedback['old'][$field] ?? $default);
}

function fieldInvalidClass(array $feedback, string $field): string {
    return fieldError($feedback, $field) !== '' ? ' is-invalid' : '';
}

function fieldAriaInvalid(array $feedback, string $field): string {
    return fieldError($feedback, $field) !== '' ? ' aria-invalid="true"' : '';
}

function requestValue(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
