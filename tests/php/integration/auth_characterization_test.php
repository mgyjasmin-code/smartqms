<?php

require_once SMARTQMS_ROOT . '/modules/auth/auth_utils.php';

function prepareAuthCharacterizationSchema(): void {
    $conn = testDatabaseConnection();
    ensureAuthSecuritySchema($conn);
    ensureEmailJobsTable($conn);
}

function createCharacterizationAuthUser(
    mysqli $conn,
    string $suffix,
    string $role = ROLE_CLIENT,
    int $verified = 1,
    int $active = 1
): array {
    $email = testFixturePrefix() . $suffix . '@example.test';
    $passwordHash = password_hash('characterization-password', PASSWORD_BCRYPT);
    $firstName = 'Characterization';
    $lastName = ucfirst($suffix);
    $stmt = $conn->prepare("
        INSERT INTO users
          (first_name, last_name, email, password_hash, role, is_verified, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssii', $firstName, $lastName, $email, $passwordHash, $role, $verified, $active);
    $stmt->execute();

    return authUserById($conn, (int) $conn->insert_id);
}

testCase('authentication persistence retains lookup registration and reset behavior', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $email = testFixturePrefix() . 'registration@example.test';
        assertFalseValue(authEmailExists($conn, $email));

        $userId = createUnverifiedClient(
            $conn,
            'Characterization',
            'Registration',
            $email,
            password_hash('initial-password', PASSWORD_BCRYPT)
        );

        assertTrueValue($userId > 0);
        assertTrueValue(authEmailExists($conn, $email));
        $user = authUserByEmail($conn, $email);
        assertSameValue($userId, (int) $user['user_id']);
        assertSameValue(ROLE_CLIENT, $user['role']);
        assertSameValue(0, (int) $user['is_verified']);
        assertSameValue($userId, (int) authClientByEmail($conn, $email)['user_id']);

        verifyAuthUserEmail($conn, $userId);
        assertSameValue(1, (int) authUserById($conn, $userId)['is_verified']);

        $newHash = password_hash('changed-password', PASSWORD_BCRYPT);
        resetAuthUserPassword($conn, $userId, $newHash);
        $resetUser = authUserById($conn, $userId);
        assertTrueValue(password_verify('changed-password', $resetUser['password_hash']));
        assertSameValue(1, (int) $resetUser['is_verified']);
        assertSameValue(null, $resetUser['otp_hash']);
        assertSameValue(null, $resetUser['otp_expires_at']);
    });
});

testCase('stored OTP verification retains valid wrong expired and clear behavior', function (): void {
    prepareAuthCharacterizationSchema();
    withTestTransaction(function (mysqli $conn): void {
        $user = createCharacterizationAuthUser($conn, 'otp');
        $userId = (int) $user['user_id'];
        $hash = password_hash('123456', PASSWORD_BCRYPT);

        storeAuthOtp($conn, $userId, $hash, date('Y-m-d H:i:s', time() + 600));
        assertSameValue('valid', verifyStoredOtp($conn, $userId, '123456'));
        assertSameValue('wrong', verifyStoredOtp($conn, $userId, '654321'));

        storeAuthOtp($conn, $userId, $hash, date('Y-m-d H:i:s', time() - 1));
        assertSameValue('expired', verifyStoredOtp($conn, $userId, '123456'));

        clearAuthOtp($conn, $userId);
        assertSameValue('wrong', verifyStoredOtp($conn, $userId, '123456'));
    });
});

testCase('OTP issuance replaces pending jobs without sending email', function (): void {
    prepareAuthCharacterizationSchema();
    withTestTransaction(function (mysqli $conn): void {
        $user = createCharacterizationAuthUser($conn, 'issue_otp');
        $userId = (int) $user['user_id'];

        assertTrueValue(issueOtp($conn, $userId, 'Characterization OTP is', 'otp', true));
        $first = authOtpRecordByUserId($conn, $userId);
        assertTrueValue(!empty($first['otp_hash']));
        assertTrueValue(strtotime($first['otp_expires_at']) > time());

        assertTrueValue(issueOtp($conn, $userId, 'Replacement characterization OTP is', 'otp', true));
        $jobs = $conn->query("
            SELECT status, message
            FROM email_jobs
            WHERE user_id = {$userId} AND type = 'otp'
            ORDER BY job_id ASC
        ")->fetch_all(MYSQLI_ASSOC);

        assertSameValue(2, count($jobs));
        assertSameValue('cancelled', $jobs[0]['status']);
        assertSameValue('[redacted]', $jobs[0]['message']);
        assertSameValue('pending', $jobs[1]['status']);
    });
});

testCase('authentication throttling retains limit and clear behavior', function (): void {
    prepareAuthCharacterizationSchema();
    withTestTransaction(function (mysqli $conn): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.77';
        $scope = 'characterization_login';
        $identifier = testFixturePrefix() . 'throttle@example.test';

        assertTrueValue(authThrottleStatus($conn, $scope, $identifier, 2, 600)['allowed']);
        recordAuthAttempt($conn, $scope, $identifier, 2, 600);
        assertTrueValue(authThrottleStatus($conn, $scope, $identifier, 2, 600)['allowed']);
        recordAuthAttempt($conn, $scope, $identifier, 2, 600);
        assertFalseValue(authThrottleStatus($conn, $scope, $identifier, 2, 600)['allowed']);
        clearAuthAttempts($conn, $scope, $identifier);
        assertTrueValue(authThrottleStatus($conn, $scope, $identifier, 2, 600)['allowed']);
    });
});

testCase('login completion regenerates session and retains role session contracts', function (): void {
    prepareAuthCharacterizationSchema();
    withTestTransaction(function (mysqli $conn): void {
        $originalSession = $_SESSION;
        try {
            $_SESSION = [];
            $admin = createCharacterizationAuthUser($conn, 'admin', ROLE_ADMIN);
            $beforeSessionId = session_id();
            completeLogin($conn, $admin);

            assertTrueValue(session_id() !== $beforeSessionId);
            assertSameValue((int) $admin['user_id'], $_SESSION['user_id']);
            assertSameValue(ROLE_ADMIN, $_SESSION['role']);
            assertSameValue('Characterization Admin', $_SESSION['name']);
            assertSameValue($admin['email'], $_SESSION['email']);
            assertTrueValue(!empty(authUserById($conn, (int) $admin['user_id'])['last_login_at']));

            $_SESSION = [];
            $staff = createCharacterizationAuthUser($conn, 'staff', ROLE_STAFF);
            $staffUserId = (int) $staff['user_id'];
            $department = 'Characterization';
            $insertStaff = $conn->prepare("INSERT INTO staff (user_id, department) VALUES (?, ?)");
            $insertStaff->bind_param('is', $staffUserId, $department);
            $insertStaff->execute();
            $staffId = (int) $conn->insert_id;

            completeLogin($conn, $staff);
            assertSameValue(ROLE_STAFF, $_SESSION['role']);
            assertSameValue($staffId, $_SESSION['staff_id']);
        } finally {
            $_SESSION = $originalSession;
        }
    });
});

testCase('logout session helper destroys and permits a clean replacement session', function (): void {
    $originalSession = $_SESSION;
    $_SESSION['user_id'] = 999;
    $_SESSION['role'] = ROLE_CLIENT;

    destroyAuthenticatedSession();
    assertSameValue(PHP_SESSION_NONE, session_status());

    session_start();
    assertFalseValue(isset($_SESSION['user_id']));
    assertFalseValue(isset($_SESSION['role']));
    $_SESSION = $originalSession;
});
