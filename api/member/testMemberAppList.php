<?php
require_once __DIR__ . '/../../../config/bootstrap.php';

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
            APPLY_ID,
            ACCOUNT_NO,
            NAME,
            PHONE,
            ADDRESS,
            REFERRER_ACCOUNT_NO,
            STATUS,
            CREATED_AT
        FROM MEMBER_APPLY
        {$whereSql}
        ORDER BY APPLY_ID DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "2";
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