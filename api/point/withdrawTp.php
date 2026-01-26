<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $period   = strtolower(trim($_POST['period'] ?? 'day'));  
    $baseDate = trim($_POST['baseDate'] ?? '');          

    if (!in_array($period, ['day','week','month'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'period'], 400);
    }
    if ($baseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $baseDate)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'baseDate'], 400);
    }

    $tz = new DateTimeZone('Asia/Seoul');
    $base = $baseDate ? new DateTime($baseDate, $tz) : new DateTime('now', $tz);

    // 기간 계산
    $start = clone $base;
    $end   = clone $base;

    if ($period === 'day') {
        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);
    } elseif ($period === 'week') {
        // ISO week: 월(1)~일(7)
        $dow = (int)$start->format('N');
        $start->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
        $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
    } else { // month
        $start->modify('first day of this month')->setTime(0, 0, 0);
        $end->modify('last day of this month')->setTime(23, 59, 59);
    }

    // 고정 조건
    $description = 'TP출금';
    $actionType  = 'OUT';

    $sql = "
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(AMOUNT), 0) AS totalAmount
        FROM POINT_LOG
        WHERE  ACTION_TYPE = ?
          AND DESCRIPTION = ?
          AND CREATED_AT >= ?
          AND CREATED_AT <= ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $actionType,
        $description,
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
    ]);

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
            'excludedUserIdRange' => '1-15',
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
