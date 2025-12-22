<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    // $accountNo = strtolower(trim($_POST['accountNo'] ?? ''));
    $accountNo = 'kni1993@naver.com';
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
            'createdAt'=> $row['CREATED_AT']
        ];
    }

    jsonResponse(RES_SUCCESS, [
        'count' => count($referrals),
        'list'  => $referrals
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
