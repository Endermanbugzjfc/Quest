<?php

namespace Endermanbugzjfc\Quest\tasks;

use Closure;
use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\Quest;
use Endermanbugzjfc\Quest\utils\SetupError;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\TextFormat;
use SOFe\AwaitStd\Await;
use SOFe\AwaitStd\DisposeException;
use function implode;
use function spl_object_id;

final class InteractTask implements TaskInterface, ProgressTrackerInterface
{
    use ItemTaskTrait;


    /**
     * @var ItemBlock[]
     */
    protected array $blocks = [];

    /**
     * @var Item[]
     */
    protected array $items = [];

    protected array $progressMessages = [];

    /**
     * @param array $params
     * @throws SetupError
     */
    public function __construct(array $params)
    {
        $additionalData = [];
        $this->blocks = self::parseItemParams($params, $additionalData);
        foreach ($this->blocks as $index => $block) {
            if (!$block instanceof ItemBlock) {
                throw new SetupError(
                    "{$block->getVanillaName()} is not a block"
                );
            }
            $item = $additionalData[$index]["item"] ?? null;
            if ($item !== null) {
                $this->items[$index] = StringToItemParser
                        ::getInstance()
                        ->parse($item)
                    ?? self::invalidItemId($item);
            }
            $this->loadProgressMessage($additionalData);
        }
    }

    public static function getIdentifier() : string
    {

        return "interact";
    }

    public function startProgressTracking(
        PlayerSession $playerSession,
        Closure       $updateProgress,
        array         $savedProgress
    ) : void
    {
        Await::f2c(function () use ($savedProgress, $updateProgress, $playerSession) {
            while (true) {
                if ($this->hasCompleted($playerSession, $savedProgress)) {
                    return;
                }
                $stopper = $playerSession->newStopper(spl_object_id($this));
                [, $event] = yield Await::race([
                    $stopper,
                    Quest::getInstance()->getStd()->awaitEvent(
                        PlayerInteractEvent::class,
                        fn(PlayerInteractEvent $event) => $event->getPlayer() === $playerSession->getPlayer()
                            and $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK,
                        false,
                        EventPriority::MONITOR,
                        false,
                        $playerSession->getPlayer()
                    )
                ]);
                if (!$event instanceof PlayerInteractEvent) {
                    return;
                }
                $update = false;
                foreach ($this->blocks as $index => $block) {
                    $updateThis = false;
                    if (self::itemEquals(
                        $event->getBlock()->asItem(),
                        $block
                    )) {
                        $item = $this->items[$index] ?? null;
                        if (isset($item)) {
                            if (self::itemEquals($event->getItem(), $item)) {
                                $updateThis = true;
                            }
                        } else {
                            $updateThis = true;
                        }
                    }
                    if ($updateThis) {
                        @$savedProgress[$index] += 1;
                        $update = true;
                    }
                }
                if ($update) {
                    $updateProgress($savedProgress);
                }
            }
        }, null, fn(DisposeException $err) => null);
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
        foreach ($this->blocks as $index => $block) {
            if ($block->getCount() > ($progress[$index] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    public function getDisplayProgress(PlayerSession $playerSession, array $progress) : string
    {
        $lines = [];
        foreach ($this->blocks as $index => $block) {
            $blockProgress = $progress[$index] ?? 0;
            if ($this->getProgressMessage(
                $lines,
                $blockProgress,
                $index
            )) {
                continue;
            }

            $name = $block->getCustomName();
            if ($name === "") {
                $name = $block->getVanillaName();
            }
            $lines[] = TextFormat::YELLOW . "Interact " . TextFormat::AQUA
                . $name . TextFormat::YELLOW . (isset($this->items[$index])
                    ? " with " . TextFormat::AQUA . $this->items[$index]
                    . TextFormat::YELLOW
                    : ""
                ) . " for " . TextFormat::GREEN . ($blockProgress)
                . " / {$block->getCount()}";
        }
        return implode("\n", $lines ?? []);
    }
}