<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\tasks;

use Endermanbugzjfc\Quest\player\PlayerSession;

interface TaskInterface
{

    public function __construct(array $params);

    public function hasCompleted(
        PlayerSession $playerSession,
        array         $progress
    ) : bool;

    public static function getIdentifier() : string;

    public function getDisplayProgress(
        PlayerSession $playerSession,
        array         $progress
    ) : string;

}