<?php

namespace Endermanbugzjfc\Quest\player;

use Endermanbugzjfc\Quest\Quest;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;
poggit\libasynql\SqlError;
SOFe\AwaitStd\Await;
SOFe\AwaitStd\DisposeException;
use Throwable;
use function count;
use function implode;

class PlayerCustomizable
{

    public function listQuests() : void
    {
        $quests = $this->getPlayerSession()->getQuests();
        $amount = count($quests);
        $tasks = 0;
        foreach ($quests as $quest) {
            $tasks += count($quest->getTasks());
        }
        $lines = ["§l§6You have §a$amount §6quests (§a$tasks §6tasks):"];

        foreach ($quests as $quest) {
            foreach ($quest->getTasks() as $task) {
                $lines[] = $task->getDisplayProgress(
                    $session = $this->getPlayerSession(),
                    $session->getTaskProgress(
                        $quest,
                        $task
                    ) ?? []
                );
            }
        }

        $this->getPlayerSession()->getPlayer()->sendMessage(implode(
            "\n",
            $lines
        ));
    }

    public function onWaitingForDatabase() : callable
    {
        $stop = false;
        Await::f2c(function () use
        (
            &
            $stop
        ) {
            $player = $this->getPlayerSession()->getPlayer();
            $std = Quest::getInstance()->getStd();

            if (!$player->spawned) {
                try {
                    yield $std->awaitEvent(
                        PlayerJoinEvent::class,
                        fn(PlayerJoinEvent $event) => $event->getPlayer() === $player,
                        false,
                        EventPriority::MONITOR,
                        false,
                        $player
                    );
                } catch (DisposeException) {
                    return;
                }
            }
            $reverse = false;
            $pos = 0;
            while (!$stop and $player->isOnline()) {
                if ($pos >= 9) {
                    $reverse = true;
                }
                if ($pos <= 0) {
                    $reverse = false;
                }
                $msg = TextFormat::YELLOW;
                for ($x = 0; $x < 10; $x++) {
                    if ($x === $pos) {
                        $msg .= TextFormat::AQUA
                            . "\u{2588}" . TextFormat::YELLOW;
                    } else {
                        $msg .= "\u{2588}";
                    }
                }
                $pos += $reverse ? -1 : 1;
                $player->sendPopup($msg);
                yield $std->sleep(10);
            }
        });
        return (function () use
        (
            &
            $stop
        ) {
            $stop = true;
        });
    }

    public function onDatabaseError(SqlError $err) : void
    {
        $this->onOtherError($err);
    }

    public function onOtherError(Throwable $err) : void
    {
        Quest::getInstance()->getLogger()->logException($err);
        $this->getPlayerSession()->getPlayer()->sendMessage(
            TextFormat::BOLD . TextFormat::RED
            . "Sorry, something went wrong! Please report this to an admin."
        );
    }

    public function __construct(
        protected PlayerSession $playerSession
    )
    {
    }

    /**
     * @return PlayerSession
     */
    public function getPlayerSession() : PlayerSession
    {
        return $this->playerSession;
    }

}