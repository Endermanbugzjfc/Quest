<?php

namespace Endermanbugzjfc\Quest\commands;

use brokiem\snpc\entity\BaseNPC;
use brokiem\snpc\manager\NPCManager;
use brokiem\snpc\SimpleNPC;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use Endermanbugzjfc\Quest\NpcPluginInstallTask;
use Endermanbugzjfc\Quest\Quest;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function microtime;

class NPCSubcommand extends BaseSubCommand
{

    public function onRun(
        CommandSender $sender,
        string        $aliasUsed,
        array         $args
    ) : void
    {
        $args = array_values($args);
        if (!$sender instanceof Player) {
            return;
        }
        Await::f2c(function () use
        (
            $args,
            $sender
        ) {
            if (
                count($args) < 1
                or
                (
                $quest = Quest::getInstance()->quests[$args[0]][0] ?? null
                ) === null) {
                $sender->sendMessage(
                    TextFormat::BOLD . TextFormat::RED
                    . "Quest category doesn't exist"
                );
                return;
            }

            if (!class_exists(SimpleNPC::class)) {
                $sender->sendMessage(
                    TextFormat::BOLD . TextFormat::YELLOW
                    . "Installing SimpleNPC..."
                );
                $time = microtime(true);
                $sender->getServer()->getAsyncPool()->submitTask(
                    new NpcPluginInstallTask(yield Await::RESOLVE)
                );
                $ok = yield Await::ONCE;
                $duration = microtime(true) - $time;
                $sender->sendMessage(TextFormat::BOLD . $ok
                    ? TextFormat::GREEN . "Succeed " . TextFormat::ITALIC
                    . TextFormat::GRAY . "({$duration}s)"
                    : TextFormat::RED . "Failed"
                );
            }

            $id = NPCManager::getInstance()->spawnNPC(
                "human_snpc",
                $sender
            );
            $entity = $sender->getWorld()->getEntity($id);
            assert($entity instanceof BaseNPC);
            $entity->setNameTag("/snpc edit $id <Nametag>");
            $entity->getCommandManager()->add(
                "quest dialog \"{$quest->getCategory()}\" \"{player}\""
            );
        });
    }

    protected function prepare() : void
    {
        $this->setPermission("quest.npc");
        try {
            $this->registerArgument(0,
                new RawStringArgument(
                    "Quest category",
                    true
                ));
        } catch (ArgumentOrderException $e) {
            throw new RuntimeException($e);
        }
    }
}