<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Application\Registry;

use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

class DefectsState
{
    private bool $hasDefects = false;

    public function registerResult(ResultInterface $result)
    {
        $this->hasDefects = $this->hasDefects || $result->hasDefects();
    }

    public function hasDefects(): bool
    {
        return $this->hasDefects;
    }
}