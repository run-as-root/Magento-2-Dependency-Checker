<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Enum;

class DefaultPackageStructure
{
    public const STRUCTURE = [
        'composer.json',
        'README.md',
        'registration.php',
        'docs' => [],
        'src' => [
            'etc' => [
                'module.xml'
            ]
        ]
    ];
}