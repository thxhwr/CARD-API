<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {

    $period = strtolower(trim($_POST['period'] ?? 'day'));          
    $baseDate = trim($_POST['baseDate'] ?? '');                     
    $excludeTestUsers = strtoupper(trim($_POST['excludeTestUsers'] ?? 'Y')); 

    if (!in_array($period, ['day', 'week', 'month'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'period'], 400);
    }

    if ($baseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $baseDate)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'baseDate'], 400);
    }

    if (!in_array($excludeTestUsers, ['Y', 'N'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'excludeTestUsers'], 400);
    }

  
    $tz = new DateTimeZone('Asia/Seoul');
    $base = $baseDate ? new DateTime($baseDate, $tz) : new DateTime('now', $tz);

    $start = clone $base;
    $end   = clone $base;

    if ($period === 'day') {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
    } elseif ($period === 'week') {
      
        $dow = (int)$start->format('N');
        $start->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
        $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
    } else {
        $start->modify('first day of this month')->setTime(0, 0, 0);
        $end->modify('last day of this month')->setTime(23, 59, 59);
    }

 
    $description = 'TP출금';
    $actionType  = 'OUT';

    
    $where = [];
    $params = [];

    $where[] = "ACTION_TYPE = ?";
    $params[] = $actionType;

    $where[] = "DESCRIPTION = ?";
    $params[] = $description;

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
            'actionType' => $actionType,
            'description' => $description,
            'excludeTestUsers' => $excludeTestUsers,
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
