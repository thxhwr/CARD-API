<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $search = trim($_POST['search'] ?? '');
    $page   = max(1, (int)($_POST['page'] ?? 1));
    $limit  = min(100, max(10, (int)($_POST['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    // ✅ 승인된 신청(=승인 회원로 간주)
    $where[]  = "A.STATUS = ?";
    $params[] = "APPROVED";

    // ✅ 검색: 이름/아이디/연락처 (MEMBER_APPLY 기준으로 가능)
    if ($search !== '') {
        $where[] = "(
            A.ACCOUNT_NO LIKE ?
            OR A.NAME LIKE ?
            OR A.PHONE LIKE ?
        )";
        $kw = '%' . $search . '%';
        array_push($params, $kw, $kw, $kw);
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // ✅ 목록 (최신 신청일 기준)
    $stmt = $pdo->prepare("
        SELECT
            A.APPLY_ID,
            A.ACCOUNT_NO,
            A.NAME,
            A.PHONE,
            A.ADDRESS,
            A.REFERRER_ACCOUNT_NO,
            A.STATUS,
            A.CREATED_AT
        FROM MEMBER_APPLY A
        {$whereSql}
        ORDER BY A.APPLY_ID DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ 총 개수
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM MEMBER_APPLY A
        {$whereSql}
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    jsonResponse(RES_SUCCESS, $list, $total);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
