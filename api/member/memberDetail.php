<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = trim($_POST['accountNo'] ?? '');
    if ($accountNo === '') {
        jsonResponse(RES_API_RESPONSE_ERROR, ['error' => 'accountNo required'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT
            A.APPLY_ID,
            A.ACCOUNT_NO,
            A.NAME,
            A.PHONE,
            A.ADDRESS,
            A.REFERRER_ACCOUNT_NO,
            RA.NAME AS REFERRER_NAME,
            A.STATUS,
            A.CREATED_AT
        FROM MEMBER_APPLY A
        LEFT JOIN (
            SELECT t.ACCOUNT_NO, t.NAME
            FROM MEMBER_APPLY t
            INNER JOIN (
                SELECT ACCOUNT_NO, MAX(APPLY_ID) AS MAX_APPLY_ID
                FROM MEMBER_APPLY
                WHERE STATUS = 'APPROVED'
                GROUP BY ACCOUNT_NO
            ) mx
              ON t.ACCOUNT_NO = mx.ACCOUNT_NO
             AND t.APPLY_ID  = mx.MAX_APPLY_ID
        ) RA
          ON RA.ACCOUNT_NO = A.REFERRER_ACCOUNT_NO
        WHERE A.STATUS = 'APPROVED'
          AND A.ACCOUNT_NO = ?
        ORDER BY A.APPLY_ID DESC
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(RES_API_RESPONSE_ERROR, ['error' => 'not found'], 404);
    }

    jsonResponse(RES_SUCCESS, $row, 1);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
