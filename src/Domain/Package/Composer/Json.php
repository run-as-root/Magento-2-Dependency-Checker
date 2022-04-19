<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Domain\Package\Composer;

class Json
{
    private string $path;

    private ?array $content = null;

    /**
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Get module directory path.
     *
     * @return string
     */
    public function getDirPath(): string
    {
        return dirname($this->path);
    }

    /**
     * Get Package Name
     *
     * @return string|null
     */
    public function getPackageName(): ?string
    {
        return $this->getContent()['name'] ?? null;
    }

    /**
     * Get Package Type (e.g. library, magento2-module etc).
     *
     * @return string|null
     */
    public function getPackageType(): ?string
    {
        return $this->getContent()['type'] ?? null;
    }

    /**
     * Get psr-4 package namespaces. Some packages could declare more then one namespace.
     *
     * @return array
     */
    public function getNamespace(): array
    {
        return isset($this->getContent()['autoload']['psr-4']) ?
            array_keys($this->getContent()['autoload']['psr-4']) : [];
    }

    /**
     * Return packages specified in 'require' section.
     *
     * @return array
     */
    public function getDependencies(): array
    {
        $dependencies = $this->getContent()['require'] ?? [];
        $dependencies = array_keys($dependencies);

        return array_filter($dependencies, function (string $dependency): bool
        {
            return strpos($dependency, '/') !== false;
        });
    }

    /**
     * Get composer.json content structured into array.
     *
     * @return array
     */
    private function getContent(): array
    {
        if (!$this->content) {
            $parsedContent = json_decode(file_get_contents($this->path), true);
            $this->content = $parsedContent ?? [];
        }

        return $this->content;
    }
}
