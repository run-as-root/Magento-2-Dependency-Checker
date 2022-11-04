## Integrity checker

Package allows to run static analysis on Magento 2 Module Packages to provide an integrity check of package.

### Supported tools: ###

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

```bash
bin/dependencies {magento root} {folder} {folder2} {folder3}
```

{magento root} - path to Magento 2 project root directory.
Tool require composer.lock to be defined.
All packages inside {folder}'s will be recognized by composer.json file. {folder} - expected to be relative inside the
magento root folder. Dependencies check will be run for composer.json and etc/module.xml together.

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
