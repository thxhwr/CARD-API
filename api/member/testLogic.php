<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/bootstrap.php';

try {
    $pdo->beginTransaction();

    $buyerUserId = 18;
    $orderId     = 'ORDER_TEST_002';
    $price       = 50000;

    $ratesStmt = $pdo->query("
        SELECT LEVEL, RATE
        FROM REFERRAL_REWARD_RATE
        WHERE IS_ACTIVE = 'Y'
        ORDER BY LEVEL ASC
    ");
    $rewardRates = $ratesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare("
        SELECT USER_ID, PARENT_USER_ID
        FROM MEMBER
        WHERE USER_ID = ?
    ");
    $stmt->execute([$buyerUserId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        throw new Exception('구매자 없음');
    }

    $parentId = $current['PARENT_USER_ID'];
    $level    = 3;

    while ($parentId && isset($rewardRates[$level])) {

        $rate   = (float)$rewardRates[$level];
        $amount = (int)floor($price * $rate);

        $stmt = $pdo->prepare("
            INSERT INTO REFERRAL_REWARD_LOG (
                FROM_USER_ID,
                TO_USER_ID,
                LEVEL,
                RATE,
                AMOUNT,
                ORDER_ID,
                CREATED_AT
            ) VALUES (
                :from_user,
                :to_user,
                :level,
                :rate,
                :amount,
                :order_id,
                NOW()
            )
        ");
        $stmt->execute([
            ':from_user' => $buyerUserId,
            ':to_user'   => $parentId,
            ':level'     => $level,
            ':rate'      => $rate,
            ':amount'    => $amount,
            ':order_id'  => $orderId,
        ]);

        $stmt = $pdo->prepare("
            SELECT PARENT_USER_ID
            FROM MEMBER
            WHERE USER_ID = ?
        ");
        $stmt->execute([$parentId]);
        $parentId = $stmt->fetchColumn();

        $level++;
    }

    $pdo->commit();
    echo "완료";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo $e->getMessage();
}
?>