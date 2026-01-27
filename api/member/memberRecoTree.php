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
        WHERE ACCOUNT_NO = ?
        ORDER BY CREATED_AT ASC
    ");
    $stmt->execute([$accountNo]);
    $downRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $downline = [];
    foreach ($downRows as $row) {
       
        $downline[] = [
            'level'             => 1,
            'referrerAccountNo' => $row['ACCOUNT_NO'],          
            'accountNo'         => $row['REFERRER_ACCOUNT_NO'],  
            'name'              => $row['NAME'],
            'phone'             => $row['PHONE'],
            'address'           => $row['ADDRESS'],
            'status'            => $row['STATUS'],
            'rejectReason'      => $row['REJECT_REASON'],
            'createdAt'         => $row['CREATED_AT'],
            'updatedAt'         => $row['UPDATED_AT'],
        ];
    }


    $upline = [];
    $visited = [];

    $current = $accountNo;

    for ($lvl = 1; $lvl <= 3; $lvl++) {
        if ($current === '') break;
        if (isset($visited[$current])) break;
        $visited[$current] = true;


        $stmt = $pdo->prepare("
            SELECT
                ACCOUNT_NO AS UP_ACCOUNT_NO
            FROM MEMBER_APPLY
            WHERE REFERRER_ACCOUNT_NO = ?
            ORDER BY CREATED_AT ASC
            LIMIT 1
        ");
        $stmt->execute([$current]);
        $upAccountNo = strtolower(trim($stmt->fetchColumn() ?? ''));

        if ($upAccountNo === '') break;


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
        $stmt->execute([$upAccountNo]);
        $up = $stmt->fetch(PDO::FETCH_ASSOC);

        $upline[] = [
            'level'             => $lvl,
            'accountNo'         => $upAccountNo,
            'name'              => $up['NAME'] ?? '',
            'phone'             => $up['PHONE'] ?? '',
            'address'           => $up['ADDRESS'] ?? '',
            'status'            => $up['STATUS'] ?? null,
            'createdAt'         => $up['CREATED_AT'] ?? null,
        ];

        $current = $upAccountNo; 
    }

    jsonResponse(RES_SUCCESS, [
        'target' => [
            'accountNo' => $target['ACCOUNT_NO'],
            'name'      => $target['NAME'],
            'phone'     => $target['PHONE'],
            'address'   => $target['ADDRESS'],
            'status'    => $target['STATUS'],
            'createdAt' => $target['CREATED_AT'],
            'updatedAt' => $target['UPDATED_AT'],
        ],
        'downline' => [
            'count' => count($downline),
            'list'  => $downline
        ],
        'upline' => [
            'count' => count($upline),
            'list'  => $upline
        ],
    ]);

} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, ['error' => $e->getMessage()], 500);
}
