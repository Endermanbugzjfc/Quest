<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\utils;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\Server;

class Command
{

    private function __construct()
    {
    }

    public static function makeConsoleCommandSender() : ConsoleCommandSender
    {
        return new ConsoleCommandSender(
            $server = Server::getInstance(),
            $server->getLanguage()
        );
    }


}