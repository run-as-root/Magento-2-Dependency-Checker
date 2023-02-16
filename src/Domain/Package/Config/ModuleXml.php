<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Domain\Package\Config;

class ModuleXml
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
     * Get module name from etc/module.xml. Name in format: Vendor_Module.
     *
     * @return string|null
     */
    public function getModuleName(): ?string
    {
        return $this->content['module']['name'] ?? null;
    }

    /**
     * Get dependencies declared in 'sequence' section of module.xml.
     *
     * @return array
     */
    public function getDependencies(): array
    {
        return $this->getContent()['module']['sequence'] ?? [];
    }

    /**
     * Get module.xml content.
     *
     * @return array
     */
    private function getContent(): array
    {
        if ($this->content === null) {
            $this->parseModuleXmlFile();
        }

        return $this->content;
    }

    /**
     * Parse module.xml file and load all required data.
     *
     * @return void
     */
    private function parseModuleXmlFile(): void
    {
        $content = simplexml_load_file($this->path);
        $this->content = [];

        if (!$content->module) {
            return;
        }

        foreach ($content->module->attributes() as $key => $value) {
            $this->content['module'][(string)$key] = (string)$value;
        }

        if (!$content->module->sequence) {
            return;
        }

        $this->content['module']['sequence'] = [];

        foreach ($content->module->sequence->children() as $module) {
            $moduleName = get_mangled_object_vars($module->attributes())['@attributes']['name'];
            $this->content['module']['sequence'][] = $moduleName;
        }
    }
}
