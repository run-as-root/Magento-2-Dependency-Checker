<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Domain;

class PackagesRegistry
{
    private array $packages = [];

    private array $packagesTypes = [];

    private array $packagesAliases = [];

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

        // Like PSR-4 autoloading, the most specific prefix wins: a package mapping a broad prefix
        // such as "Magento\\" must not shadow the packages owning "Magento\\Framework\\" etc.
        for ($i = count($parts); $i >= 1; $i--) {
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

    /**
     * Provide every package name the package satisfies in a "require" section: its own name plus
     * the names it replaces or provides (e.g. mage-os/framework replaces magento/framework).
     *
     * @return string[]
     */
    public function getPackageAliases(string $packageName): array
    {
        return $this->packagesAliases[$packageName] ?? [$packageName];
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

        $this->registerPackages($json['packages'] ?? []);
    }

    private function registerPackages(array $packages): void
    {
        foreach ($packages as $package) {
            if (!isset($package['autoload']['psr-4'])) {
                continue;
            }

            $this->packagesTypes[$package['name']] = $package['type'] ?? 'unknown';
            $this->packagesAliases[$package['name']] = array_merge(
                [$package['name']],
                array_keys($package['replace'] ?? []),
                array_keys($package['provide'] ?? [])
            );

            foreach ($package['autoload']['psr-4'] as $namespace => $paths) {
                // Magento's generated/ folder is runtime output, not package code; a package that maps
                // a namespace there (e.g. "Magento\\" => "generated/code/Magento/") does not own it.
                if ($this->isGeneratedCodePath($paths)) {
                    continue;
                }

                $this->packages[trim($namespace, '\\')] = $package['name'];
            }
        }
    }

    private function isGeneratedCodePath(string|array $paths): bool
    {
        foreach ((array)$paths as $path) {
            if (!str_starts_with(ltrim($path, './'), 'generated/')) {
                return false;
            }
        }

        return true;
    }
}
