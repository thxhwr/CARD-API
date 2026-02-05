<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $status = 'APPROVED';

    $search = trim($_POST['search'] ?? '');
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $limit  = min(100, max(10, (int)($_POST['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    $where[]  = 'M.STATUS = ?';
    $params[] = $status;

    if ($search !== '') {
        $where[] = "(
            M.ACCOUNT_NO LIKE ?
            OR M.NAME LIKE ?
            OR M.PHONE LIKE ?
        )";
        $keyword = '%' . $search . '%';
        array_push($params, $keyword, $keyword, $keyword);
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            M.ACCOUNT_NO,
            M.NAME,
            M.PHONE,
            M.STATUS,
            M.CREATED_AT
        FROM MEMBER_APPLY M
        {$whereSql}
        ORDER BY M.CREATED_AT DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM MEMBER M
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
