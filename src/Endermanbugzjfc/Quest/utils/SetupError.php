<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\utils;

use Error;
use Throwable;

class SetupError extends Error
{

    /** @noinspection PhpPureAttributeCanBeAddedInspection */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Something is wrong with your setup or configuration, it is not the plugin's fault! ($message)", $code, $previous);
    }

}