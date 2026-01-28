<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

try {
    $accountNo   = trim($_POST['accountNo'] ?? 'youbr919@naver.com');
    $accountId   = trim($_POST['accountId'] ?? '2222578');
    $amount      = (int)($_POST['amount'] ?? 10);
    $description = trim($_POST['description'] ?? 'TP 출금');

    $MIN_WITHDRAW_AMOUNT = 10;
    $FEE_AMOUNT = 1;
    $totalDeductAmount = $amount + $FEE_AMOUNT;

    if ($accountNo === '' || $accountId === '' || $amount <= 0) {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (
        $accountNo === '' ||
        $accountId === '' ||
        $amount < $MIN_WITHDRAW_AMOUNT
    ) {
        jsonResponse(RES_INVALID_PARAM, [
            'message' => '최소 출금 금액은 10입니다.'
        ], 400);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT USER_ID
        FROM MEMBER
        WHERE ACCOUNT_NO = ?
        LIMIT 1
    ");
    $stmt->execute([$accountNo]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN ACTION_TYPE = 'IN'  THEN AMOUNT
                WHEN ACTION_TYPE = 'OUT' THEN -AMOUNT
            END
        ), 0)
        FROM POINT_LOG
        WHERE USER_ID = ?
          AND TYPE_CODE = 'TP'
        FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $balance = (int)$stmt->fetchColumn();

    if ($balance < $totalDeductAmount) {
        $pdo->rollBack();
        jsonResponse(RES_POINT_LACK, [
            'required' => $totalDeductAmount,
            'balance'  => $balance
        ], 400);
    }

    $orderNo = date('YmdHis') . '-' . random_int(1000, 9999);

    $postData = [
        'transferUserId'  => $accountId,
        'amount'  => $amount,
        'remark'  => 'Thxdeal 포인트 변환',
        'orderNo' => $orderNo,
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.eximiuscard.biz/open-api/v1/user/balance/adjustment',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>json_encode($postData, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => array(
        'access_token: '.$token['AT_ACCESS_TOKEN'],
        'clientId: cb6b629375e44c3ca62c621b53659179',
        'nonce: '.$nonce,
        'timestamp: '.$timestamp,
        'sign: '.$sign,
        'Accept-Language: ko-KR',
        'Content-Type: application/json'
    ),
    ));

    $response = curl_exec($curl);

    $payout = json_decode($response, true);
    $status = $payout['status'] ?? '';
    curl_close($curl);
print_r($payout);
    if ($status === 'SUCCESS') {    
        
        $stmt = $pdo->prepare("
        INSERT INTO POINT_LOG (
            USER_ID,
            TYPE_CODE,
            ACTION_TYPE,
            AMOUNT,
            DESCRIPTION,
            CREATED_AT
        ) VALUES (
            :user_id,
            'TP',
            'OUT',
            :amount,
            :description,
            NOW()
        )
        ");
        $stmt->execute([
        ':user_id'     => $userId,
        ':amount'      => $amount,
        ':description' => 'TP 출금'
        ]);

        $stmt = $pdo->prepare("
        INSERT INTO POINT_LOG (
            USER_ID,
            TYPE_CODE,
            ACTION_TYPE,
            AMOUNT,
            DESCRIPTION,
            CREATED_AT
        ) VALUES (
            :user_id,
            'TP',
            'OUT',
            :amount,
            :description,
            NOW()
        )
        ");
        $stmt->execute([
        ':user_id'     => $userId,
        ':amount'      => $FEE_AMOUNT,
        ':description' => 'TP 출금 수수료'
        ]);

        $pdo->commit();

        jsonResponse(RES_SUCCESS, [
            'withdrawAmount' => $amount,
            'feeAmount'      => $FEE_AMOUNT,
            'totalDeducted'  => $totalDeductAmount,
            'remainBalance' => $balance - $totalDeductAmount
        ]);
    }else{
        jsonResponse(RES_API_RESPONSE_ERROR, [], 500);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}

?>