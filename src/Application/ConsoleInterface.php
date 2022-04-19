<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Application;

use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

interface ConsoleInterface
{
    public function validateParameters(): bool;

    public function printHelp(): void;

    public function printOutput(ResultInterface $result): void;

    public function getStatusCode(): int;
}
