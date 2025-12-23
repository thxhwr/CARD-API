<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = trim($_POST['accountNo'] ?? '');
    $typeCode  = trim($_POST['typeCode'] ?? '');
    $page      = max(1, (int)($_POST['page'] ?? 1));
    $limit     = min(100, max(10, (int)($_POST['limit'] ?? 20)));
    $offset    = ($page - 1) * $limit;

    if ($accountNo === '' || $typeCode === '') {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (!in_array($typeCode, ['SP', 'LP', 'TP'], true)) {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    // 1. USER_ID 조회
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

    // 2. 로그 조회
    $stmt = $pdo->prepare("
        SELECT
            POINT_LOG_ID,
            ACTION_TYPE,
            AMOUNT,
            DESCRIPTION,
            REF_ID,
            CREATED_AT
        FROM POINT_LOG
        WHERE USER_ID = ?
          AND TYPE_CODE = ?
        ORDER BY POINT_LOG_ID DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute([$userId, $typeCode]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 총 건수
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM POINT_LOG
        WHERE USER_ID = ?
          AND TYPE_CODE = ?
    ");
    $stmt->execute([$userId, $typeCode]);
    $total = (int)$stmt->fetchColumn();

    jsonResponse(RES_SUCCESS, $logs, $total);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}

?>
