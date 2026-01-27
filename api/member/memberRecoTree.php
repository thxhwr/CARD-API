<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = strtolower(trim($_POST['accountNo'] ?? ''));
    if ($accountNo === '') {
        jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
    }

    $stmt = $pdo->prepare("
        SELECT
            REFERRER_ACCOUNT_NO,
            ACCOUNT_NO,
            NAME,
            PHONE,
            ADDRESS,
            STATUS,
            REJECT_REASON,
            CREATED_AT,
            UPDATED_AT
        FROM MEMBER_APPLY
        WHERE ACCOUNT_NO = ?
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

   
    $upline = [];
    $parentAccountNo = strtolower(trim($target['REFERRER_ACCOUNT_NO'] ?? ''));

    if ($parentAccountNo !== '') {
        $stmt = $pdo->prepare("
            SELECT
                REFERRER_ACCOUNT_NO,
                ACCOUNT_NO,
                NAME,
                PHONE,
                ADDRESS,
                STATUS,
                REJECT_REASON,
                CREATED_AT,
                UPDATED_AT
            FROM MEMBER_APPLY
            WHERE ACCOUNT_NO = ?
            LIMIT 1
        ");
        $stmt->execute([$parentAccountNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($row) {
            $upline[] = [
                'level' => 1,
                'referrerAccountNo' => $row['REFERRER_ACCOUNT_NO'],
                'accountNo' => $row['ACCOUNT_NO'],
                'name' => $row['NAME'],
                'phone' => $row['PHONE'],
                'address' => $row['ADDRESS'],
                'status' => $row['STATUS'],
                'rejectReason' => $row['REJECT_REASON'],
                'createdAt' => $row['CREATED_AT'],
                'updatedAt' => $row['UPDATED_AT'],
            ];
        }
    }

    $downlineLevels = [
        1 => [],
        2 => [],
        3 => [],
    ];

    $parents = [$accountNo];
    for ($lvl = 1; $lvl <= 3; $lvl++) {
        if (empty($parents)) break;

        $placeholders = implode(',', array_fill(0, count($parents), '?'));

        $stmt = $pdo->prepare("
            SELECT
                REFERRER_ACCOUNT_NO,
                ACCOUNT_NO,
                NAME,
                PHONE,
                ADDRESS,
                STATUS,
                REJECT_REASON,
                CREATED_AT,
                UPDATED_AT
            FROM MEMBER_APPLY
            WHERE REFERRER_ACCOUNT_NO IN ($placeholders)
            ORDER BY CREATED_AT ASC
        ");
        $stmt->execute($parents);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nextParents = [];
        foreach ($rows as $r) {
            $acc = strtolower(trim($r['ACCOUNT_NO'] ?? ''));
            if ($acc !== '') $nextParents[] = $acc;

            $downlineLevels[$lvl][] = [
                'level' => $lvl,
                'referrerAccountNo' => $r['REFERRER_ACCOUNT_NO'],
                'accountNo' => $r['ACCOUNT_NO'],
                'name' => $r['NAME'],
                'phone' => $r['PHONE'],
                'address' => $r['ADDRESS'],
                'status' => $r['STATUS'],
                'rejectReason' => $r['REJECT_REASON'],
                'createdAt' => $r['CREATED_AT'],
                'updatedAt' => $r['UPDATED_AT'],
            ];
        }

        $parents = $nextParents;
    }

    $downlineFlat = array_merge($downlineLevels[1], $downlineLevels[2], $downlineLevels[3]);

    jsonResponse(RES_SUCCESS, [
        'target' => [
            'referrerAccountNo' => $target['REFERRER_ACCOUNT_NO'],
            'accountNo' => $target['ACCOUNT_NO'],
            'name' => $target['NAME'],
            'phone' => $target['PHONE'],
            'address' => $target['ADDRESS'],
            'status' => $target['STATUS'],
            'rejectReason' => $target['REJECT_REASON'],
            'createdAt' => $target['CREATED_AT'],
            'updatedAt' => $target['UPDATED_AT'],
        ],
        'upline' => [
            'count' => count($upline),
            'list' => $upline
        ],
        'downline' => [
            'count' => count($downlineFlat),
            'levels' => [
                'level1' => $downlineLevels[1],
                'level2' => $downlineLevels[2],
                'level3' => $downlineLevels[3],
            ]
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
