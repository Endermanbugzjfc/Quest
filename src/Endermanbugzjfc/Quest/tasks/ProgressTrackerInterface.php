<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\tasks;

use Closure;
use Endermanbugzjfc\Quest\player\PlayerSession;

interface ProgressTrackerInterface extends TaskInterface
{

    /**
     * @param PlayerSession $playerSession
     * @param Closure $updateProgress
     * @param array $savedProgress
     */
    public function startProgressTracking(
        PlayerSession $playerSession,
        Closure       $updateProgress,
        array         $savedProgress
    ) : void;

    public function stopProgressTracking(PlayerSession $playerSession) : void;

}