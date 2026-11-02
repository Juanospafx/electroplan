<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sessionSource = (string) file_get_contents($root . '/core/auth/session.php');
$entrySource = (string) file_get_contents($root . '/index.php');
$createProjectSource = (string) file_get_contents($root . '/api/create_project.php');
$taskManagerApiSource = (string) file_get_contents($root . '/task_manager/api.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
};

// ---------------------------------------------------------------------------
// 1. Static Contract & Source Inspection (TASK-0402 + TASK-0404 + TASK-0405)
// ---------------------------------------------------------------------------
$assert(
    str_contains($sessionSource, "'/electroplan/pages/sso-login.php'"),
    'Anonymous protected pages use the fixed Electroplan SSO initiation path.'
);
$assert(
    !str_contains($sessionSource, '../pages/login.php'),
    'The protected-page guard no longer falls back to local login.'
);
$assert(
    str_contains($sessionSource, 'function electroplan_is_authenticated'),
    'Electroplan exposes a strict server-session authentication predicate.'
);
$assert(
    str_contains($entrySource, "'/electroplan/pages/index.php'"),
    'The authenticated application root targets the canonical dashboard.'
);
$assert(
    !str_contains($entrySource, 'Bulk Upload') && !str_contains($entrySource, '<!doctype html>'),
    'The obsolete Bulk Upload UI is absent from the application root.'
);
$assert(
    !preg_match('/(?:return|next|redirect_uri)\s*=\s*\$_(?:GET|POST|REQUEST)/i', $sessionSource . $entrySource),
    'The entry flow accepts no user-controlled redirect destination.'
);
$assert(
    str_contains($sessionSource, 'function electroplan_is_api_request'),
    'Electroplan defines an API request classification predicate based on server metadata.'
);
$assert(
    str_contains($sessionSource, 'function electroplan_api_unauthorized'),
    'Electroplan defines a fail-closed HTTP 401 JSON handler for unauthorized API requests.'
);
$assert(
    !preg_match('/\[[^\]]*[\'"]viwer[\'"][^\]]*\]/', $sessionSource),
    'Strict session authentication rejects the legacy viwer typo.'
);
$assert(
    str_contains($sessionSource, "'api/api.php'")
    && str_contains($sessionSource, "'api/search.php'")
    && str_contains($sessionSource, "'api/create_project.php'")
    && str_contains($sessionSource, "'task_manager/api.php'"),
    'API classification uses an explicit fixed allowlist for api/api.php, api/search.php, api/create_project.php and task_manager/api.php.'
);
$assert(
    !str_contains($sessionSource, "'api/upload.php'")
    && !preg_match('/preg_match\s*\(\s*[\'"]#[^\'"]*api(?:\/|\$)/', $sessionSource),
    'API classification does not treat every script under api/ as JSON or include upload.php.'
);
$cpSessionPos = strpos($createProjectSource, 'core/auth/session.php');
$cpDbPos = strpos($createProjectSource, 'core/db/connection.php');
$assert(
    $cpSessionPos !== false && $cpDbPos !== false && $cpSessionPos < $cpDbPos,
    'api/create_project.php loads the session guard before database initialization.'
);
$tmSessionPos = strpos($taskManagerApiSource, 'core/auth/session.php');
$tmDbPos = strpos($taskManagerApiSource, 'core/db/connection.php');
$assert(
    $tmSessionPos !== false && $tmDbPos !== false && $tmSessionPos < $tmDbPos,
    'task_manager/api.php loads the session guard before database initialization.'
);

// ---------------------------------------------------------------------------
// 2. Strict Role & Authentication Decisions
// ---------------------------------------------------------------------------
$sessionBackup = $_SESSION ?? null;
$_SESSION = ['user_id' => 4, 'username' => 'Central User', 'role' => 'admin'];
require_once $root . '/core/auth/session.php';

