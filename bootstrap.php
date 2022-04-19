<?php declare(strict_types=1);

if (isset($argv[1])) {
    define('ROOT_DIR', realpath($argv[1]) . '/');
}

if (is_file(__DIR__ . '/../autoload.php')) {
    //Installed as package.
    include_once __DIR__ . '/../autoload.php';
} elseif (is_file(__DIR__ . '/../../../vendor/autoload.php')) {
    //Installed as symlink.
    include_once __DIR__ . '/../../../vendor/autoload.php';
} elseif(is_file(__DIR__ . '/vendor/autoload.php')) {
    //Installed as project.
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo 'Can not find vendor autoload.php file.' . PHP_EOL;
    echo 'Please run \'composer install\' and check that' .
        ' Integrity Checker tool is installed as composer package to your project.';
    exit(1);
}

define('PACKAGE_DIR', realpath(__DIR__));
