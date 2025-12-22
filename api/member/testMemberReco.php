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
            m1.USER_ID AS level1_user_id,
            m1.ACCOUNT_NO AS level1_account_no,
            m1.NAME AS level1_name,

            m2.USER_ID AS level2_user_id,
            m2.ACCOUNT_NO AS level2_account_no,
            m2.NAME AS level2_name,

            m3.USER_ID AS level3_user_id,
            m3.ACCOUNT_NO AS level3_account_no,
            m3.NAME AS level3_name
        FROM MEMBER me
        LEFT JOIN MEMBER m1 ON m1.USER_ID = me.REFERRER_USER_ID
        LEFT JOIN MEMBER m2 ON m2.USER_ID = m1.REFERRER_USER_ID
        LEFT JOIN MEMBER m3 ON m3.USER_ID = m2.REFERRER_USER_ID
        WHERE me.USER_ID = ?
        LIMIT 1
    ");

    $stmt->execute([$loginUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $result = [
        'level1' => null,
        'level2' => null,
        'level3' => null
    ];

    if ($row) {
        if (!empty($row['level1_user_id'])) {
            $result['level1'] = [
                'userId'    => (int)$row['level1_user_id'],
                'accountNo' => $row['level1_account_no'],
                'name'      => $row['level1_name']
            ];
        }

        if (!empty($row['level2_user_id'])) {
            $result['level2'] = [
                'userId'    => (int)$row['level2_user_id'],
                'accountNo' => $row['level2_account_no'],
                'name'      => $row['level2_name']
            ];
        }

        if (!empty($row['level3_user_id'])) {
            $result['level3'] = [
                'userId'    => (int)$row['level3_user_id'],
                'accountNo' => $row['level3_account_no'],
                'name'      => $row['level3_name']
            ];
        }
    }

    jsonResponse(RES_SUCCESS, $result);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
?>