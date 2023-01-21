<?php

namespace Endermanbugzjfc\Quest\commands;

CortexPE\Commando\args\RawStringArgument;
CortexPE\Commando\BaseSubCommand;
CortexPE\Commando\exception\ArgumentOrderException;
use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\Quest;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Server;
use RuntimeException;
SOFe\AwaitStd\Await;
use function array_values;
use function count;

class DialogSubcommand extends BaseSubCommand
{

    public function onRun(
        CommandSender $sender,
        string        $aliasUsed,
        array         $args
    ) : void
    {
        $args = array_values($args);
        Await::f2c(function () use
        (
            $args,
            $sender
        ) {
            $s = PlayerSession::getByPlayer(
                Server::getInstance()->getPlayerExact(
                    $args[1]
                )
            );
            $event = yield from  $s->awaitAttack();
            assert($event instanceof EntityDamageByEntityEvent);
            $entity = $event->getDamager();
            if (
                count($args) < 1
                or
                (
                $quest = Quest::getInstance()->quests[$args[0]][0] ?? null
                ) === null) {

                $s->getCustomizable()->onOtherError(new RuntimeException(
                    "Invalid quest category for NPC {$entity->getId()}"
                ));
                return;
            }
            $s->dialogApi(
                $quest->getCategory(),
                $entity
            );
        });
    }

    protected function prepare() : void
    {
        $this->setPermission("quest.dialog");
        try {
            $this->registerArgument(0,
                new RawStringArgument(
                    "Quest category",
                ));
            $this->registerArgument(1,
                new RawStringArgument(
                    "Player",
                ));
        } catch (ArgumentOrderException $e) {
            throw new RuntimeException($e);
        }
    }
}