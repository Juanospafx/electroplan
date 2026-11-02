<?php
declare(strict_types=1);

require_once __DIR__ . '/core/auth/session.php';

if (!electroplan_is_authenticated($_SESSION ?? null)) {
    return;
}

electroplan_page_redirect('/electroplan/pages/index.php');
