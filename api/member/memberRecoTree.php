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
        WHERE REFERRER_ACCOUNT_NO = ?
        ORDER BY CREATED_AT ASC
    ");
    $stmt->execute([$accountNo]);
    $downRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $downline = [];
    foreach ($downRows as $row) {
        $downline[] = [
            'level'            => 1,
            'referrerAccountNo'=> $row['REFERRER_ACCOUNT_NO'],
            'accountNo'        => $row['ACCOUNT_NO'],
            'name'             => $row['NAME'],
            'phone'            => $row['PHONE'],
            'address'          => $row['ADDRESS'],
            'status'           => $row['STATUS'],
            'rejectReason'     => $row['REJECT_REASON'],
            'createdAt'        => $row['CREATED_AT'],
            'updatedAt'        => $row['UPDATED_AT'],
        ];
    }

 
    $upline = [];
    $visited = []; 

    $currentAccountNo = $accountNo;
    $currentReferrerAccountNo = strtolower(trim($target['REFERRER_ACCOUNT_NO'] ?? ''));

    for ($lvl = 1; $lvl <= 3; $lvl++) {
        if ($currentReferrerAccountNo === '') break;
        if (isset($visited[$currentReferrerAccountNo])) break; 
        $visited[$currentReferrerAccountNo] = true;

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
        $stmt->execute([$currentReferrerAccountNo]);
        $ref = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ref) break;

        $upline[] = [
            'level'            => $lvl,
            'referrerAccountNo'=> $ref['REFERRER_ACCOUNT_NO'],
            'accountNo'        => $ref['ACCOUNT_NO'],
            'name'             => $ref['NAME'],
            'phone'            => $ref['PHONE'],
            'address'          => $ref['ADDRESS'],
            'status'           => $ref['STATUS'],
            'rejectReason'     => $ref['REJECT_REASON'],
            'createdAt'        => $ref['CREATED_AT'],
            'updatedAt'        => $ref['UPDATED_AT'],
        ];

  
        $currentAccountNo = $ref['ACCOUNT_NO'];
        $currentReferrerAccountNo = strtolower(trim($ref['REFERRER_ACCOUNT_NO'] ?? ''));
    }

 
    jsonResponse(RES_SUCCESS, [
        'target' => [
            'referrerAccountNo'=> $target['REFERRER_ACCOUNT_NO'],
            'accountNo'        => $target['ACCOUNT_NO'],
            'name'             => $target['NAME'],
            'phone'            => $target['PHONE'],
            'address'          => $target['ADDRESS'],
            'status'           => $target['STATUS'],
            'rejectReason'     => $target['REJECT_REASON'],
            'createdAt'        => $target['CREATED_AT'],
            'updatedAt'        => $target['UPDATED_AT'],
        ],
        'downline' => [
            'count' => count($downline),
            'list'  => $downline,
        ],
        'upline' => [
            'count' => count($upline),
            'list'  => $upline,
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