$assert(
    electroplan_is_authenticated(['user_id' => 4, 'username' => 'Central User', 'role' => 'admin']),
    'A complete admin session is authenticated.'
);
$assert(
    electroplan_is_authenticated(['user_id' => 5, 'username' => 'Tech User', 'role' => 'technician']),
    'A complete technician session is authenticated.'
);
$assert(
    electroplan_is_authenticated(['user_id' => 6, 'username' => 'Viewer User', 'role' => 'viewer']),
    'A complete viewer session is authenticated.'
);
$assert(
    electroplan_is_authenticated(['user_id' => 7, 'username' => 'Upper Tech', 'role' => 'Technician']),
    'Accepted roles are case-insensitive.'
);
$assert(
    !electroplan_is_authenticated([]),
    'An empty session is anonymous.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 4, 'username' => 'Central User']),
    'A partial session missing role fails closed.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 4, 'role' => 'admin']),
    'A partial session missing username fails closed.'
);
$assert(
    !electroplan_is_authenticated(['username' => 'Central User', 'role' => 'admin']),
    'A partial session missing user_id fails closed.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 4, 'username' => 'Central User', 'role' => 'viwer']),
    'Legacy viwer typo fails closed in session authentication.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 4, 'username' => 'Central User', 'role' => 'superadmin']),
    'Unknown roles fail closed in session authentication.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 4, 'username' => 'Central User', 'role' => '']),
    'Empty role fails closed in session authentication.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 0, 'username' => 'Zero User', 'role' => 'admin']),
    'Non-positive user_id 0 fails closed.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => -1, 'username' => 'Neg User', 'role' => 'admin']),
    'Negative user_id fails closed.'
);
$assert(
    !electroplan_is_authenticated(['user_id' => 'abc', 'username' => 'Invalid User', 'role' => 'admin']),
    'Non-numeric user_id fails closed.'
);

// ---------------------------------------------------------------------------
// 3. API-versus-Page Classification Decisions
// ---------------------------------------------------------------------------
$assert(
    electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/api/api.php']),
    'Executing script /electroplan/api/api.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['SCRIPT_NAME' => '/api/search.php']),
    'Executing script /api/search.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['PHP_SELF' => '/api/create_project.php']),
    'PHP_SELF under /api/create_project.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['SCRIPT_FILENAME' => 'C:\\inetpub\\wwwroot\\api\\create_project.php']),
    'Windows SCRIPT_FILENAME api\\create_project.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/task_manager/api.php']),
    'Executing script /electroplan/task_manager/api.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['SCRIPT_FILENAME' => 'C:\\inetpub\\wwwroot\\task_manager\\api.php']),
    'Windows task_manager\\api.php is classified as API.'
);
$assert(
    electroplan_is_api_request(['SCRIPT_NAME' => 'task_manager/api.php']),
    'Relative task_manager/api.php is classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_FILENAME' => 'C:\\inetpub\\wwwroot\\api\\upload.php']),
    'Windows SCRIPT_FILENAME api\\upload.php is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/api/upload.php']),
    'api/upload.php is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/api/v1/index.php']),
    'Non-allowlisted api/v1 script is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/pages/index.php']),
    'Dashboard page script is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/pages/sso-login.php']),
    'SSO login page script is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/index.php']),
    'Application root entry script is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/task_manager/index.php']),
    'Non-API script under task_manager is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/pages/api_view.php']),
    'Page script with "api" in file name is not classified as API.'
);
$assert(
    !electroplan_is_api_request(['SCRIPT_NAME' => '/electroplan/pages/index.php', 'QUERY_STRING' => 'api=1']),
    'Classification relies only on server script metadata, ignoring query strings.'
);

// ---------------------------------------------------------------------------
// 4. Runtime Guard Execution: Page Redirect vs API HTTP 401 JSON
// ---------------------------------------------------------------------------

// 4.1 Anonymous browser page redirects to fixed SSO login before HTML
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$pageGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/pages/index.php']);
$assert(
    $pageGuard === false,
    'Anonymous browser page fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Anonymous browser page triggers redirect to /electroplan/pages/sso-login.php.'
);
$assert(
    empty($GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__']),
    'Anonymous browser page never triggers API unauthorized response.'
);

// 4.2 Anonymous browser page api/upload.php redirects to fixed SSO login before HTML
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$uploadGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/api/upload.php']);
$assert(
    $uploadGuard === false,
    'Anonymous api/upload.php request fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Anonymous api/upload.php request redirects to /electroplan/pages/sso-login.php.'
);
$assert(
    empty($GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__']),
    'Anonymous api/upload.php request never triggers API unauthorized response.'
);

// 4.3 Anonymous API request under api/api.php returns HTTP 401 with JSON and no Location redirect
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] = null;
ob_start();
$apiGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/api/api.php']);
$apiOutput = ob_get_clean();
$apiJson = json_decode((string)$apiOutput, true);

$assert(
    $apiGuard === false,
    'Anonymous api/api.php request fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] === true,
    'Anonymous api/api.php request marks API unauthorized.'
);
$assert(
    ($GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] ?? null) === 401,
    'Anonymous api/api.php request sets HTTP 401 status code.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Anonymous api/api.php request never produces a Location redirect.'
);
$assert(
    is_array($apiJson) && ($apiJson['status'] ?? null) === 'error',
    'Anonymous api/api.php request returns sanitized application/json error payload.'
);

// 4.4 Anonymous API request under api/create_project.php returns HTTP 401 with JSON and no Location redirect
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] = null;
ob_start();
$createProjectGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/api/create_project.php']);
$createProjectOutput = ob_get_clean();
$createProjectJson = json_decode((string)$createProjectOutput, true);

