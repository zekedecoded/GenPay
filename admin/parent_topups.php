<?php
// Parent top-ups were merged into the tabbed admin/topups.php page —
// this stub only keeps old links and bookmarks working.
require_once __DIR__ . '/../connection/config.php';

header('Location: ' . ADMIN_URL . '/topups.php?tab=parent', true, 301);
exit;
