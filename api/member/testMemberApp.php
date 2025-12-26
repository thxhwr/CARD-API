<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

$referrerUserId = null;

$referrerAccountNo = trim($_POST['referrerAccountNo'] ?? '');
$userId = trim($_POST['userId'] ?? '');
$accountNo = trim($_POST['accountNo'] ?? '');
$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

// $accountNo = 'test' . time() . '@test.com';

if ($userId === '') {
    jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
}

if ($accountNo === '') {
    jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
}

$accountNo = strtolower($accountNo);

if (!filter_var($accountNo, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(RES_INVALID_EMAIL, [], 400);
}

$stmt = $pdo->prepare("
    SELECT 1
    FROM MEMBER
    WHERE ACCOUNT_NO = ?
    LIMIT 1
");
$stmt->execute([$accountNo]);

if ($stmt->fetchColumn()) {
    jsonResponse(RES_ACCOUNT_DUPLICATED, [], 409);
}

if ($referrerAccountNo === '') {
    jsonResponse(RES_REFERRER_REQUIRED, [], 400);
}

if (!filter_var($referrerAccountNo, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(RES_INVALID_REFERRER, [], 400);
}

try {
    if ($accountNo === '' || $name === '' || $phone === '' || $address === '') {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM MEMBER_APPLY
        WHERE ACCOUNT_NO = ?
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    if ($stmt->fetchColumn()) {
        jsonResponse(RES_ACCOUNT_DUPLICATED, [], 409);
    }

    // 3. 중복 신청 방지 (전화번호)
    $stmt = $pdo->prepare("
        SELECT 1
        FROM MEMBER_APPLY
        WHERE PHONE = ?
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn()) {
        jsonResponse(RES_INVALID_PHONE, [], 409);
    }

    // 4. 신청 INSERT
    $stmt = $pdo->prepare("
        INSERT INTO MEMBER_APPLY (
            REFERRER_ACCOUNT_NO,
            ACCOUNT_NO,
            NAME,
            PHONE,
            ADDRESS,
            STATUS,
            CREATED_AT
        ) VALUES (
            :referrer_account_no,
            :account_no,
            :name,
            :phone,
            :address,
            'PENDING',
            NOW()
        )
    ");

    $stmt->execute([
        ':referrer_account_no' => $referrerAccountNo ?: null,
        ':account_no'          => $accountNo,
        ':name'                => $name,
        ':phone'               => $phone,
        ':address'             => $address,
    ]);

    jsonResponse(RES_SUCCESS, [
        'applyId' => (int)$pdo->lastInsertId()
    ]);
} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
?>