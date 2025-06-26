<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Application\Dependencies;

use RunAsRoot\IntegrityChecker\Application\ConsoleInterface;
use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;
use RunAsRoot\IntegrityChecker\Application\Registry\DefectsState;

class Console implements ConsoleInterface
{
    private DefectsState $defectsState;
    private bool $aiPromptsMode = false;
    private bool $v2Mode = false;
    private bool $noLegacy = false;

    public function __construct()
    {
        $this->defectsState = new DefectsState();
        $this->detectModes();
    }

    /**
     * Detect if --ai-prompts, --v2, or --no-legacy flags are present in command line arguments.
     */
    private function detectModes(): void
    {
        $argv = $_SERVER['argv'] ?? [];
        $this->v2Mode = in_array('--v2', $argv);
        $this->aiPromptsMode = in_array('--ai-prompts', $argv);
        $this->noLegacy = in_array('--no-legacy', $argv);
        
        // Auto-enable no-legacy when v2 is used
        if ($this->v2Mode) {
            $this->noLegacy = true;
        }
    }

    /**
     * Check if AI prompts mode is enabled.
     */
    public function isAiPromptsMode(): bool
    {
        return $this->aiPromptsMode;
    }

    /**
     * Check if v2 mode is enabled.
     */
    public function isV2Mode(): bool
    {
        return $this->v2Mode;
    }

    /**
     * Check if no-legacy mode is enabled.
     */
    public function isNoLegacy(): bool
    {
        return $this->noLegacy;
    }

    /**
     * Validate that --no-legacy is used correctly.
     * 
     * @return bool True if valid, false if invalid
     */
    public function validateNoLegacyUsage(): bool
    {
        $argv = $_SERVER['argv'] ?? [];
        $explicitNoLegacy = in_array('--no-legacy', $argv);
        
        // Only validate if --no-legacy was explicitly used (not auto-enabled by --v2)
        if ($explicitNoLegacy && !$this->v2Mode && !$this->aiPromptsMode) {
            echo "\e[31mError: --no-legacy flag requires either --v2 or --ai-prompts to be specified.\e[30m" . PHP_EOL . PHP_EOL;
            $this->printHelp();
            return false;
        }
        return true;
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

        // Validate --no-legacy usage
        if (!$this->validateNoLegacyUsage()) {
            exit(1);
        }

        // Print legacy format unless --no-legacy is used
        if (!$this->noLegacy) {
            echo sprintf("Package %s has defects(s).\n", $result->getPackageName());

            $defects = $result->getDefects();

            if (!(empty($defects['composer']))) {
                $this->printComposerMissedDependencies($defects['composer']);
            }

            if (!(empty($defects['module']))) {
                $this->printModuleXmlMissedDependencies($defects['module']);
            }
        }

        // Print v2 format if enabled
        if ($this->v2Mode) {
            $defects = $result->getDefects();
            $packagePath = method_exists($result, 'getPackagePath') ? $result->getPackagePath() : null;

            if (!empty($defects['composer']) && $packagePath) {
                $this->printComposerMissedDependenciesV2($defects['composer'], $packagePath);
            }

            if (!empty($defects['module']) && $packagePath) {
                $this->printModuleXmlMissedDependenciesV2($defects['module'], $packagePath);
            }
        }

        // Add AI-friendly explanation if in AI mode
        if ($this->aiPromptsMode) {
            $this->printAiExplanation($result);
        }
    }

    /**
     * Print AI-friendly explanation of the dependency issues.
     */
    private function printAiExplanation(ResultInterface $result): void
    {
        $defects = $result->getDefects();
        $packageName = $result->getPackageName();
        $packagePath = method_exists($result, 'getPackagePath') ? $result->getPackagePath() : null;
        
        // Make path relative to ROOT_DIR if possible
        if ($packagePath && defined('ROOT_DIR')) {
            $relativePath = str_replace(ROOT_DIR, '', $packagePath);
            $relativePath = ltrim($relativePath, '/');
            $packageIdentifier = $relativePath ? "'{$packageName}' at {$relativePath}" : "'{$packageName}'";
        } else {
            $packageIdentifier = "'{$packageName}'";
        }
        
        echo "AI Prompt" . PHP_EOL;
        echo "----------" . PHP_EOL;
        $explanation = "Package {$packageIdentifier}";
        
        if (!empty($defects['composer']) && !empty($defects['module'])) {
            $moduleDefectsFormatted = array_map(fn($module) => str_replace('\\', '_', $module), $defects['module']);
            $explanation .= " has " . count($defects['composer']) . " missing composer dependencies: " . implode(', ', $defects['composer']);
            $explanation .= " and " . count($defects['module']) . " missing module.xml dependencies: " . implode(', ', $moduleDefectsFormatted) . ". ";
            $explanation .= "Add these to composer.json require section and module.xml sequence section to fix loading issues.";
        } elseif (!empty($defects['composer'])) {
            $explanation .= " uses " . count($defects['composer']) . " undeclared composer packages: " . implode(', ', $defects['composer']) . ". ";
            $explanation .= "Add these to composer.json require section to prevent runtime errors.";
        } elseif (!empty($defects['module'])) {
            $moduleDefectsFormatted = array_map(fn($module) => str_replace('\\', '_', $module), $defects['module']);
            $explanation .= " references " . count($defects['module']) . " undeclared Magento modules: " . implode(', ', $moduleDefectsFormatted) . ". ";
            $explanation .= "Add these to module.xml sequence section to ensure proper loading order.";
        }
        
        // Word wrap the explanation at 80 characters
        $wrappedExplanation = wordwrap($explanation, 80, PHP_EOL);
        
        echo $wrappedExplanation . PHP_EOL . PHP_EOL;
    }

