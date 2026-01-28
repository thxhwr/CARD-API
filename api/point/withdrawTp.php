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
        // 오늘 포함 뒤로 7일(= base 포함 -6일)
        $start->modify('-6 days')->setTime(0, 0, 0);
    } else {
        // 오늘 포함 뒤로 1개월
        $start->modify('-1 month')->setTime(0, 0, 0);
    }

    /**
     * 공통 필터(기간/TP/OUT/테스트계정 제외)
     */
    $baseWhere = [];
    $baseParams = [];

    $baseWhere[] = "TYPE_CODE = ?";
    $baseParams[] = "TP";

    $baseWhere[] = "ACTION_TYPE = ?";
    $baseParams[] = "OUT";

    $baseWhere[] = "CREATED_AT >= ?";
    $baseParams[] = $start->format('Y-m-d H:i:s');

    $baseWhere[] = "CREATED_AT <= ?";
    $baseParams[] = $end->format('Y-m-d H:i:s');

    if ($excludeTestUsers === 'Y') {
        $baseWhere[] = "USER_ID NOT BETWEEN 1 AND 15";
    }

    /**
     * 1) TP 출금(수수료 제외)
     * - "TP ... 출금" 포함
     * - "수수료" 포함된 건 제외
     */
    $whereWithdraw = $baseWhere;
    $paramsWithdraw = $baseParams;

    $whereWithdraw[] = "DESCRIPTION LIKE ?";
    $paramsWithdraw[] = "%TP%출금%";

    $whereWithdraw[] = "DESCRIPTION NOT LIKE ?";
    $paramsWithdraw[] = "%수수료%";

    $sqlWithdraw = "
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(AMOUNT), 0) AS totalAmount
        FROM POINT_LOG
        WHERE " . implode(" AND ", $whereWithdraw) . "
    ";

    $stmt = $pdo->prepare($sqlWithdraw);
    $stmt->execute($paramsWithdraw);
    $withdrawRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    /**
     * 2) TP 출금 수수료만
     * - 문구가 정확히 "TP 출금 수수료" 계열이라면 아래처럼 좁혀도 되고,
     * - 표현이 다양하면 "%수수료%"로 넓혀도 됩니다.
     */
    $whereFee = $baseWhere;
    $paramsFee = $baseParams;

    $whereFee[] = "DESCRIPTION LIKE ?";
    $paramsFee[] = "%TP%출금%수수료%";
    // 또는 (더 넓게)
    // $whereFee[] = "DESCRIPTION LIKE ?";
    // $paramsFee[] = "%수수료%";

    $sqlFee = "
        SELECT
            COUNT(*) AS cnt,
            COALESCE(SUM(AMOUNT), 0) AS totalAmount
        FROM POINT_LOG
        WHERE " . implode(" AND ", $whereFee) . "
    ";

    $stmt = $pdo->prepare($sqlFee);
    $stmt->execute($paramsFee);
    $feeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    jsonResponse(RES_SUCCESS, [
        'tpWithdraw' => [
            'count' => (int)($withdrawRow['cnt'] ?? 0),
            'total' => (int)($withdrawRow['totalAmount'] ?? 0),
        ],
        'tpWithdrawFee' => [
            'count' => (int)($feeRow['cnt'] ?? 0),
            'total' => (int)($feeRow['totalAmount'] ?? 0),
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
            'withdraw' => [
                'descriptionLike' => '%TP%출금%',
                'descriptionNotLike' => '%수수료%',
            ],
            'fee' => [
                'descriptionLike' => '%TP%출금%수수료%',
                // 'descriptionLike' => '%수수료%', // 넓게 잡는 경우
            ],
            'excludeTestUsers' => $excludeTestUsers,
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
