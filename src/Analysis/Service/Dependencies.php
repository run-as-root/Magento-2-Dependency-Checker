<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Service;

use RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies\Result;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner\DependenciesScannerInterface;
use RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner\PhpFiles;
use RunAsRoot\IntegrityChecker\Exception\FileNotFoundException;
use RunAsRoot\IntegrityChecker\Domain\PackagesRegistry;
use RunAsRoot\IntegrityChecker\Domain\Package;

class Dependencies implements AnalyzerInterface
{
    private const MAGENTO_MODULE_PACKAGE_TYPE = 'magento2-module';

    /**
     * @var DependenciesScannerInterface[]
     */
    private array $dependenciesScanner = [];

    private PackagesRegistry $packagesRegistry;

    public function __construct()
    {
        $this->packagesRegistry = PackagesRegistry::getInstance();
        $this->dependenciesScanner = [
            new PhpFiles()
        ];
    }

    /**
     * Analyze PackagesProvider dependencies and compare between declared dependencies and actually used.
     *
     * @param iterable $packages
     *
     * @return \Generator
     */
    public function analyse(iterable $packages): \Generator
    {
        foreach ($packages as $package) {
            $dependencies = [];
            foreach ($this->dependenciesScanner as $scanner) {
                $dependencies[] = $scanner->lookupDependencies($package);
            }
            $dependencies = array_merge([], ...$dependencies);
            yield $this->compareDependencies($package, $dependencies);
        }
    }

    /**
     * Compare package dependencies with discovered dependencies.
     *
     * @param Package $package
     * @param array $dependencies
     *
     * @return Result
     */
    private function compareDependencies(Package $package, array $dependencies): Result
    {
        return new Result(
            $package->getPackageName(),
            $this->compareComposerDependencies($package, $dependencies),
            $this->compareModuleXmlDependencies($package, $dependencies)
        );
    }

    /**
     * Compare found dependencies with dependencies in module.xml.
     *
     * @param Package $package
     * @param array $dependencies
     *
     * @return array
     */
    private function compareModuleXmlDependencies(Package $package, array $dependencies): array
    {
        if ($package->getPackageType() !== self::MAGENTO_MODULE_PACKAGE_TYPE) {
            return [];
        }

        try {
            // Convert Magento\ZZZ -> Magento_ZZZ
            $declaredModuleXml = array_map(
                fn(string $moduleName) => str_replace('_', '\\', $moduleName),
                $package->getModuleXmlDependencies()
            );
        } catch (FileNotFoundException $exception) {
            $declaredModuleXml = [];
        }

        // leave only Magento 2 modules
        $dependenciesModules = array_filter($dependencies,
            fn(string $namespace) => $this->packagesRegistry->getPackageType(
                    (string)$this->packagesRegistry->getPackageNameByNamespace($namespace)
                ) === self::MAGENTO_MODULE_PACKAGE_TYPE
        );

        return array_diff($dependenciesModules, $declaredModuleXml);
    }

    /**
     * Compare found dependencies with dependencies in composer.json.
     *
     * @param Package $package
     * @param array $dependencies
     *
     * @return array
     */
    private function compareComposerDependencies(Package $package, array $dependencies): array
    {
        $dependenciesPackages = array_filter(
            array_map(
                fn(string $namespace) => $this->packagesRegistry->getPackageNameByNamespace($namespace),
                $dependencies
            )
        );

        try {
            $composerDeps = $package->getComposerDependencies();
        } catch (FileNotFoundException $exception) {
            $composerDeps = [];
        }

        return array_diff($dependenciesPackages, $composerDeps);
    }
}
