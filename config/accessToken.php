<?php

echo "test";
exit;
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php'; // 예비용
}
// require_once BASE_PATH . '/config/lib.php';

$stmt = $pdo->query("
SELECT CONVERT_TZ(
    FROM_UNIXTIME(AT_EXPIRES_IN / 1000),'+00:00','+09:00') AS expires_at, 
    CONVERT_TZ(
    FROM_UNIXTIME(AT_TIME_STAMP / 1000),'+00:00','+09:00') AS timestamp_at, 
    AT_ACCESS_TOKEN 
FROM API_ACCESS_TOKEN WHERE AT_STATUS = 'SUCCESS' ORDER BY AT_TIME_STAMP DESC LIMIT 1;");
$token = $stmt->fetch(PDO::FETCH_ASSOC);

$now = new DateTime('now');
$expireTime = new DateTime($token['expires_at']);

if ($now >= $expireTime) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.eximiuscard.biz/open-api/v1/oauth/access-token', // 운영
    // CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/oauth/access-token', // 테스트
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>'{
        "clientId": "cb6b629375e44c3ca62c621b53659179",
        "clientSecret": "MTc2Njc0MTU2Mzc3MEVYSU1JVVM1YzFiMzVhZjU3ODk0ZThjYTA5NWJjODllNjNmZjE2Nw=="
    }',
    CURLOPT_HTTPHEADER => array(
        'clientId: cb6b629375e44c3ca62c621b53659179',
        'nonce: '.$nonce,
        'timestamp: '.$timestamp,
        'sign: '.$sign,
        'Accept-Language: ko-KR',
        'Content-Type: application/json'
    ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $accessTokenAdd = json_decode($response, true);

    print_r($accessTokenAdd);
    $sql = "
    INSERT INTO API_ACCESS_TOKEN
    (
        AT_STATUS,
        AT_EXPIRES_IN,
        AT_ACCESS_TOKEN,
        AT_TIME_STAMP
    )
    VALUES
    (
        :status,
        :expires_in,
        :access_token,
        :time_stamp
    )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':status'        => $accessTokenAdd['status'],
        ':expires_in'    => (int)$accessTokenAdd['data']['expiresIn'],
        ':access_token'  => $accessTokenAdd['data']['accessToken'],
        ':time_stamp'    => (int)$accessTokenAdd['data']['timestamp'],
    ]);

    $stmt = $pdo->query("
    SELECT CONVERT_TZ(
        FROM_UNIXTIME(AT_EXPIRES_IN / 1000),'+00:00','+09:00') AS expires_at, 
        CONVERT_TZ(
        FROM_UNIXTIME(AT_TIME_STAMP / 1000),'+00:00','+09:00') AS timestamp_at, 
        AT_ACCESS_TOKEN 
    FROM API_ACCESS_TOKEN WHERE AT_STATUS = 'SUCCESS' ORDER BY AT_TIME_STAMP DESC LIMIT 1;");
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>