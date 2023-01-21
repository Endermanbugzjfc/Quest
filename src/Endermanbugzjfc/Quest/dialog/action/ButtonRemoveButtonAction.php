<?php

namespace Endermanbugzjfc\Quest\dialog\action;

use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\QuestInstance;

class ButtonRemoveButtonAction implements ButtonActionInterface
{

    public static function getIdentifier() : string
    {
        return "button_remove";
    }

    public static function execute(
        PlayerSession $playerSession,
        QuestInstance $quest,
        array         $buttons,
        int           $pressed,
        array         $param
    ) : array
    {
        unset($buttons[$pressed]);
        return $buttons ?? [];
    }
}