<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=UTF-8');

date_default_timezone_set('Asia/Seoul');

if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Forbidden');
}

define('DB_HOST', '72.60.237.149');
define('DB_PORT', '37722');
define('DB_NAME', 'THXDEAL_DB');
define('DB_USER', 'thxdeal');
define('DB_PASS', 'dealThx11223@#');

define('DB_SSL_KEY',  '/home/thxdeal/mysql_certs/client-key.pem');
define('DB_SSL_CERT', '/home/thxdeal/mysql_certs/client-cert.pem');
define('DB_SSL_CA',   '/home/thxdeal/mysql_certs/ca.pem');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            PDO::MYSQL_ATTR_SSL_KEY  => DB_SSL_KEY,
            PDO::MYSQL_ATTR_SSL_CERT => DB_SSL_CERT,
            PDO::MYSQL_ATTR_SSL_CA   => DB_SSL_CA,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('DB Connection Error');
}

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


/* =========================
 * Response Code 정의
 * ========================= */

// 성공
const RES_SUCCESS              = 0;

// 요청 파라미터 오류 (1000번대)
const RES_INVALID_PARAM        = 1001;
const RES_INVALID_EMAIL        = 1002;
const RES_INVALID_PASSWORD     = 1003;
const RES_ACCOUNT_REQUIRED     = 1004;
const RES_ACCOUNT_DUPLICATED   = 1005;
const RES_INVALID_PHONE        = 1006;
const RES_INVALID_ADDRESS      = 1007;
const RES_INVALID_NAME         = 1008;

// 외부 API 오류 (2000번대)
const RES_API_CALL_FAIL        = 2001;
const RES_API_RESPONSE_ERROR   = 2002;

// 회원 관련 오류 (3000번대)
const RES_USER_NOT_FOUND       = 3001;
const RES_USER_DISABLED        = 3002;
const RES_PASSWORD_MISMATCH    = 3003;
const RES_DUPLICATE_ACCOUNT    = 3004;

// 추천인 관련 오류 (4000번대)
const RES_INVALID_REFERRER     = 4001;
const RES_REFERRER_NOT_FOUND   = 4002;


// 출금 관련 오류 (5000번대)
const RES_POINT_LACK           = 5001;

// 시스템 오류 (9000번대)
const RES_SYSTEM_ERROR         = 9000;

/* =========================
 * Response Message 매핑
 * ========================= */

function getResMessage(int $code): string
{
    $messages = [
        // 성공
        RES_SUCCESS              => '성공',

        // 요청 파라미터
        RES_INVALID_PARAM        => '필수 파라미터가 누락되었습니다.',
        RES_INVALID_EMAIL        => '아이디 형식이 올바르지 않습니다.',
        RES_INVALID_PASSWORD     => '비밀번호 형식이 올바르지 않습니다.',
        RES_ACCOUNT_REQUIRED     => '아이디는 필수입니다.',
        RES_ACCOUNT_DUPLICATED   => '이미 존재하는 아이디입니다.',
        RES_INVALID_PHONE        => '전화번호 형식이 올바르지 않습니다.',
        RES_INVALID_ADDRESS      => '주소 형식이 올바르지 않습니다.',
        RES_INVALID_NAME         => '이름 형식이 올바르지 않습니다.',

        // 외부 API
        RES_API_CALL_FAIL        => '외부 API 호출 실패',
        RES_API_RESPONSE_ERROR   => 'API 응답 오류',

        // 회원
        RES_USER_NOT_FOUND       => '존재하지 않는 회원입니다.',
        RES_USER_DISABLED        => '비활성화된 계정입니다.',
        RES_PASSWORD_MISMATCH    => '아이디 또는 비밀번호가 일치하지 않습니다.',
        RES_DUPLICATE_ACCOUNT    => '이미 발급신청한 아이디입니다.',

        // 출금
        RES_POINT_LACK           => '잔액이 부족합니다.',

        // 추천인
        RES_INVALID_REFERRER     => '유효하지 않은 추천인입니다.',
        RES_REFERRER_NOT_FOUND   => '존재하지 않는 추천인입니다.',

        // 시스템
        RES_SYSTEM_ERROR         => '시스템 오류',

    ];

    return $messages[$code] ?? '알 수 없는 오류';
}

