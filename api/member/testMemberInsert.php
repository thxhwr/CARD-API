<?php
require_once __DIR__ . '/../../config/bootstrap.php';

$referrerUserId = null;

$referrerAccountNo = trim($_POST['referrerAccountNo'] ?? '');
$accountNo = trim($_POST['accountNo'] ?? '');
$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

// $accountNo = 'test' . time() . '@test.com';

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

// 2. 추천인 형식 검사
if (!filter_var($referrerAccountNo, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(RES_INVALID_REFERRER, [], 400);
}

// 3. 추천인 존재 검사
$stmt = $pdo->prepare("
    SELECT USER_ID
    FROM MEMBER
    WHERE ACCOUNT_NO = ?
    LIMIT 1
");
$stmt->execute([$referrerAccountNo]);

$referrerUserId = $stmt->fetchColumn();

if (!$referrerUserId) {
    jsonResponse(RES_REFERRER_NOT_FOUND, [], 404);
}

try {

    if ($name === '' || $phone === '' || $address === '') {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (!isValidName($name)) {
        jsonResponse(RES_INVALID_NAME, [], 400);
    }

    if (!isValidPhone($phone)) {
        jsonResponse(RES_INVALID_PHONE, [], 400);
    }

    if (!isValidAddress($address)) {
        jsonResponse(RES_INVALID_ADDRESS, [], 400);
    }

    $pdo->beginTransaction();

    $pos = assignDeptAndParent($pdo);

    $userId = (int)$pdo->query("SELECT IFNULL(MAX(USER_ID), 0) + 1 FROM MEMBER")
                       ->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO MEMBER (
            USER_ID,
            REFERRER_USER_ID,
            ACCOUNT_NO,
            DEPT,
            DEPT_NO,
            PARENT_USER_ID,
            NAME,
            PHONE,
            ADDRESS
        ) VALUES (
            :user_id,
            :referrer_user_id,
            :account_no,
            :dept,
            :dept_no,
            :parent_user_id,
            :name,
            :phone,
            :address
        )
    ");

    $stmt->execute([
        ':user_id'           => $userId,
        ':referrer_user_id'  => $referrerUserId,
        ':account_no'        => $accountNo,
        ':dept'              => $pos['dept'],
        ':dept_no'           => $pos['dept_no'],
        ':parent_user_id'    => $pos['parent_user_id'],
        ':name'              => $name,
        ':phone'             => $phone,
        ':address'           => $address,
    ]);

    $pdo->commit();

    echo "USER_ID : {$userId}\n";
    echo "referrerUserId : {$referrerUserId}\n";
    echo "ACCOUNT : {$accountNo}\n";
    echo "DEPT    : {$pos['dept']}\n";
    echo "DEPT_NO : {$pos['dept_no']}\n";
    echo "PARENT  : {$pos['parent_user_id']}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        jsonResponse(RES_DUPLICATE_ACCOUNT, [], 409);
    }
    throw $e;
}
?>