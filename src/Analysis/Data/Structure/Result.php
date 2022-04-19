<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Data\Structure;

use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

class Result implements ResultInterface
{
    private string $packageName;

    private array $missedComponents;

    public function __construct(string $packageName, array $missedComponents)
    {
        $this->packageName = $packageName;
        $this->missedComponents = $missedComponents;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function hasDefects(): bool
    {
        return !empty($this->missedComponents);
    }

    public function getDefects(): array
    {
        return $this->missedComponents;
    }
}
