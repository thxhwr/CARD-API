<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// $curl = curl_init();

// curl_setopt_array($curl, array(
//    CURLOPT_URL => 'https://eximius-vcc-pay-customer-service.siweipay.com/open-api/v1/oauth/access-token',
//    CURLOPT_RETURNTRANSFER => true,
//    CURLOPT_ENCODING => '',
//    CURLOPT_MAXREDIRS => 10,
//    CURLOPT_TIMEOUT => 0,
//    CURLOPT_FOLLOWLOCATION => true,
//    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//    CURLOPT_CUSTOMREQUEST => 'POST',
//    CURLOPT_POSTFIELDS =>'{
//     "clientId": "74c01d46896d48608367e308edf9e7f1",
//     "clientSecret": "MTc2NDMyNTk4MTU4MkVYSU1JVVNjYjc5Njc2YWJmOTE0MGQ4YWU4YzhiOTE2MzJlMmNkMA=="
// }',
//    CURLOPT_HTTPHEADER => array(
//       'clientId: 74c01d46896d48608367e308edf9e7f1',
//       'nonce: {{$string.uuid}}',
//       'timestamp: {{$date.anytime|format(\'yyyyMMddHHmmss\')}}',
//       'sign: ',
//       'Accept-Language: en-US',
//       'Content-Type: application/json'
//    ),
// ));

// $response = curl_exec($curl);

// curl_close($curl);
// echo $response;

class SignatureExample
{
    // 共享密钥（需安全保管，勿泄露）
    private const SECRET_KEY = 'hwrft11223@#';

    public static function main()
    {
        $clientId = '74c01d46896d48608367e308edf9e7f1'; // 商户唯一标识（由服务端分配）
        $timestamp = self::getTimestamp(); // 时间戳
        $nonce = self::generateNonce();    // 32位随机串

        $data = sprintf(
            'clientId=%s&nonce=%s&timestamp=%s',
            $clientId,
            $nonce,
            $timestamp
        );

        try {
            $sign = self::generateSign($data, self::SECRET_KEY);

            // 输出结果
            echo "时间戳：{$timestamp}\n";
            echo "32位随机串：{$nonce}\n";
            echo "加密前字符串：{$data}\n";
            echo "最终签名：{$sign}\n";
        } catch (Throwable $e) {
            throw new RuntimeException('签名生成失败', 0, $e);
        }
    }

    /** 生成32位随机字符串（字母+数字） */
    public static function generateNonce(): string
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
    public static function getTimestamp(): string
    {
        return date('YmdHis');
    }

    /** 签名生成核心方法 */
    public static function generateSign(string $data, string $clientSecret): string
    {
        // 拼接 data 与密钥，MD5 后转大写
        $content = $data . $clientSecret;
        return strtoupper(md5($content));
    }
}

// 실행
SignatureExample::main();

?>