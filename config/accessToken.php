<?php
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
    
    $clientId  = '74c01d46896d48608367e308edf9e7f1';
    $timestamp = getTimestamp();
    $nonce     = generateNonce();

    $data = sprintf('clientId=%s&nonce=%s&timestamp=%s',$clientId,$nonce,$timestamp);

    $sign = generateSign($data, SECRET_KEY);

    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/oauth/access-token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>'{
        "clientId": "74c01d46896d48608367e308edf9e7f1",
        "clientSecret": "MTc2NDMyNTk4MTU4MkVYSU1JVVNjYjc5Njc2YWJmOTE0MGQ4YWU4YzhiOTE2MzJlMmNkMA=="
    }',
    CURLOPT_HTTPHEADER => array(
        'clientId: 74c01d46896d48608367e308edf9e7f1',
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

print_r($token);
?>