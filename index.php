<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=UTF-8');
const SECRET_KEY = 'MTc2NDMyNTk4MTU4MkVYSU1JVVNjYjc5Njc2YWJmOTE0MGQ4YWU4YzhiOTE2MzJlMmNkMA==';

function generateNonce(): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $length = strlen($characters);
    $nonce = '';

    for ($i = 0; $i < 32; $i++) {
        $nonce .= $characters[random_int(0, $length - 1)];
    }

    return $nonce;
}

/** 生成指定格式时间戳 yyyyMMddHHmmss */
function getTimestamp(): string
{
    return date('YmdHis');
}

/** 签名生成核心方法 */
function generateSign(string $data, string $clientSecret): string
{
    return strtoupper(md5($data . $clientSecret));
}


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
      'nonce: {$nonce}',
      'timestamp: {$timestamp}',
      'sign: {$sign}',
      'Accept-Language: ko-KR',
      'Content-Type: application/json'
   ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;

?>