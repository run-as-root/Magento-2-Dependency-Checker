<?php declare(strict_types=1);

namespace RunAsRoot\IntegrityChecker\Exception;

use Throwable;

class FileNotFoundException extends \Exception
{
    private const MESSAGE_PATTERN = 'File was not found: %s in package %s.';

    public function __construct(string $file, string $package, $message = "", $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = sprintf(self::MESSAGE_PATTERN, $file, $package);
        }

        parent::__construct($message, $code, $previous);
    }
}
