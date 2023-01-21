<?php

namespace Endermanbugzjfc\Quest\dialog\action;

use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\QuestInstance;

class AssignButtonAction implements ButtonActionInterface
{

    public static function getIdentifier() : string
    {
        return "assign";
    }

    public static function execute(
        PlayerSession $playerSession,
        QuestInstance $quest,
        array         $buttons,
        int           $pressed,
        array         $param
    ) : array
    {
        $playerSession->assignQuest($quest);
        return $buttons;
    }
}