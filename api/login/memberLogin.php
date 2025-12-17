<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/config/accessToken.php';

$memberId = "kni1993@naver.com";
$password = "123456";

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
   CURLOPT_POSTFIELDS =>'{
    "accountNo": "kni1993@naver.com",
    "pageIndex": 1,
    "pageSize": 1
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
$memberInfo = json_decode($response, true);
curl_close($curl);
echo $response;

if(md5($password) == $memberInfo['data'][0]['password']){
    echo "일치";
}else{
    echo "불일치";
}
?>