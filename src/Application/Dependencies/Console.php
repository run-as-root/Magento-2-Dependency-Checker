<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Application\Dependencies;

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

    /**
     * Print result message for package.
     *
     * @param ResultInterface $result
     */
    public function printOutput(ResultInterface $result): void
    {
        $this->defectsState->registerResult($result);

        if (!$result->hasDefects()) {
            return;
        }

        echo sprintf("Package %s has defects(s).\n", $result->getPackageName());

        $defects = $result->getDefects();

        if (!(empty($defects['composer']))) {
            $this->printComposerMissedDependencies($defects['composer']);
        }

        if (!(empty($defects['module']))) {
            $this->printModuleXmlMissedDependencies($defects['module']);
        }
    }

    /**
     * Format and print.
     *
     * @param array $missedDependencies
     */
    private function printModuleXmlMissedDependencies(array $missedDependencies): void
    {
        echo "Missed dependencies in etc/module.xml\n";

        foreach ($missedDependencies as $packageNamespace) {
            echo sprintf("\t- %s\n", str_replace('\\', '_', $packageNamespace));
        }

        echo PHP_EOL;
    }

    /**
     * Format and print.
     *
     * @param string[] $missedDependencies
     */
    private function printComposerMissedDependencies(array $missedDependencies): void
    {
        echo "Missed dependencies in composer.json\n";

        foreach ($missedDependencies as $packageName) {
            echo sprintf("\t- \"%s\": \"*\"\n", $packageName);
        }

        echo PHP_EOL;
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
                    "\e[31mCan not find directory \"%s\". Please check your input parameters.",
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
        echo 'Tool to check integrity of declared dependencies in composer.json and etc/module.xml. Usage:' . PHP_EOL;
        echo 'php bin/dependencies [Magento2 root] {folder1} {folder2}' . PHP_EOL;
        echo '[Magento2 root] - path to Magento 2 project root directory.' . PHP_EOL;
        echo '{folder1} {folder2} - list of folders to scan, separated by space. ';
        echo 'If not provided, scan will be run for "src" and "app".' . PHP_EOL;
    }
}
