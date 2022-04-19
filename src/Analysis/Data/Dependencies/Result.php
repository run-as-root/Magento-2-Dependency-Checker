<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Analysis\Data\Dependencies;

use RunAsRoot\IntegrityChecker\Analysis\Data\ResultInterface;

class Result implements ResultInterface
{
    private string $packageName;

    private array $composerDefects;

    private array $moduleXmlDefects;

    /**
     * @param string $packageName
     * @param array $composerDefects
     * @param array $moduleXmlDefects
     */
    public function __construct(string $packageName, array $composerDefects, array $moduleXmlDefects)
    {
        $this->packageName = $packageName;
        $this->composerDefects = $composerDefects;
        $this->moduleXmlDefects = $moduleXmlDefects;
    }

    public function hasDefects(): bool
    {
        return !empty($this->composerDefects) || !empty($this->moduleXmlDefects);
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * Return complex defects array.
     * Structure:
     * [
     *  'composer' => [
     *          'missed\module-one',
     *          'missed\module-two'
     *        ],
     *  'module' => [
     *          'Module_One',
     *          'Module_Two',
     *        ]
     * ]
     *
     * @return array
     */
    public function getDefects(): array
    {
        return [
            'composer' => $this->composerDefects,
            'module' => $this->moduleXmlDefects
        ];
    }
}
