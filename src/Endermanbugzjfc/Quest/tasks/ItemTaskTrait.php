<?php

namespace Endermanbugzjfc\Quest\tasks;

use Endermanbugzjfc\Quest\utils\SetupError;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function array_push;
use function str_replace;

trait ItemTaskTrait
{

    protected array $progressMessages = [];

    /**
     * @param array $params
     * @param array|null $additionalData
     * @return Item[]
     * @throws SetupError
     */
    private static function parseItemParams(
        array $params,
        array &$additionalData = null
    ) : array
    {
        $items = [];
        foreach ($params as $k => $v) {
            $item = StringToItemParser::getInstance()->parse($k)
                ?? self::invalidItemId($k);
            $name = $v["name"] ?? null;
            unset($v["name"]);
            if (isset($name)) {
                $item->setCustomName($name);
            }
            foreach ($items as $si) {
                if (self::itemEquals($item, $si)) {
                    throw new SetupError("Duplicated item: $k with the name \"$name\"");
                }
            }
            $index = array_push($items, $item) - 1;
            $item->setCount(($v["amount"] ?? 1));
            unset($v["amount"]);
            if ($additionalData !== null) {
                $additionalData[$index] = $v;
            }
        }
        return $items;
    }

    private static function itemEquals(Item $item, Item $taskItem) : bool
    {
        $id = $item->getId() === $taskItem->getId();
        $meta = $item->getMeta() === $taskItem->getMeta();
        if ($taskItem->hasCustomName()) {
            $name = $item->getCustomName() === $taskItem->getCustomName();
        }
        return $id and $meta and ($name ?? true);
    }

    private static function invalidItemId(string $id) : void
    {
        throw new SetupError("Invalid item ID: $id");
    }

    private function getProgressMessage(
        array  &$lines,
        string $progress,
        int    $index
    ) : bool
    {
        $progressMessage = $this->progressMessages[$index] ?? null;
        if ($progressMessage !== null) {
            if ($progressMessage !== "") {
                $lines[] = str_replace(
                    "{progress}",
                    $progress,
                    $progressMessage
                );
                return true;
            }
        }
        return false;
    }

    private function loadProgressMessage(
        array $additionalData,
    ) : void
    {
        foreach ($additionalData as $index => $data) {
            $progressMessage = $data["progress-message"] ?? null;
            if ($progressMessage !== null) {
                $this->progressMessages[$index] = $progressMessage;
            }
        }
    }

}