    /**
     * Format and print composer dependencies in v2 format (copy-paste ready JSON).
     *
     * @param string[] $missedDependencies
     * @param string $packagePath
     */
    private function printComposerMissedDependenciesV2(array $missedDependencies, string $packagePath): void
    {
        // Make path relative to ROOT_DIR if possible
        $relativePath = $packagePath;
        if (defined('ROOT_DIR')) {
            $relativePath = str_replace(ROOT_DIR, '', $packagePath);
            $relativePath = ltrim($relativePath, '/');
        }
        
        echo "Missed dependencies in {$relativePath}/composer.json\n";
        echo str_repeat('-', 60) . PHP_EOL;
        
        foreach ($missedDependencies as $packageName) {
            echo sprintf(",\n\"%s\": \"*\"", $packageName);
        }
        echo PHP_EOL;
        echo str_repeat('-', 60) . PHP_EOL . PHP_EOL;
    }

    /**
     * Format and print module.xml dependencies in v2 format (copy-paste ready XML).
     *
     * @param array $missedDependencies
     * @param string $packagePath
     */
    private function printModuleXmlMissedDependenciesV2(array $missedDependencies, string $packagePath): void
    {
        // Make path relative to ROOT_DIR if possible
        $relativePath = $packagePath;
        if (defined('ROOT_DIR')) {
            $relativePath = str_replace(ROOT_DIR, '', $packagePath);
            $relativePath = ltrim($relativePath, '/');
        }
        
        echo "Missed dependencies in {$relativePath}/etc/module.xml\n";
        echo str_repeat('-', 60) . PHP_EOL;
        foreach ($missedDependencies as $packageNamespace) {
            $moduleName = str_replace('\\', '_', $packageNamespace);
            echo sprintf("<module name=\"%s\"/>\n", $moduleName);
        }

        echo str_repeat('-', 60) . PHP_EOL . PHP_EOL;
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

        // Filter out --ai-prompts, --v2, and --no-legacy flags for path validation
        $filteredArgv = array_filter($argv, fn($arg) => !in_array($arg, ['--ai-prompts', '--v2', '--no-legacy']));
        $filteredArgv = array_values($filteredArgv);

        if (!is_file($filteredArgv[1] . DIRECTORY_SEPARATOR . 'composer.lock')) {
            echo "\e[31m\"composer.lock\" file was not found in Magento 2 Directory.\e[30m" . PHP_EOL;
            return false;
        }

        $result = true;
        for ($i = 2; $i < count($filteredArgv); $i++) {
            if (!is_dir(ROOT_DIR . $filteredArgv[$i])) {
                echo  sprintf(
                    "\e[31mCan not find directory \"%s\". Please check your input parameters.",
                    ROOT_DIR . $filteredArgv[$i]
                ) . PHP_EOL
                    . sprintf("Path \"%s\" should be relative to Magento 2 Directory.\e[30m", $filteredArgv[$i])
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
        echo 'php bin/dependencies [Magento2 root] {folder1} {folder2} [--ai-prompts] [--v2] [--no-legacy]' . PHP_EOL;
        echo '[Magento2 root] - path to Magento 2 project root directory.' . PHP_EOL;
        echo '{folder1} {folder2} - list of folders to scan, separated by space. ';
        echo 'If not provided, scan will be run for "src" and "app".' . PHP_EOL;
        echo '--ai-prompts - add AI-friendly explanations after each problem.' . PHP_EOL;
        echo '--v2 - show copy-paste ready format with file paths.' . PHP_EOL;
        echo '--no-legacy - omit traditional output format (use with --v2 or --ai-prompts).' . PHP_EOL;
    }
}
