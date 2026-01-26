<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {

    $actionType = strtoupper(trim($_POST['actionType'] ?? '')); // IN / OUT / ''(전체)   
    $typeCodes  = $_POST['typeCodes'] ?? ['SP','TP','LP'];     // 배열 또는 미전달


    if (!is_array($typeCodes)) {
        $typeCodes = array_filter(array_map('trim', explode(',', (string)$typeCodes)));
    }
    if (empty($typeCodes)) $typeCodes = ['SP','TP','LP'];

    // 허용 코드만
    $allowed = ['SP','TP','LP'];
    $typeCodes = array_values(array_intersect($allowed, array_map('strtoupper', $typeCodes)));
    if (empty($typeCodes)) $typeCodes = ['SP','TP','LP'];

    // actionType 검증
    if ($actionType !== '' && !in_array($actionType, ['IN','OUT'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'actionType'], 400);
    }

    // IN절 placeholder 생성
    $inPlaceholders = implode(',', array_fill(0, count($typeCodes), '?'));

    $where = [];
    $params = [];

    $where[] = "TYPE_CODE IN ($inPlaceholders)";
    $params = array_merge($params, $typeCodes);

    if ($actionType !== '') {
        $where[] = "ACTION_TYPE = ?";
        $params[] = $actionType;
    }  

    $sql = "
        SELECT
            TYPE_CODE,
            SUM(
                CASE
                    WHEN ACTION_TYPE = 'IN'  THEN AMOUNT
                    WHEN ACTION_TYPE = 'OUT' THEN -AMOUNT
                    ELSE 0
                END
            ) AS TOTAL
        FROM POINT_LOG
        " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
        GROUP BY TYPE_CODE
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [TYPE_CODE => TOTAL]

    // 없는 타입은 0 보정
    $result = [
        'SP' => (int)($rows['SP'] ?? 0),
        'TP' => (int)($rows['TP'] ?? 0),
        'LP' => (int)($rows['LP'] ?? 0),
    ];

    jsonResponse(RES_SUCCESS, [
        'total' => $result,
        'filters' => [
            'actionType' => $actionType ?: null,           
            'typeCodes' => $typeCodes,
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
