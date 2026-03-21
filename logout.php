<?php
/**
 * Logout Handler
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');

if (is_logged_in()) {
    $auth->logout();
}

redirect('login.php', 'You have logged out successfully.', 'success');
