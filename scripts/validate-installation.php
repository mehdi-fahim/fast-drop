<?php

/**
 * FastDrop Installation Validation Script
 * 
 * This script validates that all components are properly installed and configured.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
if (file_exists(__DIR__ . '/../.env.local')) {
    $dotenv->load(__DIR__ . '/../.env.local');
}
$dotenv->load(__DIR__ . '/../.env');

class InstallationValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $success = [];

    public function run(): void
    {
        echo "🔍 FastDrop Installation Validation\n";
        echo "===================================\n\n";

        $this->checkPhpVersion();
        $this->checkPhpExtensions();
        $this->checkComposer();
        $this->checkSymfony();
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkStorage();
        $this->checkSecurity();
        $this->checkPermissions();
        $this->checkDependencies();

        $this->displayResults();
    }

    private function checkPhpVersion(): void
    {
        $version = PHP_VERSION;
        $required = '8.2.0';
        
        if (version_compare($version, $required, '>=')) {
            $this->success[] = "✅ PHP version: {$version} (>= {$required})";
        } else {
            $this->errors[] = "❌ PHP version: {$version} (required: >= {$required})";
        }
    }

    private function checkPhpExtensions(): void
    {
        $required = [
            'pdo' => 'Database support',
            'pdo_pgsql' => 'PostgreSQL support',
            'json' => 'JSON support',
            'mbstring' => 'Multibyte string support',
            'xml' => 'XML support',
            'zip' => 'ZIP support',
            'gd' => 'Image processing',
            'curl' => 'HTTP client',
            'intl' => 'Internationalization',
            'bcmath' => 'Arbitrary precision mathematics',
        ];

        foreach ($required as $ext => $description) {
            if (extension_loaded($ext)) {
                $this->success[] = "✅ {$description}: {$ext} extension loaded";
            } else {
                $this->errors[] = "❌ Missing {$description}: {$ext} extension not found";
            }
        }

        // Optional extensions
        $optional = [
            'redis' => 'Redis support (optional, uses Predis as fallback)',
            'imagick' => 'ImageMagick support (optional)',
        ];

        foreach ($optional as $ext => $description) {
            if (extension_loaded($ext)) {
                $this->success[] = "✅ {$description}: {$ext} extension loaded";
            } else {
                $this->warnings[] = "⚠️  {$description}: {$ext} extension not found (optional)";
            }
        }
    }

    private function checkComposer(): void
    {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            $this->success[] = "✅ Composer: Dependencies installed";
        } else {
            $this->errors[] = "❌ Composer: Dependencies not installed (run composer install)";
        }
    }

    private function checkSymfony(): void
    {
        if (file_exists(__DIR__ . '/../bin/console')) {
            $this->success[] = "✅ Symfony: Console available";
        } else {
            $this->errors[] = "❌ Symfony: Console not found";
        }
    }

    private function checkDatabase(): void
    {
        $dsn = $_ENV['DATABASE_URL'] ?? null;
        
        if (!$dsn) {
            $this->errors[] = "❌ Database: DATABASE_URL not configured";
            return;
        }

        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->success[] = "✅ Database: Connection successful";
            
            // Check if tables exist
            $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll();
            if (count($tables) > 0) {
                $this->success[] = "✅ Database: Tables found (" . count($tables) . " tables)";
            } else {
                $this->warnings[] = "⚠️  Database: No tables found (run migrations)";
            }
        } catch (PDOException $e) {
            $this->errors[] = "❌ Database: Connection failed - " . $e->getMessage();
        }
    }

    private function checkRedis(): void
    {
        $redisUrl = $_ENV['REDIS_URL'] ?? 'redis://localhost:6379';
        
        try {
            // Try Predis first (pure PHP Redis client)
            if (class_exists('Predis\Client')) {
                $redis = new Predis\Client($redisUrl);
                $redis->ping();
                $this->success[] = "✅ Redis: Connection successful (using Predis)";
                return;
            }
            
            // Fallback to native Redis extension
            if (extension_loaded('redis')) {
                $redis = new Redis();
                $parsed = parse_url($redisUrl);
                $host = $parsed['host'] ?? 'localhost';
                $port = $parsed['port'] ?? 6379;
                
                if ($redis->connect($host, $port)) {
                    $this->success[] = "✅ Redis: Connection successful (using native extension)";
                    $redis->close();
                } else {
                    $this->errors[] = "❌ Redis: Connection failed";
                }
                return;
            }
            
            $this->warnings[] = "⚠️  Redis: Neither Predis nor Redis extension available - Redis features will not work";
        } catch (Exception $e) {
            $this->warnings[] = "⚠️  Redis: " . $e->getMessage() . " (Redis features will not work)";
        }
    }

    private function checkStorage(): void
    {
        $type = $_ENV['STORAGE_TYPE'] ?? 'local';
        
        if ($type === 'local') {
            $path = $_ENV['STORAGE_LOCAL_PATH'] ?? __DIR__ . '/../var/storage';
            $path = str_replace('%kernel.project_dir%', __DIR__ . '/..', $path);
            
            if (is_dir($path) && is_writable($path)) {
                $this->success[] = "✅ Storage: Local directory writable ({$path})";
            } else {
                $this->errors[] = "❌ Storage: Local directory not writable ({$path})";
            }
        } elseif ($type === 's3') {
            $this->warnings[] = "⚠️  Storage: S3 configuration not validated (manual check required)";
        }
    }

    private function checkSecurity(): void
    {
        $appSecret = $_ENV['APP_SECRET'] ?? null;
        $hmacSecret = $_ENV['HMAC_SECRET'] ?? null;
        
        if (!$appSecret) {
            $this->errors[] = "❌ Security: APP_SECRET not configured";
        } elseif ($appSecret === 'your-secret-key-here-change-in-production') {
            $this->warnings[] = "⚠️  Security: APP_SECRET is using default value";
        } else {
            $this->success[] = "✅ Security: APP_SECRET configured";
        }
        
        if (!$hmacSecret) {
            $this->errors[] = "❌ Security: HMAC_SECRET not configured";
        } elseif ($hmacSecret === 'your-hmac-secret-key-change-in-production') {
            $this->warnings[] = "⚠️  Security: HMAC_SECRET is using default value";
        } else {
            $this->success[] = "✅ Security: HMAC_SECRET configured";
        }
    }

    private function checkPermissions(): void
    {
        $varDir = __DIR__ . '/../var';
        $cacheDir = $varDir . '/cache';
        $logDir = $varDir . '/log';
        
        if (is_writable($varDir)) {
            $this->success[] = "✅ Permissions: var/ directory writable";
        } else {
            $this->errors[] = "❌ Permissions: var/ directory not writable";
        }
        
        if (is_writable($cacheDir)) {
            $this->success[] = "✅ Permissions: var/cache/ directory writable";
        } else {
            $this->errors[] = "❌ Permissions: var/cache/ directory not writable";
        }
        
        if (is_writable($logDir)) {
            $this->success[] = "✅ Permissions: var/log/ directory writable";
        } else {
            $this->errors[] = "❌ Permissions: var/log/ directory not writable";
        }
    }

    private function checkDependencies(): void
    {
        $requiredPackages = [
            'symfony/framework-bundle',
            'doctrine/orm',
            'doctrine/doctrine-bundle',
            'league/flysystem',
            'predis/predis',
            'ramsey/uuid',
        ];
        
        $composerLock = json_decode(file_get_contents(__DIR__ . '/../composer.lock'), true);
        $installedPackages = array_column($composerLock['packages'] ?? [], 'name');
        
        foreach ($requiredPackages as $package) {
            if (in_array($package, $installedPackages)) {
                $this->success[] = "✅ Dependency: {$package} installed";
            } else {
                $this->errors[] = "❌ Dependency: {$package} not found";
            }
        }
    }

    private function displayResults(): void
    {
        echo "\n📊 Validation Results\n";
        echo "====================\n\n";
        
        if (!empty($this->success)) {
            echo "✅ Success (" . count($this->success) . ")\n";
            foreach ($this->success as $message) {
                echo "   {$message}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  Warnings (" . count($this->warnings) . ")\n";
            foreach ($this->warnings as $message) {
                echo "   {$message}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "❌ Errors (" . count($this->errors) . ")\n";
            foreach ($this->errors as $message) {
                echo "   {$message}\n";
            }
            echo "\n";
        }
        
        $totalIssues = count($this->errors) + count($this->warnings);
        
        if (count($this->errors) > 0) {
            echo "🚨 Installation has critical errors that must be fixed.\n";
            exit(1);
        } elseif (count($this->warnings) > 0) {
            echo "⚠️  Installation has warnings that should be addressed.\n";
            echo "FastDrop should work but may not be optimally configured.\n";
        } else {
            echo "🎉 Installation validation successful!\n";
            echo "FastDrop is properly configured and ready to use.\n";
        }
        
        echo "\nNext steps:\n";
        if (count($this->errors) === 0) {
            echo "1. Create an admin user: php bin/console app:create-admin-user\n";
            echo "2. Run migrations: php bin/console doctrine:migrations:migrate\n";
            echo "3. Start the server: symfony server:start\n";
            echo "4. Access the application at http://localhost:8000\n";
        } else {
            echo "1. Fix the errors listed above\n";
            echo "2. Re-run this validation script\n";
        }
    }
}

// Run validation
$validator = new InstallationValidator();
$validator->run();
