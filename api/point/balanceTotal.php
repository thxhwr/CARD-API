<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $actionType = strtoupper(trim($_POST['actionType'] ?? '')); 
    $typeCodes  = $_POST['typeCodes'] ?? ['SP','TP','LP'];    

    
    if (!is_array($typeCodes)) {
        $typeCodes = array_filter(array_map('trim', explode(',', (string)$typeCodes)));
    }
    if (empty($typeCodes)) {
        $typeCodes = ['SP','TP','LP'];
    }


    $allowed = ['SP','TP','LP'];
    $typeCodes = array_values(array_intersect($allowed, array_map('strtoupper', $typeCodes)));
    if (empty($typeCodes)) {
        $typeCodes = ['SP','TP','LP'];
    }

    
    if ($actionType !== '' && !in_array($actionType, ['IN','OUT'], true)) {
        jsonResponse(RES_INVALID_PARAM, ['field' => 'actionType'], 400);
    }

   
    $where = [];
    $params = [];

    
    $where[] = "USER_ID NOT BETWEEN 1 AND 15";


    $inPlaceholders = implode(',', array_fill(0, count($typeCodes), '?'));
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
        WHERE " . implode(" AND ", $where) . "
        GROUP BY TYPE_CODE
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

   
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

   
    $result = [
        'SP' => (int)($rows['SP'] ?? 0),
        'TP' => (int)($rows['TP'] ?? 0),
        'LP' => (int)($rows['LP'] ?? 0),
    ];

    jsonResponse(RES_SUCCESS, [
        'total' => $result,
        'filters' => [
            'actionType' => $actionType ?: null,
            'typeCodes'  => $typeCodes,
            'excludedUserIdRange' => '1-15',
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
