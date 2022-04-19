<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Domain;

class PackagesRegistry
{
    private array $packages = [];

    private array $packagesTypes = [];

    private static ?PackagesRegistry $instance = null;

    private function __construct()
    {
        $this->parseComposerLock();
    }

    private function __clone()
    {
    }

    /**
     * Provide singleton instance of PackagesProvider Registry.
     *
     * @return PackagesRegistry
     */
    public static function getInstance(): PackagesRegistry
    {
        if (!self::$instance) {
            self::$instance = new PackagesRegistry();
        }

        return self::$instance;
    }

    /**
     * Provide Package Name by Module Namespace.
     *
     * @param string $namespace
     *
     * @return string|null
     */
    public function getPackageNameByNamespace(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);

        for ($i = 1; $i <= count($parts); $i++) {
            $namespace = implode('\\', array_slice($parts, 0, $i));
            if (isset($this->packages[$namespace])) {
                return $this->packages[$namespace];
            }
        }

        return null;
    }

    public function getPackageType(string $packageName): string
    {
        return $this->packagesTypes[$packageName] ?? 'unknown';
    }

    public function getAllProjectNamespaces(): array
    {
        return array_keys($this->packages);
    }

    /**
     * Parse composer.lock file to discover information about packages on the project.
     */
    private function parseComposerLock(): void
    {
        $lockFile = ROOT_DIR . 'composer.lock';
        if (!is_file($lockFile)) {
            return;
        }

        $json = file_get_contents($lockFile);
        $json = json_decode($json, true);

        foreach ($json['packages'] as $package) {
            if (!isset($package['autoload']['psr-4'])) {
                continue;
            }

            $this->packagesTypes[$package['name']] = $package['type'] ?? 'unknown';

            $namespaces = array_keys($package['autoload']['psr-4']);
            foreach ($namespaces as $namespace) {
                $this->packages[trim($namespace, '\\')] = $package['name'];
            }
        }
    }
}
