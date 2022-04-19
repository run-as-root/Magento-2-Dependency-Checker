<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner;

use RunAsRoot\IntegrityChecker\Domain\PackagesRegistry;
use RunAsRoot\IntegrityChecker\Domain\Package;

class PhpFiles implements DependenciesScannerInterface
{
    private const FILE_MASKS = ['php', 'phtml'];

    private string $regexp;

    public function __construct()
    {
        $namespaces = PackagesRegistry::getInstance()->getAllProjectNamespaces();
        $availableVendors = [];

        foreach ($namespaces as $namespace) {
            $availableVendors[] = explode('\\', $namespace)[0];
        }

        /**
         * Regular expression to lookup classes and namespaces inside of module php/phtml files.
         * Vendor names are taken from PackagesProvider Registry to limit number of outputs variations which could be returned.
         * Reg.exp. for project with only Magneto namespace: '~(\B[\\\\]|[^\\\\]\b)((Magento([_\\]))[a-zA-Z0-9]{2,})~';
         * Expected to match in next strings:
         * Magento\Zzz in cases:
         * use \Magento\Zzz\Module\Some\Class;
         * use Magento\Zzz\Module\Some\Class;
         * $a = \Magento\Zzz\Module\Some\Class::class;
         * use \Magento\Zzz\Rewrite\Magento\Catalog\Something;
         * $b = Magento\Zzz\Rewrite\Magento\Catalog\Something::class; (in case if file does not have namespace);
         */
        $this->regexp = '~(\B[\\\\]|[^\\\\]\b)(?<module>(' .
            implode('[_\\\\]|', array_unique($availableVendors)) .
            '[_\\\\])[a-zA-Z0-9]{2,})~';
    }

    /**
     * Search for dependencies inside the module directory.
     * Scan *.php and *.phtml files for PHP classes with regexp and collect corresponding modules which are required
     * by the package to work properly.
     *
     * @param Package $package
     *
     * @return string[] - list of packages founded as dependencies inside package's files.
     */
    public function lookupDependencies(Package $package): array
    {
        $collectedDependencies = [];

        foreach ($package->getPackageFiles() as $file) {
            if (\in_array($file->getFileInfo()->getExtension(), self::FILE_MASKS)) {
                $collectedDependencies[] = $this->analyzeFile($file, $package->getPackageNamespaces());
            }
        }

        return array_unique(array_merge([], ...$collectedDependencies));
    }

    /**
     * Get list of required packages dependencies from php file.
     *
     * @param \SplFileInfo $file
     * @param string[] $currentModuleNamespaces
     *
     * @return string[] - list of packages mentioned inside the file.
     */
    private function analyzeFile(\SplFileInfo $file, array $currentModuleNamespaces): array
    {
        $contents = \php_strip_whitespace($file->getPathname());

        if ($file->getExtension() === 'phtml') {
            $contents = $this->stripeHtml($contents);
        }

        if (!preg_match_all($this->regexp, $contents, $matches)) {
            return [];
        }

        $matches['module'] = array_unique($matches['module']);
        $dependenciesInfo = [];

        foreach ($matches['module'] as $referenceModule) {
            $referenceModule = str_replace('_', '\\', $referenceModule);
            if (\in_array($referenceModule, $currentModuleNamespaces)) {
                continue;
            }

            $dependenciesInfo[] = $referenceModule;
        }

        return $dependenciesInfo;
    }

    /**
     * Collects php content inside of template file and return it as result.
     *
     * @param string $contents
     *
     * @return string
     */
    private function stripeHtml(string $contents): string
    {
        return (string)preg_replace_callback(
            '~(<\?(php|=)\s+.*\?>)~sU',
            function (array $matches) use ($contents, &$contentsWithoutHtml)
            {
                $contentsWithoutHtml .= $matches[1];

                return $contents;
            },
            $contents
        );
    }
}
