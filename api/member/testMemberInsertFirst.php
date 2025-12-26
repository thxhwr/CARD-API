<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

$referrerUserId = null;
$applyId = (int)($_POST['applyId'] ?? 1);

if ($applyId <= 0) {
    jsonResponse(RES_INVALID_PARAM, [], 400);
}

$pdo->beginTransaction();

// 1. 신청 조회
$stmt = $pdo->prepare("
    SELECT *
    FROM MEMBER_APPLY
    WHERE APPLY_ID = ?
    FOR UPDATE
");
$stmt->execute([$applyId]);
$apply = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$apply) {
    jsonResponse(RES_NOT_FOUND, [], 404);
}

if ($apply['STATUS'] !== 'PENDING') {
    jsonResponse(RES_ALREADY_PROCESSED, [], 409);
}

// 2. 추천인 확인
$stmt = $pdo->prepare("
    SELECT USER_ID
    FROM MEMBER
    WHERE ACCOUNT_NO = ?
");
$stmt->execute([$apply['REFERRER_ACCOUNT_NO']]);
$referrerUserId = $stmt->fetchColumn();

if (!$referrerUserId) {
    jsonResponse(RES_REFERRER_NOT_FOUND, [], 404);
}

if (!$referrerUserId) {
    jsonResponse(RES_REFERRER_NOT_FOUND, [], 404);
}

$pdo->beginTransaction();

try {

    if ($apply['NAME'] === '' || $apply['PHONE'] === '' || $apply['ADDRESS'] === '') {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (!isValidName($apply['NAME'])) {
        jsonResponse(RES_INVALID_NAME, [], 400);
    }

    if (!isValidPhone($apply['PHONE'])) {
        jsonResponse(RES_INVALID_PHONE, [], 400);
    }

    if (!isValidAddress($apply['ADDRESS'])) {
        jsonResponse(RES_INVALID_ADDRESS, [], 400);
    }


    //

    $usePoint = 100;

    $orderNo = date('YmdHis') . '-' . random_int(1000, 9999);
    $postData = [
        'userId'  => $userId,
        'orderNo' => $orderNo,
        'amount'  => -abs($usePoint),
        'remark'  => '오프라인 카드 신청',
    ];

    $status = 'SUCCESS';

    if ($status === 'SUCCESS') {
        $pos = assignDeptAndParent($pdo);
        $userId = (int)$pdo->query("SELECT IFNULL(MAX(USER_ID), 0) + 1 FROM MEMBER")
                        ->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO MEMBER (
                USER_ID,
                REFERRER_USER_ID,
                ACCOUNT_NO,
                DEPT,
                DEPT_NO,
                PARENT_USER_ID,
                NAME,
                PHONE,
                ADDRESS
            ) VALUES (
                :user_id,
                :referrer_user_id,
                :account_no,
                :dept,
                :dept_no,
                :parent_user_id,
                :name,
                :phone,
                :address
            )
        ");

        $stmt->execute([
            ':user_id'           => $userId,
            ':referrer_user_id'  => $referrerUserId,
            ':account_no'        => $apply['ACCOUNT_NO'],
            ':dept'              => $pos['dept'],
            ':dept_no'           => $pos['dept_no'],
            ':parent_user_id'    => $pos['parent_user_id'],
            ':name'              => $apply['NAME'],
            ':phone'             => $apply['PHONE'],
            ':address'           => $apply['ADDRESS'],
        ]);

        $maxLevel = 3;
        $level    = 1;
        
        $stmt = $pdo->prepare("
            SELECT REFERRER_USER_ID
            FROM MEMBER
            WHERE USER_ID = ?
        ");
        $stmt->execute([$userId]);
        $referrerId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT LEVEL, RATE
            FROM REFERRAL_REWARD_RATE
            WHERE IS_ACTIVE = 'Y'
            AND LEVEL BETWEEN 1 AND :max_level
            ORDER BY LEVEL ASC
        ");
        $stmt->bindValue(':max_level', $maxLevel, PDO::PARAM_INT);
        $stmt->execute();

        $rewardRates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        while ($level <= $maxLevel && $referrerId) {
            if (!$referrerId) {
                break;
            }

            if (!isset($rewardRates[$level])) {
                break;
            }

            $ratePercent = (float)$rewardRates[$level];
            $rewardTotal = (int)floor($usePoint * $ratePercent);

            if ($rewardTotal <= 0) {
                break;
            }

            $spAmount = (int)floor($rewardTotal / 2);
            $tpAmount = $rewardTotal - $spAmount;

            $stmt = $pdo->prepare("
                INSERT INTO POINT_LOG
                    (USER_ID, TYPE_CODE, ACTION_TYPE, AMOUNT, DESCRIPTION, REF_ID)
                VALUES
                    (:uid, 'SP', 'IN', :amt, :desc, :ref_id)
            ");
            $stmt->execute([
                ':uid'    => $referrerId,
                ':amt'    => $spAmount,
                ':desc'   => "추천 {$level}대 보상 (SP)",
                ':ref_id' => $orderNo,
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO POINT_LOG
                    (USER_ID, TYPE_CODE, ACTION_TYPE, AMOUNT, DESCRIPTION, REF_ID)
                VALUES
                    (:uid, 'TP', 'IN', :amt, :desc, :ref_id)
            ");
            $stmt->execute([
                ':uid'    => $referrerId,
                ':amt'    => $tpAmount,
                ':desc'   => "추천 {$level}대 보상 (TP)",
                ':ref_id' => $orderNo,
            ]);

            $stmt = $pdo->prepare("
                SELECT REFERRER_USER_ID
                FROM MEMBER
                WHERE USER_ID = ?
            ");
            $stmt->execute([$referrerId]);
            $referrerId = $stmt->fetchColumn();

            $level++;
        }

        $maxLevel   = 20;
        $level      = 1;
        $rewardRate = 0.02; // 2%

        $stmt = $pdo->prepare("
            SELECT PARENT_USER_ID
            FROM MEMBER
            WHERE USER_ID = ?
        ");
        $stmt->execute([$userId]);
        $parentId = $stmt->fetchColumn();

        while ($level <= $maxLevel && $parentId) {

            $rewardTotal = (int)floor($usePoint * $rewardRate);

            if ($rewardTotal <= 0) {
                break;
            }

            $spAmount = (int)floor($rewardTotal / 2);
            $tpAmount = $rewardTotal - $spAmount;

            $stmt = $pdo->prepare("
                INSERT INTO POINT_LOG
                    (USER_ID, TYPE_CODE, ACTION_TYPE, AMOUNT, DESCRIPTION, REF_ID)
                VALUES
                    (:uid, 'SP', 'IN', :amt, :desc, :ref_id)
            ");
            $stmt->execute([
                ':uid'    => $parentId,
                ':amt'    => $spAmount,
                ':desc'   => "후원 {$level}대 보상 (SP)",
                ':ref_id' => $orderNo,
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO POINT_LOG
                    (USER_ID, TYPE_CODE, ACTION_TYPE, AMOUNT, DESCRIPTION, REF_ID)
                VALUES
                    (:uid, 'TP', 'IN', :amt, :desc, :ref_id)
            ");
            $stmt->execute([
                ':uid'    => $parentId,
                ':amt'    => $tpAmount,
                ':desc'   => "후원 {$level}대 보상 (TP)",
                ':ref_id' => $orderNo,
            ]);

            $stmt = $pdo->prepare("
                SELECT PARENT_USER_ID
                FROM MEMBER
                WHERE USER_ID = ?
            ");
            $stmt->execute([$parentId]);
            $parentId = $stmt->fetchColumn();

            $level++;
        }

        $stmt = $pdo->prepare("
            UPDATE MEMBER_APPLY
            SET STATUS = 'APPROVED'
            WHERE APPLY_ID = ?
        ");
        $stmt->execute([$applyId]);

        $pdo->commit();

        jsonResponse(RES_SUCCESS, [
            'userId' => $userId
        ]);
    } else {
        if ($status === 'ERROR_1118') {
            jsonResponse(RES_POINT_LACK, [], 400);
        }
    
        jsonResponse(RES_API_RESPONSE_ERROR, [], 500);
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        jsonResponse(RES_DUPLICATE_ACCOUNT, [], 409);
    }
    throw $e;
}
?>