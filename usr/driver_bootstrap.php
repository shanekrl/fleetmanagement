<?php
// driver_bootstrap.php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';
require_driver();

/**
 * Returns the logged-in driver's accounts.id
 * We try: (a) direct $_SESSION['account_id'] if you store it,
 * (b) map from tms_user by email (current site behavior),
 * (c) fallback to tms_user.u_id if you temporarily used accounts.id there.
 */
function current_driver_id(mysqli $db): int {
    $acc = 0;
    if (!empty($_SESSION['account_id'])) return (int)$_SESSION['account_id'];

    $u_id = (int)($_SESSION['u_id'] ?? 0);
    if ($u_id > 0) {
        // join by email (tms_user.u_email -> accounts.email)
        $sql = "SELECT a.id 
                  FROM tms_user u 
                  JOIN accounts a ON a.email = u.u_email 
                 WHERE u.u_id = ? AND a.role='driver' 
                 LIMIT 1";
        if ($s = $db->prepare($sql)) {
            $s->bind_param('i', $u_id);
            $s->execute();
            $s->bind_result($accRes);
            if ($s->fetch()) $acc = (int)$accRes;
            $s->close();
        }
        if ($acc) return $acc;

        // last resort: if u_id already matches accounts.id in your env
        $q = $db->query("SELECT id FROM accounts WHERE id={$u_id} AND role='driver' LIMIT 1");
        if ($q && $q->num_rows) { $acc = (int)$q->fetch_assoc()['id']; }
    }
    return $acc;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
