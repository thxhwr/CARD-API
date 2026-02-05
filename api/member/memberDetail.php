<?php
include __DIR__ . "/head.php";
include __DIR__ . "/side.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function callApi(string $url, array $postData): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 10,
  ]);
  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return [null, "API 호출 실패: {$err}"];
  }
  curl_close($ch);

  $json = json_decode($raw, true);
  if (!is_array($json)) return [null, "응답 JSON 파싱 실패"];
  $resCode = (int)($json['resCode'] ?? $json['code'] ?? 1);
  if ($resCode !== 0) {
    $msg = $json['message'] ?? 'API 오류';
    if (!empty($json['data']['error'])) $msg .= " (" . $json['data']['error'] . ")";
    return [null, $msg];
  }
  return [$json, null];
}

function makeUrl(array $override = []): string {
  $params = array_merge($_GET, $override);
  return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($params);
}

// ===== 파라미터 =====
$accountNo = trim($_GET['accountNo'] ?? '');
if ($accountNo === '') {
  echo "<div style='padding:18px;color:#ef4444;'>accountNo가 없습니다.</div>";
  exit;
}

$typeCode = trim($_GET['typeCode'] ?? 'TP'); // TP=페이
if (!in_array($typeCode, ['TP','SP','LP'], true)) $typeCode = 'TP';

$logPage  = max(1, (int)($_GET['logPage'] ?? 1));
$logLimit = 15;

// ===== API URL =====
$API_MEMBER_DETAIL = 'https://api.thxdeal.com/api/member/memberDetail.php';
$API_POINT_HISTORY = 'https://api.thxdeal.com/api/point/history.php';

// ===== 회원정보 =====
[$detailJson, $detailErr] = callApi($API_MEMBER_DETAIL, ['accountNo' => $accountNo]);
$member = $detailJson['data'] ?? [];

$name    = $member['NAME'] ?? '';
$phone   = $member['PHONE'] ?? '';
$address = $member['ADDRESS'] ?? '';
$refId   = $member['REFERRER_ACCOUNT_NO'] ?? '';
$refName = $member['REFERRER_NAME'] ?? '';
$created = $member['CREATED_AT'] ?? '';
$joinStr = $created ? date('Y-m-d', strtotime($created)) : '';

// ===== 포인트 내역 =====
[$historyJson, $historyErr] = callApi($API_POINT_HISTORY, [
  'accountNo' => $accountNo,
  'typeCode'  => $typeCode,
  'page'      => $logPage,
  'limit'     => $logLimit,
]);

$logs = $historyJson['data'] ?? [];
$totalLogs = (int)($historyJson['total'] ?? $historyJson['totalLine'] ?? 0);
$totalLogPages = max(1, (int)ceil($totalLogs / $logLimit));

// 페이지네이션 범위(5개)
$range = 2;
$start = max(1, $logPage - $range);
$end   = min($totalLogPages, $logPage + $range);
while (($end - $start) < ($range * 2) && $start > 1) $start--;
while (($end - $start) < ($range * 2) && $end < $totalLogPages) $end++;
?>

<div class="detail-wrap">
  <div class="detail-grid">

    <!-- ===== 좌측 : 회원정보 ===== -->
    <section class="panel">
      <div class="member-header">
        <h2>회원정보</h2>
      </div>

      <div class="member-body">
        <?php if ($detailErr): ?>
          <div class="alert"><?= h($detailErr) ?></div>
        <?php endif; ?>

        <div class="field">
          <label>이름</label>
          <input type="text" value="<?= h($name) ?>" readonly>
        </div>

        <div class="field">
          <label>아이디</label>
          <input type="text" value="<?= h($accountNo) ?>" readonly>
        </div>

        <div class="field">
          <label>연락처</label>
          <input type="text" value="<?= h($phone) ?>" readonly>
        </div>

        <div class="field">
          <label>이메일</label>
          <input type="text" value="<?= h($accountNo) ?>" readonly>
        </div>

        <div class="field">
          <label>주소</label>
          <input type="text" value="<?= h($address) ?>" readonly>
        </div>

        <div class="field">
          <label>총 보유 TP</label>
          <input type="text" value="" readonly>
        </div>

        <div class="field">
          <label>총 보유 SP</label>
          <input type="text" value="" readonly>
        </div>

        <div class="field">
          <label>총 보유 LP</label>
          <input type="text" value="" readonly>
        </div>

        <div class="field">
          <label>추천인</label>
          <input type="text" value="<?= h(trim($refId . ($refName ? " ({$refName})" : ""))) ?>" readonly>
        </div>

        <div class="field">
          <label>가입일</label>
          <input type="text" value="<?= h($joinStr) ?>" readonly>
        </div>
      </div>
    </section>

    <!-- ===== 우측 : 포인트 내역 ===== -->
    <section class="panel">
      <div class="point-header">
        <h2>포인트 내역</h2>
        <p>선택한 타입의 입/출금(적립/사용) 로그</p>
      </div>

      <div class="point-body">
        <?php if ($historyErr): ?>
          <div class="alert"><?= h($historyErr) ?></div>
        <?php endif; ?>

        <div class="tabs">
          <a class="tab <?= $typeCode==='TP'?'active':'' ?>" href="<?= h(makeUrl(['typeCode'=>'TP','logPage'=>1])) ?>">페이</a>
          <a class="tab <?= $typeCode==='SP'?'active':'' ?>" href="<?= h(makeUrl(['typeCode'=>'SP','logPage'=>1])) ?>">SP</a>
          <a class="tab <?= $typeCode==='LP'?'active':'' ?>" href="<?= h(makeUrl(['typeCode'=>'LP','logPage'=>1])) ?>">LP</a>
        </div>

        <table class="point-table">
          <thead>
            <tr>
              <th style="width:170px;">일시</th>
              <th style="width:90px;">구분</th>
              <th style="width:100px;">금액</th>
              <th>내용</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$historyErr && empty($logs)): ?>
            <tr><td colspan="4" class="empty">내역이 없습니다.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $r): ?>
              <?php
                $dt = $r['CREATED_AT'] ?? '';
                $dtStr = $dt ? date('Y-m-d H:i', strtotime($dt)) : '';
                $action = strtoupper((string)($r['ACTION_TYPE'] ?? ''));
                $amt = (float)($r['AMOUNT'] ?? 0);
                $desc = $r['DESCRIPTION'] ?? '';
                $cls = ($action === 'OUT' || $action === 'USE') ? 'out' : 'in';
              ?>
              <tr>
                <td><?= h($dtStr) ?></td>
                <td class="<?= h($cls) ?>"><?= h($action) ?></td>
                <td><?= h(number_format($amt)) ?></td>
                <td><?= h($desc) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>

        <?php if (!$historyErr && $totalLogPages > 1): ?>
          <div class="pagination">
            <div class="pages">
              <a class="p <?= $logPage<=1?'disabled':'' ?>" href="<?= h(makeUrl(['logPage'=>1])) ?>">«</a>
              <a class="p <?= $logPage<=1?'disabled':'' ?>" href="<?= h(makeUrl(['logPage'=>max(1,$logPage-1)])) ?>">‹</a>

              <?php if ($start > 1): ?><span class="dots">…</span><?php endif; ?>

              <?php for ($p=$start; $p<=$end; $p++): ?>
                <a class="p <?= $p===$logPage?'active':'' ?>" href="<?= h(makeUrl(['logPage'=>$p])) ?>"><?= (int)$p ?></a>
              <?php endfor; ?>

              <?php if ($end < $totalLogPages): ?><span class="dots">…</span><?php endif; ?>

              <a class="p <?= $logPage>=$totalLogPages?'disabled':'' ?>" href="<?= h(makeUrl(['logPage'=>min($totalLogPages,$logPage+1)])) ?>">›</a>
              <a class="p <?= $logPage>=$totalLogPages?'disabled':'' ?>" href="<?= h(makeUrl(['logPage'=>$totalLogPages])) ?>">»</a>
            </div>

            <div class="count">
              총 <b><?= (int)$totalLogs ?></b>건 · <?= (int)$logPage ?>/<?= (int)$totalLogPages ?>p
            </div>
          </div>
        <?php endif; ?>

      </div>
    </section>

  </div>
