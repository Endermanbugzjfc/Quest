<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\tasks;

use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\utils\SetupError;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use function implode;

final class ObtainTask implements TaskInterface
{
    use ItemTaskTrait;

    /***
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
        $this->items = self::parseItemParams($params, $additionalData);
        $this->loadProgressMessage($additionalData);
    }

    public static function getIdentifier() : string
    {
        return "obtain";
    }

    public function hasCompleted(
        PlayerSession $playerSession,
        array         $progress
    ) : bool
    {
        $count = $this->count($playerSession);
        foreach ($this->items as $index => $item) {
            if (($count[$index] ?? 0) < $item->getCount()) {
                return false;
            }
        }
        return true;
    }

    public function getDisplayProgress(PlayerSession $playerSession, array $progress) : string
    {
        $lines = [];
        $count = $this->count($playerSession);
        foreach ($this->items as $index => $item) {
            $itemProgress = $count[$index];

            $pleaseStopPhpSjom = $this->getProgressMessage(
                $lines,
                (string)$itemProgress,
                $index
            );
            if ($pleaseStopPhpSjom) {
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

    protected function count(PlayerSession $playerSession) : array
    {
        $count = [];
        foreach ($this->items as $index => $item) {
            foreach ($playerSession
                         ->getPlayer()
                         ->getInventory()
                         ->getContents()
                     as $si) {
                if (self::itemEquals($si, $item)) {
                    @$count[$index] += $item->getCount();
                }
            }
        }
        return $count;
    }
}