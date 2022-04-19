<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Application\Structure;

use RunAsRoot\IntegrityChecker\Application\ConsoleInterface;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;
use RunAsRoot\IntegrityChecker\Application\Registry\DefectsState;

class Console implements ConsoleInterface
{
    private DefectsState $defectsState;

    public function __construct()
    {
        $this->defectsState = new DefectsState();
    }

    public function printOutput(ResultInterface $result): void
    {
        $this->defectsState->registerResult($result);

        if (!$result->hasDefects()) {
            return;
        }

        echo PHP_EOL;
        echo sprintf("Package \"%s\" has incorrect structure.\nMissed folders/files:", $result->getPackageName());

        $this->printTree($result->getDefects());
        echo PHP_EOL;
    }

    /**
     * Recursively print the tree.
     *
     * @param array $tree
     * @param int $tabs
     */
    private function printTree(array $tree, int $tabs = 1): void
    {
        foreach ($tree as $name => $stem) {
            echo PHP_EOL;
            echo str_repeat("\t", $tabs);

            if (is_array($stem)) {
                echo "- {$name}";
                $this->printTree($stem, $tabs + 1);
            } else {
                echo "- {$stem}";
            }
        }
    }

    public function getStatusCode(): int
    {
        return $this->defectsState->hasDefects() ? 1 : 0;
    }

    public function validateParameters(): bool
    {
        $argc = $_SERVER['argc'];
        $argv = $_SERVER['argv'];

        if ($argc < 2) {
            echo "\e[31mExpected first parameter as Magento 2 Root Directory.\e[30m" . PHP_EOL;
            return false;
        }

        if (!is_file($argv[1] . DIRECTORY_SEPARATOR . 'composer.lock')) {
            echo "\e[31m\"composer.lock\" file was not found in Magento 2 Directory.\e[30m" . PHP_EOL;
            return false;
        }

        $result = true;
        for ($i = 2; $i < $argc; $i++) {
            if (!is_dir(ROOT_DIR . $argv[$i])) {
                echo  sprintf(
                        "\e[31mCan not find directory \"%s\". Please check your input parameters.\e[30m",
                        ROOT_DIR . $argv[$i]
                    ) . PHP_EOL
                    . sprintf("Path \"%s\" should be relative to Magento 2 Directory.\e[30m", $argv[$i])
                    . PHP_EOL;
                $result = false;
            }
        }

        return $result;
    }

    public function printHelp(): void
    {
        echo "\e[32mHelp\e[30m" . PHP_EOL;
        echo 'Tool to check if modules are follow to standard module structure. Usage:' . PHP_EOL;
        echo 'php bin/structure [Magento2 root] {folder1} {folder2}' . PHP_EOL;
        echo '[Magento2 root] - path to Magento 2 project root directory.' . PHP_EOL;
        echo '{folder1} {folder2} - list of relative folders to scan, separated by space. ';
        echo 'If not provided, scan will be run for "src" and "app".' . PHP_EOL;
    }
}
