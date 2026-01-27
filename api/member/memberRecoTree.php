<?php
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $accountNo = strtolower(trim($_POST['accountNo'] ?? ''));
    if ($accountNo === '') {
        jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
    }

    // ✅ target: 일단 MEMBER_APPLY에서 계정 존재 확인용(한 줄)
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

    // =========================
    // ✅ (1) 하위: accountNo(추천인)가 추천한 회원들
    // 네 정의: ACCOUNT_NO(추천인) -> REFERRER_ACCOUNT_NO(피추천인)
    // 그러므로 "내가 추천한 사람들" = WHERE ACCOUNT_NO = 내 accountNo
    // =========================
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
        // row에서 실제 하위 회원 계정은 REFERRER_ACCOUNT_NO (네 정의)
        $downline[] = [
            'level'             => 1,
            'referrerAccountNo' => $row['ACCOUNT_NO'],           // 추천인(나)
            'accountNo'         => $row['REFERRER_ACCOUNT_NO'],  // 피추천인(하위)
            'name'              => $row['NAME'],
            'phone'             => $row['PHONE'],
            'address'           => $row['ADDRESS'],
            'status'            => $row['STATUS'],
            'rejectReason'      => $row['REJECT_REASON'],
            'createdAt'         => $row['CREATED_AT'],
            'updatedAt'         => $row['UPDATED_AT'],
        ];
    }

    // =========================
    // ✅ (2) 상위: 나(accountNo)를 추천한 회원들 3대까지
    // 상위 1대: WHERE REFERRER_ACCOUNT_NO = 내 accountNo 인 row의 ACCOUNT_NO 가 추천인
    // =========================
    $upline = [];
    $visited = [];

    $current = $accountNo;

    for ($lvl = 1; $lvl <= 3; $lvl++) {
        if ($current === '') break;
        if (isset($visited[$current])) break;
        $visited[$current] = true;

        // "current(나)를 추천한 사람" 찾기
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

        // 상위 계정 상세(있으면)
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

        $current = $upAccountNo; // 다음 상위로
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
