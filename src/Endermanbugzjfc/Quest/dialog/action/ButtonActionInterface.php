<?php

namespace Endermanbugzjfc\Quest\dialog\action;

use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\QuestInstance;
use _1cf7da649ebdaa27fef8ref\libNpcDialogue\form\NpcDialogueButtonData;

interface ButtonActionInterface
{

    public static function getIdentifier() : string;

    /**
     * @param PlayerSession $playerSession
     * @param QuestInstance $quest
     * @param NpcDialogueButtonData[] $buttons
     * @param int $pressed
     * @param array $param
     * @return NpcDialogueButtonData[] Buttons to add to the dialog.
     */
    public static function execute(
        PlayerSession $playerSession,
        QuestInstance $quest,
        array         $buttons,
        int           $pressed,
        array         $param
    ) : array;

}