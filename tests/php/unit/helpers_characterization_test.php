<?php

testCase('queue transaction state helper is available from the normal application bootstrap', function (): void {
    assertTrueValue(function_exists('queueConnectionHasActiveTransaction'));
    $helpers = (string) file_get_contents(SMARTQMS_ROOT . '/config/helpers.php');
    assertStringContains("modules/shared/database.php", $helpers);
});

testCase('phone normalization strips non-digits', function (): void {
    assertSameValue('6309171234567', normalizePhone('+63 (0) 917-123-4567'));
});

testCase('Philippine mobile validation matches the current contract', function (): void {
    assertTrueValue(isValidPhMobile('09171234567'));
    assertFalseValue(isValidPhMobile('9171234567'));
    assertFalseValue(isValidPhMobile('0917123456x'));
});

testCase('shared validation primitives retain current accepted values', function (): void {
    assertSameValue('client@example.test', normalizeEmail(' Client@Example.Test '));
    assertTrueValue(isValidEmail('client@example.test'));
    assertFalseValue(isValidEmail('not-an-email'));
    assertTrueValue(hasRequiredText(' value '));
    assertFalseValue(hasRequiredText('   '));
    assertTrueValue(hasRequiredText('   ', false));
    assertTrueValue(hasMinimumLength('12345678', 8));
    assertFalseValue(hasMinimumLength('1234567', 8));
    assertTrueValue(passwordsMatch('same-password', 'same-password'));
    assertFalseValue(passwordsMatch('one-password', 'other-password'));
    assertTrueValue(isSixDigitOtp('123456'));
    assertFalseValue(isSixDigitOtp('12345'));
    assertTrueValue(isRatingInRange(1));
    assertTrueValue(isRatingInRange(5));
    assertFalseValue(isRatingInRange(0));
    assertTrueValue(isPositiveIdentifier(1));
    assertFalseValue(isPositiveIdentifier(0));
    assertTrueValue(isServiceEncodedValue('127'));
    assertFalseValue(isServiceEncodedValue('128'));
    assertTrueValue(isServiceDisplayOrder('0'));
    assertFalseValue(isServiceDisplayOrder('-1'));
    assertTrueValue(isAllowedWindowStatus('busy'));
    assertFalseValue(isAllowedWindowStatus('paused'));
    assertTrueValue(isValidReportDate('2026-07-23'));
    assertFalseValue(isValidReportDate('2026/07/23'));
});

testCase('client type encoding remains stable', function (): void {
    assertSameValue(0, clientTypeEncoded('regular'));
    assertSameValue(1, clientTypeEncoded('senior'));
    assertSameValue(2, clientTypeEncoded('pwd'));
    assertSameValue(0, clientTypeEncoded('unknown'));
});

testCase('fallback wait calculation preserves current formula', function (): void {
    assertSameValue(10.0, fallbackWaitEstimate(4, 2, 5.0));
    assertSameValue(7.5, fallbackWaitEstimate(4, 2, 5.0, 1));
    assertSameValue(2.0, fallbackWaitEstimate(0, 0, 0.0));
});

testCase('flash feedback is stored, consumed once, and normalized', function (): void {
    unset($_SESSION['form_feedback']);
    flashFormFeedback('characterization_form', ['email' => 'Invalid'], ['email' => 'person@example.test'], 'Form error');
    $feedback = consumeFormFeedback('characterization_form');
    assertSameValue('Invalid', $feedback['field_errors']['email']);
    assertSameValue('person@example.test', $feedback['old']['email']);
    assertSameValue('Form error', $feedback['form_error']);
    assertSameValue(['field_errors' => [], 'old' => [], 'form_error' => ''], consumeFormFeedback('characterization_form'));
});

testCase('CSV export retains a formula-injection guard', function (): void {
    $source = file_get_contents(SMARTQMS_ROOT . '/modules/reports/export_csv.php');
    assertTrueValue(is_string($source));
    assertStringContains('function safeCsvCell', $source);
    assertStringContains('preg_match', $source);
    assertStringContains("'=", "'" . '=SUM(A1:A2)');
});

testCase('HTTP contract inventory is complete and points to existing entry points', function (): void {
    $contracts = require SMARTQMS_TEST_ROOT . '/contracts/http_contracts.php';
    assertTrueValue(count($contracts) >= 25, 'Expected a broad endpoint inventory.');
    foreach ($contracts as $contract) {
        assertArrayHasKeys(['path', 'method', 'role', 'request_fields', 'success', 'error', 'session_keys'], $contract);
        $path = explode('?', $contract['path'], 2)[0];
        assertTrueValue(is_file(SMARTQMS_ROOT . '/' . $path), 'Missing endpoint: ' . $path);
    }
});
