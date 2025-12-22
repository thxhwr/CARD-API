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

    $stmt = $pdo->query("
        SELECT
            USER_ID,
            PARENT_USER_ID,
            ACCOUNT_NO,
            NAME,
            DEPT,
            DEPT_NO
        FROM MEMBER
        ORDER BY DEPT ASC, DEPT_NO ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    $map = [];
    foreach ($rows as $row) {
        $map[$row['USER_ID']] = [
            'userId'    => (int)$row['USER_ID'],
            'accountNo' => $row['ACCOUNT_NO'],
            'name'      => $row['NAME'],
            'dept'      => (int)$row['DEPT'],
            'deptNo'    => (int)$row['DEPT_NO'],
            'children'  => [],
            'parentId'  => $row['PARENT_USER_ID']
        ];
    }

    
    foreach ($map as $id => &$node) {
        $parentId = $node['parentId'];
        if ($parentId && isset($map[$parentId])) {
            $map[$parentId]['children'][] = &$node;
        }
    }
    unset($node);

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