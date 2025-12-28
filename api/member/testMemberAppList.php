<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $status = trim($_POST['status'] ?? 'PENDING');
    $search = trim($_POST['search'] ?? '');
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $limit  = min(100, max(10, (int)($_POST['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]  = 'STATUS = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $where[] = "
            (
                ACCOUNT_NO LIKE ?
                OR NAME LIKE ?
                OR PHONE LIKE ?
                OR REFERRER_ACCOUNT_NO LIKE ?
            )
        ";
        $keyword = '%' . $search . '%';
        array_push($params, $keyword, $keyword, $keyword, $keyword);
    }
    
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT
            A.APPLY_ID,
            A.ACCOUNT_NO,
            A.NAME,
            A.PHONE,
            A.ADDRESS,
            A.REFERRER_ACCOUNT_NO,
            R.ACCOUNT_NO   AS REFERRER_USER_ID,
            R.NAME      AS REFERRER_NAME,
            A.STATUS,
            A.CREATED_AT
        FROM MEMBER_APPLY A
        LEFT JOIN MEMBER R
            ON A.REFERRER_ACCOUNT_NO = R.ACCOUNT_NO
        {$whereSql}
        ORDER BY A.APPLY_ID DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM MEMBER_APPLY
        {$whereSql}
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    
    jsonResponse(RES_SUCCESS, $list, $total);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}

?>