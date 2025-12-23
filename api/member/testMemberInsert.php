<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

$referrerUserId = null;

$referrerAccountNo = trim($_POST['referrerAccountNo'] ?? 'kni1993@naver.com');
$accountNo = trim($_POST['accountNo'] ?? 'ksw93152@nate.com');
$name    = trim($_POST['name'] ?? '최지헌');
$phone   = trim($_POST['phone'] ?? '01012341234');
$address = trim($_POST['address'] ?? '28562 충북 청주시 서원구 1순환로 627 빌딩');

// $accountNo = 'test' . time() . '@test.com';

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

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/station/user/payment',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>'{
        "userId": 11,
        "orderNo": "1234",
        "amount": -1000,
        "remark": "test"
    }',
    CURLOPT_HTTPHEADER => array(
        'access_token: '.$token['AT_ACCESS_TOKEN'],
        'clientId: 74c01d46896d48608367e308edf9e7f1',
        'nonce: '.$nonce,
        'timestamp: '.$timestamp,
        'sign: '.$sign,
        'Accept-Language: ko-KR',
        'Content-Type: application/json'
    ),
    ));

    $response = curl_exec($curl);

    $payout = json_decode($response, true);
    curl_close($curl);
    print_r($payout);
    exit;
    if (($payout['status'] ?? '') !== 'SUCCESS') {
        jsonResponse(RES_API_RESPONSE_ERROR, [], 500);
    }


    $pdo->beginTransaction();

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

    $pdo->commit();

    echo "USER_ID : {$userId}\n";
    echo "referrerUserId : {$referrerUserId}\n";
    echo "ACCOUNT : {$accountNo}\n";
    echo "DEPT    : {$pos['dept']}\n";
    echo "DEPT_NO : {$pos['dept_no']}\n";
    echo "PARENT  : {$pos['parent_user_id']}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        jsonResponse(RES_DUPLICATE_ACCOUNT, [], 409);
    }
    throw $e;
}
?>