</div>

<style>
/* ===== 퍼블리싱(스샷 스타일) ===== */
.detail-wrap { padding: 24px; }
.detail-grid { display:grid; grid-template-columns: 440px 1fr; gap:24px; align-items:start; }
@media (max-width:1100px){ .detail-grid{ grid-template-columns:1fr; } }

.panel{
  background:#fff;
  border-radius:16px;
  border:1px solid #e5e7eb;
  overflow:hidden;
}

/* 좌측 헤더 */
.member-header{
  padding:18px 22px;
  background: linear-gradient(135deg, #c7d7ff 0%, #e5c9ff 100%);
}
.member-header h2{ margin:0; font-size:20px; font-weight:700; }

/* 우측 헤더 */
.point-header{ padding:18px 22px 10px; }
.point-header h2{ margin:0; font-size:20px; font-weight:700; }
.point-header p{ margin:6px 0 0; font-size:13px; color:#6b7280; }

.member-body{ padding:20px 22px 24px; }
.point-body{ padding:14px 22px 22px; }

.field{ margin-bottom:16px; }
.field label{
  display:block;
  margin-bottom:6px;
  font-size:14px;
  font-weight:600;
  color:#374151;
}
.field input{
  width:100%;
  height:42px;
  padding:0 12px;
  border:1px solid #9ca3af;
  border-radius:4px;
  background:#fff;
  font-size:15px;
}

/* 탭 */
.tabs{ display:flex; gap:6px; margin-bottom:14px; }
.tab{
  padding:6px 14px;
  border:1px solid #d1d5db;
  border-radius:4px;
  background:#fff;
  text-decoration:none;
  color:#111827;
  font-size:14px;
  cursor:pointer;
}
.tab.active{
  background:#111827;
  color:#fff;
  border-color:#111827;
}

/* 테이블 */
.point-table{ width:100%; border-collapse:collapse; }
.point-table th, .point-table td{
  padding:10px 8px;
  border-bottom:1px solid #e5e7eb;
  font-size:14px;
}
.point-table th{ text-align:left; color:#6b7280; font-weight:600; }
.empty{ padding:16px; text-align:center; color:#6b7280; }

.in{ color:#2563eb; font-weight:700; }
.out{ color:#dc2626; font-weight:700; }

/* 페이지네이션 */
.pagination{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:14px;
  font-size:14px;
}
.pages{ display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.p{
  padding:4px 8px;
  text-decoration:none;
  color:#111827;
  font-weight:700;
}
.p.active{ text-decoration:underline; }
.p.disabled{ pointer-events:none; opacity:.35; }
.dots{ color:#9ca3af; padding:0 4px; }
.count{ color:#6b7280; }
.alert{
  margin-bottom:12px;
  padding:10px 12px;
  border:1px solid #fecaca;
  background:#fff1f2;
  color:#b91c1c;
  border-radius:8px;
}
</style>

<script>
  // 사이드바 토글(모바일)
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      const t = e.target;
      if (!sidebar.contains(t) && !sidebarToggle.contains(t) && window.innerWidth <= 768) {
        sidebar.classList.remove('open');
      }
    });
  }
</script>

</body>
</html>
