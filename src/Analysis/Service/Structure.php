<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Service;

use RunAsRoot\IntegrityChecker\Analysis\Data\Structure\Result;
use RunAsRoot\IntegrityChecker\Domain\Package;

class Structure implements AnalyzerInterface
{
    private array $standardStructure;

    /**
     * @param array $expectedStructure
     */
    public function __construct(array $expectedStructure = [])
    {
        $this->standardStructure = $expectedStructure;
    }

    /**
     * Analyze packages structure and compare with standard structure.
     * For analysis, build tree of package folders and files and compare two trees.
     * Example Package Tree:
     * [
     *  'registration.php',
     *  'composer.json',
     *  'src' => [
     *          'etc' => [
     *                  'module.xml',
     *                  'di.xml',
     *                  'config.xml;
     *          ],
     *          'Model' => [
     *                  'Entity.php'
     *          ]
     *  ],
     *  'README.md'
     *]
     *
     * @param iterable $packages
     *
     * @return \Generator
     */
    public function analyse(iterable $packages): \Generator
    {
        /** @var Package $package */
        foreach ($packages as $package) {
            $tree = $this->buildPackageTree($package);

            yield new Result($package->getPackageName(), $this->compareTrees($this->standardStructure, $tree));
        }
    }

    /**
     * Compare Standard Tree structure with extension one. Provide result as missed components.
     *
     * @param array $standardTree
     * @param array $packageTree
     *
     * @return array
     */
    private function compareTrees(array $standardTree, array $packageTree): array
    {
        $diff = [];
        foreach ($standardTree as $name => $standardStem) {
            if (!is_array($standardStem) && !in_array($standardStem, $packageTree)) {
                // we found a leaf - it's a file!
                $diff[] = $standardStem;
            }

            if (is_array($standardStem) && !isset($packageTree[$name])) {
                $diff[$name] = $standardStem;
            }

            if (isset($packageTree[$name]) && is_array($packageTree[$name])) {
                $result = $this->compareTrees($standardStem, $packageTree[$name]);

                if (!empty($result)) {
                    $diff[$name] = $result;
                }
            }
        }

        return $diff;
    }

    /**
     * Build tree for package, based on information about folders and files inside.
     *
     * @param Package $package
     *
     * @return array
     */
    private function buildPackageTree(Package $package): array
    {
        $packageRoot = $package->getPackagePath();
        $tree = [];

        foreach ($package->getPackageFiles() as $file) {
            if ($file->isDir()) {
                continue;
            }
            $parts = explode(
                DIRECTORY_SEPARATOR,
                str_replace($packageRoot . DIRECTORY_SEPARATOR, '', $file->getPathname())
            );

            $stem = &$tree;

            for ($i = 0; $i < count($parts); $i++) {
                $part = $parts[$i];

                if ($i + 1 === count($parts)) {
                    // We found a leaf.
                    $stem[] = $part;
                    break;
                }

                if (!isset($stem[$part])) {
                    $stem[$part] = [];
                }

                $stem = &$stem[$part];
            }
        }

        return $tree;
    }
}
