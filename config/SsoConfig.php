<?php
declare(strict_types=1);

/**
 * Electroplan Single Sign-On (SSO) Configuration Loader.
 *
 * Precedence:
 * 1. Environment variables (getenv, $_ENV, $_SERVER)
 * 2. Explicit private PHP configuration file outside repository and document root
 * 3. Fail-closed defaults with sanitized error messages (SSO_ENABLED=false)
 */
class SsoConfig
{
    /**
     * Immutable default SSO state (fail-closed).
     */
    public const DEFAULT_ENABLED = false;
    public const DEFAULT_SSO_ENABLED = false;

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $cachedConfig = null;

    /**
     * Loads and validates the SSO configuration.
     *
     * @return array{
     *     enabled: bool,
     *     hub_base_url: string,
     *     client_id: string,
     *     client_secret: string,
     *     redirect_uri: string,
     *     timeout: int,
     *     authorize_url: string,
     *     token_url: string,
     *     userinfo_url: string,
     *     scopes: list<string>,
     *     SSO_ENABLED: bool,
     *     SSO_HUB_BASE_URL: string,
     *     SSO_CLIENT_ID: string,
     *     SSO_CLIENT_SECRET: string,
     *     SSO_REDIRECT_URI: string,
     *     SSO_TIMEOUT: int
     * }
     * @throws \RuntimeException on missing, unreadable, malformed, or invalid configuration.
     */
    public static function load(): array
    {
        if (self::$cachedConfig !== null) {
            return self::$cachedConfig;
        }

        $isPrivateConfigDefined = (getenv('ELECTROPLAN_SSO_PRIVATE_CONFIG') !== false)
            || (getenv('SSO_PRIVATE_CONFIG') !== false)
            || isset($_ENV['ELECTROPLAN_SSO_PRIVATE_CONFIG'])
            || isset($_ENV['SSO_PRIVATE_CONFIG'])
            || isset($_SERVER['ELECTROPLAN_SSO_PRIVATE_CONFIG'])
            || isset($_SERVER['SSO_PRIVATE_CONFIG']);

        $privateConfig = [];

        if ($isPrivateConfigDefined) {
            $privateConfigPath = self::getEnvValue('ELECTROPLAN_SSO_PRIVATE_CONFIG')
                ?? self::getEnvValue('SSO_PRIVATE_CONFIG');

            if ($privateConfigPath === null || $privateConfigPath === '' || !file_exists($privateConfigPath) || !is_file($privateConfigPath) || !is_readable($privateConfigPath)) {
                throw new \RuntimeException('SSO configuration error: configuration file not found or unreadable');
            }

            if (self::isDisallowedLocation($privateConfigPath)) {
                throw new \RuntimeException('SSO configuration error: configuration file in disallowed location');
            }

            try {
                $loaded = (static function (string $filePath) {
                    return require $filePath;
                })($privateConfigPath);
            } catch (\Throwable $e) {
                throw new \RuntimeException('SSO configuration error: invalid configuration file format');
            }

            if (!is_array($loaded)) {
                throw new \RuntimeException('SSO configuration error: invalid configuration file format');
            }

            $privateConfig = $loaded;
        }

        // Determine enabled flag with default to false (fail-closed)
        $enabledRaw = self::getEnvValue('ELECTROPLAN_SSO_ENABLED')
            ?? self::getEnvValue('SSO_ENABLED')
            ?? self::getRawFileValue($privateConfig, ['ELECTROPLAN_SSO_ENABLED', 'SSO_ENABLED', 'enabled']);

        $enabled = self::DEFAULT_ENABLED;
        if ($enabledRaw !== null) {
            if (is_bool($enabledRaw)) {
                $enabled = $enabledRaw;
            } else {
                $lower = strtolower(trim((string)$enabledRaw));
                $enabled = in_array($lower, ['1', 'true', 'yes', 'on'], true);
            }
        }

        $hubBaseUrl = self::getEnvValue('ELECTROPLAN_SSO_HUB_BASE_URL')
            ?? self::getEnvValue('SSO_HUB_BASE_URL')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_HUB_BASE_URL', 'SSO_HUB_BASE_URL', 'hub_base_url', 'base_url']);

        $clientId = self::getEnvValue('ELECTROPLAN_SSO_CLIENT_ID')
            ?? self::getEnvValue('SSO_CLIENT_ID')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_CLIENT_ID', 'SSO_CLIENT_ID', 'client_id']);

        $clientSecret = self::getEnvValue('ELECTROPLAN_SSO_CLIENT_SECRET')
            ?? self::getEnvValue('SSO_CLIENT_SECRET')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_CLIENT_SECRET', 'SSO_CLIENT_SECRET', 'client_secret']);

        $redirectUri = self::getEnvValue('ELECTROPLAN_SSO_REDIRECT_URI')
            ?? self::getEnvValue('SSO_REDIRECT_URI')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_REDIRECT_URI', 'SSO_REDIRECT_URI', 'redirect_uri']);

        $timeoutRaw = self::getEnvValue('ELECTROPLAN_SSO_TIMEOUT')
            ?? self::getEnvValue('SSO_TIMEOUT')
            ?? self::getRawFileValue($privateConfig, ['ELECTROPLAN_SSO_TIMEOUT', 'SSO_TIMEOUT', 'timeout']);

        // Check complete absence: no private config file defined and all configuration variables missing
        if (!$isPrivateConfigDefined && $hubBaseUrl === null && $clientId === null && $clientSecret === null && $redirectUri === null) {
            throw new \RuntimeException('SSO configuration error: configuration is missing');
        }

        // Validate hub base URL (must be valid HTTPS URL)
        if ($hubBaseUrl === null || trim($hubBaseUrl) === '') {
            throw new \RuntimeException('SSO configuration error: hub base URL is required');
        }
        $hubBaseUrl = rtrim(trim($hubBaseUrl), '/');
        if (!self::isHttpsUrl($hubBaseUrl)) {
            throw new \RuntimeException('SSO configuration error: hub base URL must be a valid HTTPS URL');
        }

        // Validate client ID
        if ($clientId === null || trim($clientId) === '') {
            throw new \RuntimeException('SSO configuration error: client ID is required');
        }
        $clientId = trim($clientId);

        // Validate client secret
        if ($clientSecret === null || trim($clientSecret) === '') {
            throw new \RuntimeException('SSO configuration error: client secret is required');
        }
        $clientSecret = trim($clientSecret);

        // Validate redirect URI (must be a valid HTTPS URL)
        if ($redirectUri === null || trim($redirectUri) === '') {
            throw new \RuntimeException('SSO configuration error: redirect URI is required');
        }
        $redirectUri = trim($redirectUri);
        if (!self::isHttpsUrl($redirectUri)) {
            throw new \RuntimeException('SSO configuration error: redirect URI must be a valid HTTPS URL');
        }

        // Validate timeout
        $timeout = 10;
        if ($timeoutRaw !== null) {
            if (!is_numeric($timeoutRaw) || (int)$timeoutRaw <= 0) {
                throw new \RuntimeException('SSO configuration error: timeout must be a positive integer');
            }
            $timeout = (int)$timeoutRaw;
        }

        // Endpoints derived or overridden (must all be valid HTTPS URLs)
        $authorizeUrl = self::getEnvValue('ELECTROPLAN_SSO_AUTHORIZE_URL')
            ?? self::getEnvValue('SSO_AUTHORIZE_URL')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_AUTHORIZE_URL', 'SSO_AUTHORIZE_URL', 'authorize_url', 'urlAuthorize'])
            ?? ($hubBaseUrl . '/oauth/authorize');

        $authorizeUrl = trim($authorizeUrl);
        if (!self::isHttpsUrl($authorizeUrl)) {
            throw new \RuntimeException('SSO configuration error: authorize URL must be a valid HTTPS URL');
        }

        $tokenUrl = self::getEnvValue('ELECTROPLAN_SSO_TOKEN_URL')
            ?? self::getEnvValue('SSO_TOKEN_URL')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_TOKEN_URL', 'SSO_TOKEN_URL', 'token_url', 'urlAccessToken'])
            ?? ($hubBaseUrl . '/oauth/token');

        $tokenUrl = trim($tokenUrl);
        if (!self::isHttpsUrl($tokenUrl)) {
            throw new \RuntimeException('SSO configuration error: token URL must be a valid HTTPS URL');
        }

        $userinfoUrl = self::getEnvValue('ELECTROPLAN_SSO_USERINFO_URL')
            ?? self::getEnvValue('SSO_USERINFO_URL')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_SSO_USERINFO_URL', 'SSO_USERINFO_URL', 'userinfo_url', 'urlResourceOwnerDetails'])
            ?? ($hubBaseUrl . '/oauth/userinfo');

        $userinfoUrl = trim($userinfoUrl);
        if (!self::isHttpsUrl($userinfoUrl)) {
            throw new \RuntimeException('SSO configuration error: userinfo URL must be a valid HTTPS URL');
        }

        $scopesRaw = self::getEnvValue('ELECTROPLAN_SSO_SCOPES')
            ?? self::getEnvValue('SSO_SCOPES')
            ?? self::getRawFileValue($privateConfig, ['ELECTROPLAN_SSO_SCOPES', 'SSO_SCOPES', 'scopes']);

        $scopes = ['profile', 'email'];
        if ($scopesRaw !== null) {
            if (is_array($scopesRaw)) {
                $scopes = array_values(array_filter($scopesRaw, 'is_string'));
            } elseif (is_string($scopesRaw) && trim($scopesRaw) !== '') {
                $scopes = preg_split('/[\s,]+/', trim($scopesRaw)) ?: ['profile', 'email'];
            }
        }

        $config = [
            'enabled' => $enabled,
            'hub_base_url' => $hubBaseUrl,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'timeout' => $timeout,
            'authorize_url' => $authorizeUrl,
            'token_url' => $tokenUrl,
            'userinfo_url' => $userinfoUrl,
            'scopes' => $scopes,
            'urlAuthorize' => $authorizeUrl,
            'urlAccessToken' => $tokenUrl,
            'urlResourceOwnerDetails' => $userinfoUrl,
            'SSO_ENABLED' => $enabled,
            'SSO_HUB_BASE_URL' => $hubBaseUrl,
            'SSO_CLIENT_ID' => $clientId,
            'SSO_CLIENT_SECRET' => $clientSecret,
            'SSO_REDIRECT_URI' => $redirectUri,
            'SSO_TIMEOUT' => $timeout,
        ];

        self::$cachedConfig = $config;
        return $config;
    }

