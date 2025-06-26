## Integrity Checker

Package allows to run static analysis on Magento 2 Module Packages to provide an integrity check of package.

### Supported Tools ###

- **Composer.json package dependencies checker** - check *.xml, *.js, *.php and *.phtml on a subject if other packages used inside
  and check if corresponding module/package is declared as required in composer.json.
- **Module.xml dependencies checker** - analyse if packages' etc/module.xml file contains in 'sequence' section all
  Magento 2 modules which classes are used in *.xml, *.js, *.php and *.phtml files of the package.
- **Package structure checker** - verify if all newly added Magento 2 modules has a proper structure with all required
  files.

### Standalone Installation ###
1. Add your access token to auth.json (see [how to create access token](https://medium.com/@sirajul.anik/install-composer-packages-from-private-repository-from-gitlab-b43597c409c0)).
```bash
composer config --global --auth gitlab-token.gitlab.com {ACCESS_TOKEN}
```
2. Install project from gitlab repository
```bash
composer create-project run_as_root/integrity-checker --repository-url="{\"type\": \"vcs\", \"url\": \"git@gitlab.com:oleksandr.kravchuk1/integrity-checker.git\"}" -s dev integrity-checker dev-development --remove-vcs
```

### Package Installation ###
1. Add Gitlab repository to list of available repositories for your project composer.json
```bash
composer config repositories.integrity-checker '{"type": "vcs", "url": "git@gitlab.com:oleksandr.kravchuk1/integrity-checker.git"}'
```
2. Add your access token to auth.json (see [how to create access token](https://medium.com/@sirajul.anik/install-composer-packages-from-private-repository-from-gitlab-b43597c409c0)).
```bash
composer config --global --auth gitlab-token.gitlab.com {ACCESS_TOKEN}
```
3. Change packages minimum stability to `dev` (required during development only.
```bash
composer config minimum-stability dev 
```
4. Install package via composer
```bash
composer require --dev run_as_root/integrity-checker dev-development
```

### Usage ###

#### Dependencies Checker ####

**Basic Usage:**
```bash
bin/dependencies {magento root} {folder} {folder2} {folder3}
```

**Advanced Options:**
```bash
bin/dependencies {magento root} {folder} [--ai-prompts] [--v2] [--no-legacy]
```

**Parameters:**
- `{magento root}` - path to Magento 2 project root directory. Tool requires composer.lock to be defined.
- `{folder}` - expected to be relative inside the magento root folder. All packages inside folder(s) will be recognized by composer.json file.
- `--ai-prompts` - add AI-friendly explanations after each problem for better understanding
- `--v2` - show copy-paste ready format with full file paths (automatically enables --no-legacy)
- `--no-legacy` - omit traditional output format (automatically enabled by --v2, or use with --ai-prompts)

**Examples:**

1. **Standard output** (classic format):
```bash
bin/dependencies /path/to/magento2 src/custom-modules
```
Output:
```
Package my/custom-module has defects(s).
Missed dependencies in composer.json
	- "magento/module-checkout": "*"
	- "magento/module-quote": "*"

Missed dependencies in etc/module.xml
	- Magento_Checkout
	- Magento_Quote
```

2. **AI-friendly explanations**:
```bash
bin/dependencies /path/to/magento2 src/custom-modules --ai-prompts
```
Output includes the standard format plus:
```
AI Prompt
----------
Package 'my/custom-module' at src/custom-modules/my/custom-module has 2 missing
composer dependencies: magento/module-checkout, magento/module-quote and 2
missing module.xml dependencies: Magento_Checkout, Magento_Quote. Add these to
composer.json require section and module.xml sequence section to fix loading
issues.
```

3. **Copy-paste ready format** (v2 - automatically clean):
```bash
bin/dependencies /path/to/magento2 src/custom-modules --v2
```
Output:
```
------------------------------------------------------------
Missed dependencies in src/custom-modules/my/custom-module/composer.json

,
"magento/module-checkout": "*",
"magento/module-quote": "*"
------------------------------------------------------------

------------------------------------------------------------
Missed dependencies in src/custom-modules/my/custom-module/etc/module.xml

<module name="Magento_Checkout"/>
<module name="Magento_Quote"/>
------------------------------------------------------------
```

4. **Combined v2 + AI explanations** (clean with AI help):
```bash
bin/dependencies /path/to/magento2 src/custom-modules --v2 --ai-prompts
```
Provides both the copy-paste ready format and AI explanations, with no legacy output.

5. **Clean AI explanations only** (no legacy output) - **"One Shot" Solution**:
```bash
bin/dependencies /path/to/magento2 src/custom-modules --ai-prompts --no-legacy
```
This combination provides **clean, AI-ready output** perfect for:
- **One-shot problem solving**: Copy the AI explanation directly to ChatGPT/Claude for instant solutions
- **Documentation**: Clean explanations without technical noise
- **Learning**: Understand what needs to be fixed and why

Output:
```
AI Prompt
----------
Package 'my/custom-module' at src/custom-modules/my/custom-module has 2 missing
composer dependencies: magento/module-checkout, magento/module-quote and 2
missing module.xml dependencies: Magento_Checkout, Magento_Quote. Add these to
composer.json require section and module.xml sequence section to fix loading
issues.
```

💡 **Pro Tip**: Copy this output directly to any AI assistant for instant, context-aware solutions to your dependency issues!

### Validation and Error Handling

The `--no-legacy` flag has built-in validation to prevent confusing output:

**Error case** - using `--no-legacy` alone:
```bash
bin/dependencies /path/to/magento2 src/custom-modules --no-legacy
```
Output:
```
Error: --no-legacy flag requires either --v2 or --ai-prompts to be specified.

Usage: bin/dependencies {magento_path} {folders} [--ai-prompts] [--v2] [--no-legacy]
...
```

**Valid combinations**:
- `--v2` (automatically enables --no-legacy) ✓
- `--no-legacy --ai-prompts` ✓  
- `--v2 --ai-prompts` (automatically enables --no-legacy) ✓

**Notes:**
- Dependencies check will be run for composer.json and etc/module.xml together.
- If no folders are specified, the tool will scan "src" and "app" by default.
- The v2 format provides exact code snippets that can be copied directly into your files.
- Leading commas in composer.json format make it easy to paste into existing require sections.
- **`--v2` automatically enables `--no-legacy`** for clean, modern output.
- Use `--no-legacy --ai-prompts` for the cleanest AI-ready output - perfect for "one-shot" problem solving.
- The `--no-legacy` flag must be combined with `--v2` or `--ai-prompts` - using it alone will show an error and help.
- The `--no-legacy` flag is particularly useful in CI/CD pipelines or when processing output programmatically.

#### Module Structure Checker ####

```bash
bin/structure {magento root} {folder} {folder2} {folder3}
```

{magento root} - path to Magento 2 project root directory.
Tool collects all packages in {folder} by registration.php files. For each module it compares
current structure with Standard structure and print diff, if Standard structure was not followed.

Standard package structure:

```bash
docs
src
  etc
    module.xml
README.md
composer.json
registration.php
```

### Output Formats ###

The dependencies checker supports multiple output formats to suit different workflows:

#### Classic Format (Default)
The traditional output format showing missing dependencies in a simple list format. Compatible with all existing tooling and scripts.

#### AI-Friendly Format (`--ai-prompts`)
Adds explanatory text after each problem, making it easier for AI assistants and developers to understand what needs to be fixed and why.

#### Copy-Paste Ready Format (`--v2`)
Provides formatted code snippets with full file paths that can be directly copied into your project files:
- Composer dependencies with leading commas for easy pasting
- Module.xml entries in proper XML format
- Visual separators for clear section identification
- Relative file paths for easy navigation

#### Clean Output Mode (`--no-legacy`)
Omits the traditional output format, showing only modern formats (v2 or AI explanations):
- Cleaner output for automated processing
- Ideal for CI/CD pipelines and tooling integration
- Must be combined with `--v2` or `--ai-prompts` flags
- Reduces noise when only specific output formats are needed

### Backwards Compatibility ###

All existing functionality remains unchanged. The classic output format is still the default, ensuring compatibility with existing scripts, CI/CD pipelines, and tooling that depend on the original output format.
