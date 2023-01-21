<?php

namespace Endermanbugzjfc\Quest\utils;

use pocketmine\item\Item;
use pocketmine\player\Player;

class ItemUtils
{

    private function __construct()
    {
    }

    public static function dropOrAddToInventory(
        Player $player,
        Item   $item
    ) : void
    {
        $inventory = $player->getInventory();
        if ($inventory->firstEmpty() === -1) {
            $player->getWorld()->dropItem(
                $player->getPosition(),
                $item
            );
        } else {
            $inventory->addItem($item);
        }
    }


}