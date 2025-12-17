<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

try {

    $_POST['memberId'] = 'kni1993@naver.com';
    $_POST['memberPw'] = '123454';

    $memberId = $_POST['memberId'] ?? '';
    $password = $_POST['memberPw'] ?? '';

    if (empty($memberId) || empty($password)) {
        jsonResponse(RES_INVALID_PARAM, [], 400);
    }

    if (!filter_var($memberId, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(RES_INVALID_EMAIL, [], 400);
    }

    $payload = [
        'accountNo' => $memberId,
        'pageIndex' => 1,
        'pageSize'  => 1,
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/station/user/query',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($payload),
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
    $memberInfo = json_decode($response, true);
    curl_close($curl);

    if (($memberInfo['status'] ?? '') !== 'SUCCESS') {
        jsonResponse(RES_API_RESPONSE_ERROR, [], 500);
    }
    
    if (empty($memberInfo['data'][0])) {
        jsonResponse(RES_USER_NOT_FOUND, [], 404);
    }

    if(md5($password) == $memberInfo['data'][0]['password']){
        jsonResponse(RES_SUCCESS, [
            'accountNo'    => $memberInfo['data'][0]['accountNo'],
            'userId' => $memberInfo['data'][0]['userId'],
            'status' => $memberInfo['data'][0]['status']
        ]);
    }else{
        if (!hash_equals($memberInfo['data'][0]['password'], md5($password))) {
            jsonResponse(RES_PASSWORD_MISMATCH, [], 401);
        }
    }
} catch (Throwable $e) {
    jsonResponse(RES_SYSTEM_ERROR, [
        'error' => $e->getMessage()
    ], 500);
}
?>