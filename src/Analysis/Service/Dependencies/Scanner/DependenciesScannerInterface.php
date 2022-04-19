<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Service\Dependencies\Scanner;

use RunAsRoot\IntegrityChecker\Domain\Package;

interface DependenciesScannerInterface
{
    public function lookupDependencies(Package $package): array;
}
