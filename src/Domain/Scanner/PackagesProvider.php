<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Domain\Scanner;

use RunAsRoot\IntegrityChecker\Domain\Package;

class PackagesProvider
{
    /**
     * Get files according to paths.
     *
     * @param array $paths
     * @param string $fileMask
     * @param callable|null $filter
     *
     * @return \Generator
     */
    public function getPackages(array $paths, ?callable $filter = null, string $fileMask = '/composer\\.json/'): \Generator
    {
        $collectedPaths = [];
        foreach ($paths as $path) {
            $collectedPaths[] = $this->getMatchedFilesFolders(ROOT_DIR . $path, $fileMask, $filter);
        }

        $uniquePackages = array_unique(array_merge([], ...$collectedPaths));

        foreach ($uniquePackages as $packagePath) {
            yield new Package($packagePath);
        }
    }

    /**
     * Lookup and get directory path to composer.json files.
     *
     * @param string $path
     * @param string $fileMask
     * @param callable|null $filter
     *
     * @return array
     */
    private function getMatchedFilesFolders(string $path, string $fileMask, ?callable $filter): array
    {
        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $matchedFiles = new \RegexIterator($allFiles, $fileMask);

        $matchedFiles = iterator_to_array($matchedFiles);

        if ($filter !== null) {
            $matchedFiles = array_filter($matchedFiles, $filter);
        }

        return array_map(fn(\SplFileInfo $file) => $file->getPath(), $matchedFiles);
    }
}
