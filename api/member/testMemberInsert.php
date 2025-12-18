<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $pdo->beginTransaction();

    $pos = assignDeptAndParent($pdo);

    $accountNo = 'test' . time() . '@test.com';

    $userId = (int)$pdo->query("SELECT IFNULL(MAX(USER_ID), 0) + 1 FROM MEMBER")
                       ->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO MEMBER (
            USER_ID,
            ACCOUNT_NO,
            DEPT,
            DEPT_NO,
            PARENT_USER_ID
        ) VALUES (
            :user_id,
            :account_no,
            :dept,
            :dept_no,
            :parent_user_id
        )
    ");

    $stmt->execute([
        ':user_id'        => $userId,
        ':account_no'     => $accountNo,
        ':dept'           => $pos['dept'],
        ':dept_no'        => $pos['dept_no'],
        ':parent_user_id' => $pos['parent_user_id'],
    ]);

    $pdo->commit();

    echo "✅ 회원가입 성공\n";
    echo "USER_ID : {$userId}\n";
    echo "ACCOUNT : {$accountNo}\n";
    echo "DEPT    : {$pos['dept']}\n";
    echo "DEPT_NO : {$pos['dept_no']}\n";
    echo "PARENT  : {$pos['parent_user_id']}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo $e->getMessage();
}
?>