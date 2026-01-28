<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $period = strtolower(trim($_POST['period'] ?? 'day'));                 
    $baseDate = trim($_POST['baseDate'] ?? '');                            
    $excludeTestUsers = strtoupper(trim($_POST['excludeTestUsers'] ?? 'N')); 

    if (!in_array($period, ['day','week','month'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'period'], 400);
    }
    if ($baseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $baseDate)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'baseDate'], 400);
    }
    if (!in_array($excludeTestUsers, ['Y','N'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'excludeTestUsers'], 400);
    }

    $tz = new DateTimeZone('Asia/Seoul');
    $base = $baseDate ? new DateTime($baseDate, $tz) : new DateTime('now', $tz);

    $end = clone $base;
    $end->setTime(23, 59, 59);

    $start = clone $base;

    if ($period === 'day') {
        $start->setTime(0, 0, 0);
    } elseif ($period === 'week') {
        $start->modify('-6 days')->setTime(0, 0, 0);
    } else { 
        $start->modify('-1 month')->setTime(0, 0, 0);
    }

    $where = [];
    $params = [];

    $where[] = "TYPE_CODE = ?";
    $params[] = "TP";

    $where[] = "ACTION_TYPE = ?";
    $params[] = "OUT";

    $where[] = "DESCRIPTION LIKE ?";
    $params[] = "%TP%출금%";

    $where[] = "DESCRIPTION NOT LIKE ?";
    $params[] = "%수수료%";


    $where[] = "CREATED_AT >= ?";
    $params[] = $start->format('Y-m-d H:i:s');

    $where[] = "CREATED_AT <= ?";
    $params[] = $end->format('Y-m-d H:i:s');

    if ($excludeTestUsers === 'Y') {
        $where[] = "USER_ID NOT BETWEEN 1 AND 15";
    }

    $sql = "
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(AMOUNT), 0) AS totalAmount
        FROM POINT_LOG
        WHERE " . implode(" AND ", $where) . "
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse(RES_SUCCESS, [
        'tpWithdraw' => [
            'count' => (int)($row['cnt'] ?? 0),
            'total' => (int)($row['totalAmount'] ?? 0),
        ],
        'range' => [
            'period' => $period,
            'baseDate' => $base->format('Y-m-d'),
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ],
        'filters' => [
            'typeCode' => 'TP',
            'actionType' => 'OUT',
            'descriptionLike' => '%TP%출금%',
            'excludeTestUsers' => $excludeTestUsers,
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