$assert(
    $createProjectGuard === false,
    'Anonymous api/create_project.php request fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] === true,
    'Anonymous api/create_project.php marks API unauthorized.'
);
$assert(
    ($GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] ?? null) === 401,
    'Anonymous api/create_project.php sets HTTP 401 status code.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Anonymous api/create_project.php never produces a Location redirect.'
);
$assert(
    is_array($createProjectJson) && ($createProjectJson['status'] ?? null) === 'error',
    'Anonymous api/create_project.php returns sanitized application/json error payload.'
);

// 4.5 Anonymous API request for task_manager/api.php returns HTTP 401 with JSON and no Location redirect
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] = null;
ob_start();
$tmGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/task_manager/api.php']);
$tmOutput = ob_get_clean();
$tmJson = json_decode((string)$tmOutput, true);

$assert(
    $tmGuard === false,
    'Anonymous task_manager/api.php request fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] === true,
    'Anonymous task_manager/api.php marks API unauthorized.'
);
$assert(
    ($GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] ?? null) === 401,
    'Anonymous task_manager/api.php sets HTTP 401 status code.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Anonymous task_manager/api.php never produces a Location redirect.'
);
$assert(
    is_array($tmJson) && ($tmJson['status'] ?? null) === 'error',
    'Anonymous task_manager/api.php returns sanitized application/json error payload.'
);

// 4.6 Anonymous API request for api/search.php returns HTTP 401 with JSON and no Location redirect
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] = null;
ob_start();
$searchGuard = electroplan_enforce_session([], ['SCRIPT_NAME' => '/electroplan/api/search.php']);
$searchOutput = ob_get_clean();
$searchJson = json_decode((string)$searchOutput, true);

$assert(
    $searchGuard === false,
    'Anonymous api/search.php request fails authentication in central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] === true,
    'Anonymous api/search.php marks API unauthorized.'
);
$assert(
    ($GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] ?? null) === 401,
    'Anonymous api/search.php sets HTTP 401 status code.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Anonymous api/search.php never produces a Location redirect.'
);
$assert(
    is_array($searchJson) && ($searchJson['status'] ?? null) === 'error',
    'Anonymous api/search.php returns sanitized application/json error payload.'
);

// 4.7 Legacy viwer typo fails closed in both page and API contexts
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
ob_start();
$viwerApiGuard = electroplan_enforce_session(
    ['user_id' => 8, 'username' => 'Typo User', 'role' => 'viwer'],
    ['SCRIPT_NAME' => '/electroplan/api/api.php']
);
$viwerApiOutput = ob_get_clean();
$assert(
    $viwerApiGuard === false,
    'Session with legacy viwer typo fails authentication in API guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] === true,
    'Legacy viwer typo on API script returns HTTP 401 unauthorized.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Legacy viwer typo on API script never produces a Location redirect.'
);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$viwerUploadGuard = electroplan_enforce_session(
    ['user_id' => 8, 'username' => 'Typo User', 'role' => 'viwer'],
    ['SCRIPT_NAME' => '/electroplan/api/upload.php']
);
$assert(
    $viwerUploadGuard === false,
    'Session with legacy viwer typo fails authentication on upload.php.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Legacy viwer typo on upload.php redirects to SSO login.'
);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$viwerPageGuard = electroplan_enforce_session(
    ['user_id' => 8, 'username' => 'Typo User', 'role' => 'viwer'],
    ['SCRIPT_NAME' => '/electroplan/pages/index.php']
);
$assert(
    $viwerPageGuard === false,
    'Session with legacy viwer typo fails authentication in page guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Legacy viwer typo on page script redirects to SSO login.'
);

// 4.8 Authenticated session passes central guard without redirect or 401
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = null;
$_SESSION = ['user_id' => 12, 'username' => 'Valid Tech', 'role' => 'technician'];
$validGuard = electroplan_enforce_session($_SESSION, ['SCRIPT_NAME' => '/electroplan/api/api.php']);
$assert(
    $validGuard === true,
    'Complete technician session passes central guard.'
);
$assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Authenticated session produces no redirect.'
);
$assert(
    empty($GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__']),
    'Authenticated session produces no 401 response.'
);
$assert(
    $_SESSION['role'] === 'technician',
    'Authenticated session role is preserved and normalized.'
);

// ---------------------------------------------------------------------------
// 5. Cleanup & Exit
// ---------------------------------------------------------------------------
if ($sessionBackup === null) {
    unset($_SESSION);
} else {
    $_SESSION = $sessionBackup;
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Electroplan automatic SSO entry contract: PASS' . PHP_EOL;
