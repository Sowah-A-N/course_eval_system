<?php
/**
 * Audit Logging Helper
 *
 * Writes a row to the audit_logs table for any admin action.
 * Call log_audit() after every successful INSERT, UPDATE, DELETE, or
 * significant event (login, logout, token generation, export, etc.)
 *
 * Parameters:
 *   $conn        - Active mysqli connection
 *   $user_id     - ID of the acting user
 *   $action_type - One of the AUDIT_* constants defined in constants.php
 *   $table_name  - Target DB table (or null for non-table actions)
 *   $record_id   - PK of the affected record (or null)
 *   $old_values  - Associative array of values before the change (or null)
 *   $new_values  - Associative array of values after the change (or null)
 */

if (!defined('ROLE_ADMIN')) {
    require_once dirname(__DIR__) . '/config/constants.php';
}

function log_audit(
    $conn,
    $user_id,
    $action_type,
    $table_name = null,
    $record_id = null,
    $old_values = null,
    $new_values = null
) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
        : null;

    // audit_logs.user_id is NOT NULL — use 0 as sentinel for anonymous/pre-auth actions
    $user_id = $user_id !== null ? (int)$user_id : 0;

    $old_json = $old_values !== null ? json_encode($old_values) : null;
    $new_json = $new_values !== null ? json_encode($new_values) : null;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO audit_logs
            (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    // record_id is a primary-key integer — bind as 'i', not 's'.
    // Binding an int column as a string forces MySQL to cast on every insert;
    // more importantly it breaks strict-mode servers that enforce column types.
    $record_id = $record_id !== null ? (int)$record_id : null;

    mysqli_stmt_bind_param(
        $stmt,
        'ississss',    // i=user_id, s=action_type, s=table_name, i=record_id, s=old_json, s=new_json, s=ip, s=ua
        $user_id,
        $action_type,
        $table_name,
        $record_id,
        $old_json,
        $new_json,
        $ip_address,
        $user_agent
    );

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
