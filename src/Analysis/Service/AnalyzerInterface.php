<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Service;

interface AnalyzerInterface
{
    public function analyse(iterable $packages): iterable;
}