    /**
     * Checks whether SSO authentication is currently enabled.
     */
    public static function isEnabled(): bool
    {
        $val = self::getEnvValue('ELECTROPLAN_SSO_ENABLED')
            ?? self::getEnvValue('SSO_ENABLED');

        if ($val !== null) {
            $lower = strtolower(trim((string)$val));
            return in_array($lower, ['1', 'true', 'yes', 'on'], true);
        }

        try {
            $config = self::load();
            return (bool)($config['enabled'] ?? self::DEFAULT_ENABLED);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resets cached configuration state.
     */
    public static function reset(): void
    {
        self::$cachedConfig = null;
    }

    /**
     * Extracts a scalar environment variable value.
     */
    private static function getEnvValue(string $key): ?string
    {
        $val = getenv($key);
        if ($val !== false) {
            $val = trim((string)$val);
            if ($val !== '') {
                return $val;
            }
        }
        if (isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
            $val = trim((string)$_ENV[$key]);
            if ($val !== '') {
                return $val;
            }
        }
        if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
            $val = trim((string)$_SERVER[$key]);
            if ($val !== '') {
                return $val;
            }
        }
        return null;
    }

    /**
     * Extracts a scalar value from a config array using candidate keys.
     *
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    private static function getFileValue(array $config, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $config) && is_scalar($config[$k])) {
                $val = trim((string)$config[$k]);
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * Extracts raw value from a config array using candidate keys.
     *
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    private static function getRawFileValue(array $config, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $config)) {
                return $config[$k];
            }
        }
        return null;
    }

    /**
     * Checks if a configuration file path resides inside repository or document root.
     */
    private static function isDisallowedLocation(string $filePath): bool
    {
        $realFilePath = realpath($filePath);
        if ($realFilePath === false) {
            return false;
        }

        $roots = [];

        // Repository root (config/ is 1 level under repo root)
        $repoRoot = realpath(dirname(__DIR__));
        if ($repoRoot !== false) {
            $roots[] = $repoRoot;
        }

        // Document root from $_SERVER or getenv, if determinable
        $docRootRaw = null;
        if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && trim($_SERVER['DOCUMENT_ROOT']) !== '') {
            $docRootRaw = trim($_SERVER['DOCUMENT_ROOT']);
        } elseif (getenv('DOCUMENT_ROOT') !== false) {
            $envDocRoot = trim((string)getenv('DOCUMENT_ROOT'));
            if ($envDocRoot !== '') {
                $docRootRaw = $envDocRoot;
            }
        }

        if ($docRootRaw !== null) {
            $docRoot = realpath($docRootRaw);
            if ($docRoot !== false) {
                $roots[] = $docRoot;
            }
        }

        $pathsToCheck = [$realFilePath];
        $dir = dirname($filePath);
        $realDir = realpath($dir);
        if ($realDir !== false) {
            $pathsToCheck[] = $realDir . DIRECTORY_SEPARATOR . basename($filePath);
        }

        foreach ($roots as $root) {
            foreach ($pathsToCheck as $checkPath) {
                if (self::isPathInside($checkPath, $root)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if a path is located inside a root directory.
     */
    private static function isPathInside(string $path, string $root): bool
    {
        $normPath = str_replace('\\', '/', $path);
        $normRoot = rtrim(str_replace('\\', '/', $root), '/');

        if (DIRECTORY_SEPARATOR === '\\' || PHP_OS_FAMILY === 'Windows') {
            $normPath = strtolower($normPath);
            $normRoot = strtolower($normRoot);
        }

        if ($normPath === $normRoot) {
            return true;
        }

        return str_starts_with($normPath, $normRoot . '/');
    }

    /**
     * Validates whether a URL is a valid absolute HTTPS URL.
     */
    private static function isHttpsUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return false;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if (!is_string($scheme) || strtolower($scheme) !== 'https') {
            return false;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        return is_string($host) && trim($host) !== '';
    }
}

if (!class_exists('Electroplan\\Config\\SsoConfig', false)) {
    class_alias(\SsoConfig::class, 'Electroplan\\Config\\SsoConfig');
}
