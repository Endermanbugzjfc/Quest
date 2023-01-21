<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\commands;

use CortexPE\Commando\BaseCommand;
use Endermanbugzjfc\Quest\player\PlayerSession;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class QuestCommand extends BaseCommand
{

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            return;
        }
        PlayerSession::getByPlayer($sender)->listQuests();
    }

    protected function prepare() : void
    {
        $this->setPermission("quest.quest");
        $pl = $this->getOwningPlugin();
        $this->registerSubCommand(new NPCSubcommand(
            $pl,
            "npc",
            "Spawn an NPC for a category of quest",
        ));
        $this->registerSubCommand(new DialogSubcommand(
            $pl,
            "dialog",
            "Spawn an NPC for a category of quest",
        ));
    }
}
