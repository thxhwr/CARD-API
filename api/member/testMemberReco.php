<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = strtolower(trim($_POST['accountNo'] ?? ''));

    if ($accountNo === '') {
        jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT USER_ID
        FROM MEMBER
        WHERE ACCOUNT_NO = ?
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $loginUserId = $stmt->fetchColumn();

    if (!$loginUserId) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

    $stmt = $pdo->prepare("
        SELECT
            ma.REFERRER_ACCOUNT_NO AS referrerAccountNo,
            m.NAME AS referrerName,
            m.USER_ID AS referrerUserId
        FROM MEMBER_APPLY ma
        LEFT JOIN MEMBER m
            ON m.ACCOUNT_NO = ma.REFERRER_ACCOUNT_NO
        WHERE ma.ACCOUNT_NO = ?
        ORDER BY ma.APPLY_ID DESC
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $refRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $referrer = null;
    if ($refRow && !empty($refRow['referrerAccountNo'])) {
        $referrer = [
            'accountNo' => $refRow['referrerAccountNo'],               
            'name'      => $refRow['referrerName'] ?? null,           
            'userId'    => $refRow['referrerUserId'] ? (int)$refRow['referrerUserId'] : null,
        ];
    }

  
    $stmt = $pdo->prepare("
        SELECT
            USER_ID,
            ACCOUNT_NO,
            NAME,
            DEPT,
            DEPT_NO,
            CREATED_AT
        FROM MEMBER
        WHERE REFERRER_USER_ID = ?
        ORDER BY CREATED_AT ASC
    ");
    $stmt->execute([$loginUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $referrals = [];
    foreach ($rows as $row) {
        $referrals[] = [
            'userId'    => (int)$row['USER_ID'],
            'accountNo' => $row['ACCOUNT_NO'],
            'name'      => $row['NAME'],
            'dept'      => (int)$row['DEPT'],
            'deptNo'    => (int)$row['DEPT_NO'],
            'createdAt' => $row['CREATED_AT'],
        ];
    }

    jsonResponse(RES_SUCCESS, [
        'referrer' => $referrer,
        'count' => count($referrals),
        'list'  => $referrals,
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
