<?php
// vendor/inc/audit.php
// Compatible audit logger (handles legacy and new call styles)

if (!function_exists('audit_log')) {
  function audit_log(
    mysqli $db,
    $actor_id,
    $action,
    $entity_id = null,
    $details_or_before = null,
    $after_or_status = null,
    $status_or_message = null,
    $message_or_entity_type = null,
    $entity_type_or_email = null,
    $actor_email = null,
    $actor_role = null,
    $ip = null,
    $user_agent = null,
    $before_json = null,
    $after_json = null
  ) {
    try {
      // --- Normalize incoming args for legacy/new callers -------------------
      $argc = func_num_args();

      $status      = 'success';
      $message     = null;
      $entity_type = 'account';
      $before      = null;
      $after       = null;

      if ($argc <= 6) {
        // Legacy 6: audit_log($db,$actor_id,$action,$entity_id,$before,$after)
        $before = $details_or_before;
        $after  = $after_or_status;
      } elseif ($argc === 7) {
        // Mid 7: last is status
        $before = $details_or_before;
        $after  = $after_or_status;
        $status = $status_or_message ?: 'success';
      } else {
        // New 8+: ($before,$after,$status,$message,$entity_type, $actor_email,$actor_role,$ip,$ua)
        $before      = $details_or_before;
        $after       = $after_or_status;
        $status      = $status_or_message ?: 'success';
        $message     = $message_or_entity_type;
        $entity_type = $entity_type_or_email ?: 'account';
        // If $entity_type_or_email was actually an email (older style), shift gracefully
        if ($actor_email === null && filter_var($entity_type_or_email, FILTER_VALIDATE_EMAIL)) {
          $actor_email = $entity_type_or_email;
          $entity_type = 'account';
        }
      }

      // Fallbacks from session (don’t echo anything)
      if ($actor_email === null && !empty($_SESSION['email'])) {
        $actor_email = (string)$_SESSION['email'];
      } elseif ($actor_email === null && !empty($_SESSION['a_email'])) {
        $actor_email = (string)$_SESSION['a_email'];
      }

      if ($actor_role === null) {
        if (!empty($_SESSION['role'])) {
          $actor_role = strtolower((string)$_SESSION['role']);
        } elseif (!empty($_SESSION['user_type'])) {
          $ut = strtolower((string)$_SESSION['user_type']);
          $actor_role = $ut === 'user' ? 'driver' : ($ut === 'admin' ? 'admin' : null);
        }
      }

      if ($ip === null)        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
      if ($user_agent === null) $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

      // JSON normalizer that always yields json_valid(...) output
      $toJson = function($v) {
        if ($v === null) return null;
        if (is_string($v)) {
          $t = trim($v);
          if ($t === '' || $t[0] === '{' || $t[0] === '[') return $v;
          return json_encode(['data' => $v], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      };

      $details_json = $toJson(['ip' => $ip, 'ua' => $user_agent]);
      $before_json  = $toJson($before);
      $after_json   = $toJson($after);

      // Strings (allow NULL)
      $actor_email = $actor_email !== '' ? $actor_email : null;
      $actor_role  = $actor_role  !== '' ? $actor_role  : null;
      $ip          = $ip          !== '' ? $ip          : null;
      $user_agent  = $user_agent  !== '' ? $user_agent  : null;
      $entity_type = $entity_type !== '' ? $entity_type : null;
      $entity_id   = ($entity_id === '' ? null : (string)$entity_id);
      $message     = $message !== '' ? $message : null;
      $status      = in_array($status, ['success','failure','info'], true) ? $status : 'success';

      // Feature-detect INET6_ATON availability once per request
      static $INET6_OK = null;
      if ($INET6_OK === null) {
        $probe = @$db->query("SELECT INET6_ATON('::1') AS x");
        $INET6_OK = (bool)$probe;
        if ($probe) { $probe->free(); }
      }
      $ipExpr = $INET6_OK ? 'INET6_ATON(?)' : '?';

      // Build SQL with IP expr that matches server capability
      $sql = "
        INSERT INTO audit_logs
          (occurred_at, actor_id, actor_email, actor_role, ip_address, user_agent,
           action, entity_type, entity_id, status, message, details_json, before_json, after_json)
        VALUES
          (NOW(), ?, ?, ?, {$ipExpr}, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ";

      if (!$stmt = $db->prepare($sql)) {
        error_log('audit_log prepare failed: '.$db->error);
        return false; // never throw/echo
      }

      // Params (1 int + 12 strings = 13 total)
      $params = [
        (int)$actor_id,
        $actor_email,
        $actor_role,
        $ip,
        $user_agent,
        (string)$action,
        $entity_type,
        $entity_id,
        $status,
        $message,
        $details_json,
        $before_json,
        $after_json,
      ];
      $types = 'i' . str_repeat('s', count($params) - 1);

      // Bind by reference
      $bind = [$types];
      foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
      // @phpstan-ignore-next-line
      call_user_func_array([$stmt, 'bind_param'], $bind);

      $ok = $stmt->execute();
      if (!$ok) error_log('audit_log execute failed: '.$stmt->error);
      $stmt->close();
      return $ok;
    } catch (Throwable $e) {
      // ABSORB all errors – never print/echo, just log
      error_log('audit_log fatal: '.$e->getMessage());
      return false;
    }
  }
}
