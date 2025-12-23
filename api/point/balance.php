<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = trim($_POST['accountNo'] ?? '');

    if ($accountNo === '') {
        jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
    }

    // 1. 회원 조회
    $stmt = $pdo->prepare("
        SELECT USER_ID
        FROM MEMBER
        WHERE ACCOUNT_NO = ?
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

    // 2. 포인트 잔액 계산
    $stmt = $pdo->prepare("
        SELECT
            TYPE_CODE,
            SUM(
                CASE
                    WHEN ACTION_TYPE = 'IN' THEN AMOUNT
                    WHEN ACTION_TYPE = 'OUT' THEN -AMOUNT
                END
            ) AS BALANCE
        FROM POINT_LOG
        WHERE USER_ID = ?
        GROUP BY TYPE_CODE
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. 없는 타입은 0으로 보정
    $result = [
        'SP' => (int)($rows['SP'] ?? 0),
        'LP' => (int)($rows['LP'] ?? 0),
        'TP' => (int)($rows['TP'] ?? 0),
    ];

    jsonResponse(RES_SUCCESS, $result);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}

?>