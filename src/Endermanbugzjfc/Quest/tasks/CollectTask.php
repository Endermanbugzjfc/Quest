<?php

namespace Endermanbugzjfc\Quest\tasks;

use Closure;
use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\Quest;
use Endermanbugzjfc\Quest\utils\SetupError;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use _84cd9599c7b9e93885c2SOFe\AwaitStd\Await;
use function implode;
use function min;
use function spl_object_id;

final class CollectTask implements TaskInterface, ProgressTrackerInterface
{
    use ItemTaskTrait;


    /**
     * @var Item[]
     */
    protected array $items = [];

    /**
     * @param array $params
     * @throws SetupError
     */
    public function __construct(array $params)
    {
        $additionalData = [];
        $this->items = self::parseItemParams($params, $additionalData);
        $this->loadProgressMessage(
            $additionalData,
        );
    }

    public static function getIdentifier() : string
    {

        return "collect";
    }

    public function startProgressTracking(
        PlayerSession $playerSession,
        Closure       $updateProgress,
        array         $savedProgress
    ) : void
    {
        $progress = $savedProgress;
        unset($savedProgress);


        Await::f2c(function () use ($progress, $updateProgress, $playerSession) {
            if ($this->hasCompleted($playerSession, $progress)) {
                return;
            }
            while (true) {
                $stopper = $playerSession->newStopper(spl_object_id($this));
                [, $event] = yield Await::race([
                    $stopper,
                    Quest::getInstance()->getStd()->awaitEvent(
                        PlayerItemHeldEvent::class,
                        fn(PlayerItemHeldEvent $event) => $event->getPlayer() === $playerSession->getPlayer(),
                        false,
                        EventPriority::MONITOR,
                        false,
                        $playerSession->getPlayer()
                    )
                ]);
                if (!$event instanceof PlayerItemHeldEvent) {
                    return;
                }
                $item = $event->getItem();
                foreach ($this->items as $index => $taskItem) {
                    if (!self::itemEquals($event->getItem(), $taskItem)) {
                        continue;
                    }
                    $old = $progress[$index] ?? 0;
                    $new = $old + $item->getCount();
                    $new = min($taskItem->getCount(), $new);
                    $diff = $new - $old;
                    $item->setCount($item->getCount() - $diff);
                    $playerSession->getPlayer()
                        ->getInventory()
                        ->setItem(
                            $event->getSlot(),
                            $item
                        );
                    $progress[$index] = $new;
                    $updateProgress($progress);
                }
            }
        });
    }

    public function stopProgressTracking(PlayerSession $playerSession) : void
    {
        $playerSession->resolveStopper(spl_object_id($this));
    }

    public function hasCompleted(
        PlayerSession $playerSession,
        array         $progress
    ) : bool
    {
        foreach ($this->items as $index => $item) {
            if ($item->getCount() > ($progress[$index] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    public function getDisplayProgress(PlayerSession $playerSession, array $progress) : string
    {
        $lines = [];
        foreach ($this->items as $index => $item) {
            $itemProgress = $progress[$index] ?? 0;
            if ($this->getProgressMessage(
                $lines,
                (string)$itemProgress,
                $index
            )) {
                continue;
            }

            $name = $item->getCustomName();
            if ($name === "") {
                $name = $item->getVanillaName();
            }
            $lines[] = TextFormat::YELLOW . $name . ": " . TextFormat::GREEN
                . ($itemProgress) . " / {$item->getCount()}";

        }
        return implode("\n", $lines ?? []);
    }
}