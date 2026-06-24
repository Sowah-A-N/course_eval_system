<?php
/**
 * User Account Helper Functions
 *
 * Shared utilities for automatic username derivation and temporary
 * password generation used by all user-creation forms.
 */

/**
 * Derive a unique username from first and last name.
 * Format: firstname.lastname  (lowercase, letters only, no accents)
 * On collision: append incrementing suffix → firstname.lastname2, …3, …
 */
function ces_derive_username(mysqli $conn, string $f_name, string $l_name): string {
    $sanitize = function($s) { return strtolower(preg_replace('/[^a-zA-Z]/', '', $s)); };
    $base = $sanitize($f_name) . '.' . $sanitize($l_name);
    if ($base === '.' || $base === '') {
        $base = 'user.' . bin2hex(random_bytes(3));
    }

    $username = $base;
    $suffix   = 2;
    while (true) {
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM user_details WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        mysqli_stmt_close($stmt);
        if (!$exists) {
            return $username;
        }
        $username = $base . $suffix++;
        if ($suffix > 9999) {
            return $base . bin2hex(random_bytes(2));
        }
    }
}

/**
 * Generate a temporary password that always satisfies the application
 * password policy (uppercase + lowercase + digit, minimum 10 characters).
 *
 * The password is shown once to the creator and then stored hashed.
 * The user is forced to change it on first login.
 */
function ces_generate_temp_password(): string {
    // 2 uppercase letters (A-Z)
    $upper = chr(rand(65, 90)) . chr(rand(65, 90));
    // 6 lowercase hex characters (a-f + 0-9) from random bytes
    $lower = bin2hex(random_bytes(3));
    // 2 guaranteed digits
    $digits = str_pad((string)rand(0, 99), 2, '0', STR_PAD_LEFT);

    $raw    = $upper . $lower . $digits; // 10 characters total
    $chars  = str_split($raw);
    shuffle($chars);
    return implode('', $chars);
}
