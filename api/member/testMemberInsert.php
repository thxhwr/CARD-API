<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

$referrerUserId = null;

$referrerAccountNo = trim($_POST['referrerAccountNo'] ?? 'thx.manager@gmail.com');
// $userId = trim($_POST['userId'] ?? '');
$accountNo = trim($_POST['accountNo'] ?? 'hwrft'.random(1000, 9999).'@test.com');
$name    = trim($_POST['name'] ?? 'hwrft');
$phone   = trim($_POST['phone'] ?? '010'.random(1000, 9999).random(1000, 9999));
$address = trim($_POST['address'] ?? '경기도 878');

// $accountNo = 'test' . time() . '@test.com';

// if ($userId === '') {
//     jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
// }

if ($accountNo === '') {
    jsonResponse(RES_ACCOUNT_REQUIRED, [], 400);
}

$accountNo = strtolower($accountNo);

if (!filter_var($accountNo, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(RES_INVALID_EMAIL, [], 400);
}

$stmt = $pdo->prepare("
    SELECT 1
    FROM MEMBER
    WHERE ACCOUNT_NO = ?
    LIMIT 1
");
$stmt->execute([$accountNo]);

if ($stmt->fetchColumn()) {
    jsonResponse(RES_ACCOUNT_DUPLICATED, [], 409);
}

if ($referrerAccountNo === '') {
    jsonResponse(RES_REFERRER_REQUIRED, [], 400);
}

// 2. 추천인 형식 검사
if (!filter_var($referrerAccountNo, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(RES_INVALID_REFERRER, [], 400);
}

// 3. 추천인 존재 검사
$stmt = $pdo->prepare("
    SELECT USER_ID
    FROM MEMBER
    WHERE ACCOUNT_NO = ?
    LIMIT 1
");
$stmt->execute([$referrerAccountNo]);

$referrerUserId = $stmt->fetchColumn();

if (!$referrerUserId) {
    jsonResponse(RES_REFERRER_NOT_FOUND, [], 404);
}

$pdo->beginTransaction();

try {

    if ($name === '' || $phone === '' || $address === '') {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (!isValidName($name)) {
        jsonResponse(RES_INVALID_NAME, [], 400);
    }

    if (!isValidPhone($phone)) {
        jsonResponse(RES_INVALID_PHONE, [], 400);
    }

    if (!isValidAddress($address)) {
        jsonResponse(RES_INVALID_ADDRESS, [], 400);
    }


    //

    $usePoint = 100;

    $orderNo = date('YmdHis') . '-' . random_int(1000, 9999);
    // $postData = [
    //     'userId'  => $userId,
    //     'orderNo' => $orderNo,
    //     'amount'  => -abs($usePoint),
    //     'remark'  => '오프라인 카드 신청',
    // ];

    // $curl = curl_init();

    // curl_setopt_array($curl, array(
    // CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/station/user/payment',
    // CURLOPT_RETURNTRANSFER => true,
    // CURLOPT_ENCODING => '',
    // CURLOPT_MAXREDIRS => 10,
    // CURLOPT_TIMEOUT => 0,
    // CURLOPT_FOLLOWLOCATION => true,
    // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    // CURLOPT_CUSTOMREQUEST => 'POST',
    // CURLOPT_POSTFIELDS =>json_encode($postData, JSON_UNESCAPED_UNICODE),
    // CURLOPT_HTTPHEADER => array(
    //     'access_token: '.$token['AT_ACCESS_TOKEN'],
    //     'clientId: 74c01d46896d48608367e308edf9e7f1',
    //     'nonce: '.$nonce,
    //     'timestamp: '.$timestamp,
    //     'sign: '.$sign,
    //     'Accept-Language: ko-KR',
    //     'Content-Type: application/json'
    // ),
    // ));

    // $response = curl_exec($curl);

    // $payout = json_decode($response, true);
    // $status = $payout['status'] ?? '';
    curl_close($curl);

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
            ':account_no'        => $accountNo,
            ':dept'              => $pos['dept'],
            ':dept_no'           => $pos['dept_no'],
            ':parent_user_id'    => $pos['parent_user_id'],
            ':name'              => $name,
            ':phone'             => $phone,
            ':address'           => $address,
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