/* =========================
 * 공통 JSON 응답 함수
 * ========================= */

function jsonResponse(
    int $code,
    array $data = [],
    int $totalLine = 0,
    int $httpStatus = 200
): never {
    http_response_code($httpStatus);

    echo json_encode([
        'resCode'   => $code,
        'message'   => getResMessage($code),
        'data'      => $data,
        'totalLine' => $totalLine
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


function insertLoginLog(array $params): void
{
    global $pdo;

    try {
        $sql = "
            INSERT INTO MEMBER_LOGIN_LOG (
                USER_ID,
                ACCOUNT_NO,
                LOGIN_RESULT,
                FAIL_CODE,
                IP_ADDRESS,
                USER_AGENT
            ) VALUES (
                :user_id,
                :account_no,
                :login_result,
                :fail_code,
                :ip_address,
                :user_agent
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'      => $params['user_id']      ?? null,
            ':account_no'   => $params['account_no'],
            ':login_result' => $params['login_result'],
            ':fail_code'    => $params['fail_code']    ?? null,
            ':ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('[LOGIN_LOG_ERROR] ' . $e->getMessage());
    }
}



//

function calculateDept(int $totalCount): int
{
    $dept = 1;
    while ((2 ** $dept - 1) < $totalCount) {
        $dept++;
    }
    return $dept;
}

function assignDeptAndParent(PDO $pdo): array
{
    // 전체 회원 수
    $total = (int)$pdo->query("SELECT COUNT(*) FROM MEMBER")->fetchColumn();
    $newIndex = $total + 1;

    // dept 계산
    $dept = calculateDept($newIndex);

    // 이전 dept 누적 수
    $prevTotal = (2 ** ($dept - 1)) - 1;
    $deptNo = $newIndex - $prevTotal;

    $parentUserId = null;

    if ($dept > 1) {
        $parentIndex = intdiv($deptNo - 1, 2) + 1;

        $stmt = $pdo->prepare("
            SELECT USER_ID
            FROM MEMBER
            WHERE DEPT = ?
            ORDER BY DEPT_NO
            LIMIT 1 OFFSET ?
        ");
        $stmt->execute([$dept - 1, $parentIndex - 1]);
        $parentUserId = $stmt->fetchColumn();
    }

    return [
        'dept' => $dept,
        'dept_no' => $deptNo,
        'parent_user_id' => $parentUserId
    ];
}

/**
 * 이름 유효성 검사
 * - 한글/영문/공백 허용
 * - 2~30자
 */
function isValidName(string $name): bool
{
    $name = trim($name);

    if ($name === '') {
        return false;
    }

    return (bool)preg_match('/^[가-힣a-zA-Z\s]{2,30}$/u', $name);
}

/**
 * 휴대폰 번호 유효성 검사
 * - 010xxxxxxxx 형식
 * - 하이픈 없이
 */
function isValidPhone(string $phone): bool
{
    $phone = preg_replace('/\D/', '', $phone); // 숫자만 남김

    return (bool)preg_match('/^01[0-9]{8,9}$/', $phone);
}

/**
 * 주소 유효성 검사
 * - 최소 5자 이상
 * - 특수문자 대부분 허용
 */
function isValidAddress(string $address): bool
{
    $address = trim($address);

    return mb_strlen($address, 'UTF-8') >= 5;
}

function buildTree(array $members, int $rootUserId): array
{
    $map = [];
    foreach ($members as $m) {
        $m['children'] = [];
        $map[$m['USER_ID']] = $m;
    }

    $tree = null;

    foreach ($map as $userId => &$node) {
        if ($node['PARENT_USER_ID'] === $rootUserId) {
            $map[$rootUserId]['children'][] = &$node;
        }
    }

    return $map[$rootUserId];
}

?>