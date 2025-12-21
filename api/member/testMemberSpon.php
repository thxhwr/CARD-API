<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $loginUserId = (int)$_POST['userId'];

    $stmt = $pdo->query("
        SELECT
            USER_ID,
            PARENT_USER_ID,
            NAME,
            ACCOUNT_NO,
            DEPT,
            DEPT_NO
        FROM MEMBER
        ORDER BY DEPT ASC, DEPT_NO ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $row['children'] = [];
        $map[$row['USER_ID']] = $row;
    }

    foreach ($map as $id => &$node) {
        $parentId = $node['PARENT_USER_ID'];
        if ($parentId && isset($map[$parentId])) {
            $map[$parentId]['children'][] = &$node;
        }
    }

    if (!isset($map[$loginUserId])) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

    jsonResponse(RES_SUCCESS, $map[$loginUserId]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
?>