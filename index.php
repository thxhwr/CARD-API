<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
      'nonce: {{$string.uuid}}',
      'timestamp: {{$date.anytime|format(\'yyyyMMddHHmmss\')}}',
      'sign: ',
      'Accept-Language: en-US',
      'Content-Type: application/json'
   ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;

?>