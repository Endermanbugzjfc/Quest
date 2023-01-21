<?php

namespace Endermanbugzjfc\Quest\dialog\action;

use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\Quest;
use Endermanbugzjfc\Quest\QuestInstance;
use Endermanbugzjfc\Quest\utils\ItemUtils;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

class GiveBoxButtonAction implements ButtonActionInterface
{

    public static function getIdentifier() : string
    {
        return "give_box";
    }


    public static function execute(
        PlayerSession $playerSession,
        QuestInstance $quest,
        array         $buttons,
        int           $pressed,
        array         $param
    ) : array
    {
        foreach ($param as $boxParam) {
            $box = StringToItemParser::getInstance()->parse($boxParam["icon"]);
            $box->setCustomName($boxParam["name"]);
            $tag = $box->getNamedTag();
            $questTag = $tag->getCompoundTag(
                    Quest::NAMED_TAG_IDENTIFIER
                ) ?? new CompoundTag();

            foreach ($boxParam["items"] as $itemId => $itemParam) {
                $item = StringToItemParser::getInstance()->parse($itemId);
                $item->setCount($itemParam["amount"] ?? 1);
                $item->setCustomName($itemParam["name"] ?? "");
                $items[] = $item->nbtSerialize();
            }

            $questTag->setTag("box", new ListTag($items ?? []));
            $tag->setTag(Quest::NAMED_TAG_IDENTIFIER, $questTag);
            $box->setNamedTag($tag);

            ItemUtils::dropOrAddToInventory(
                $playerSession->getPlayer(),
                $box
            );
        }
        return $buttons;
    }
}