<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest;

use Endermanbugzjfc\Quest\dialog\action\ButtonActionInterface;
use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\tasks\TaskInterface;
use Endermanbugzjfc\Quest\utils\Command;
use Endermanbugzjfc\Quest\utils\SetupError;
use http\Exception\RuntimeException;
use JsonException;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
ref\libNpcDialogue\form\NpcDialogueButtonData;
ref\libNpcDialogue\NpcDialogue;
SOFe\AwaitStd\Await;
use function count;
use function explode;
use function is_a;
use function mb_strlen;
use function mb_substr;
use function str_replace;

class QuestInstance
{

    protected array $tasks = [];

    public function __construct(
        protected string $category,
        protected int    $index,
        protected array  $data
    )
    {
        $tasks = $this->data["task"] ?? [];
        unset($this->data["task"]);
        foreach ($tasks as $identifier => $param) {
            foreach (Quest::getInstance()->tasks as $task) {
                if (!is_a(
                    $task,
                    TaskInterface::class,
                    true
                )) {
                    continue;
                }
                if (
                    $task
                        ::getIdentifier() ===
                    Quest
                        ::cleanIdentifiers($identifier)
                ) {
                    $this->tasks[] = new $task($param);
                }
            }
        }
    }

    public static function fromFile(
        string $category,
        int    $index,
        string $file
    ) : self
    {
        return new self($category, $index, (new Config($file))->getAll());
    }

    public function sendDialog(
        PlayerSession $playerSession,
        callable      $updateDialog,
        array         $savedData,
        Entity        $npc
    ) : bool
    {
        if (empty($savedData)) {
            $param = $this->data["dialog"] ?? null;
        } else {
            $param = $savedData;
        }
        unset($savedData);
        if ($param === null) {
            return false;
        }
        Await::f2c(/**
         * @throws JsonException
         */ function () use ($updateDialog, $npc, $playerSession, $param) {
            $std = Quest::getInstance()->getStd();
            $skip = false;

            $dialog = self::createDialog();
            $dialog->addButton(
                (new NpcDialogueButtonData())
                    ->setName(
                        $label = TextFormat::ITALIC . TextFormat::GRAY
                            . "Skip"
                    )
                    ->setText($label)
                    ->setClickHandler(function (
                        Player $player
                    ) use (&$skip) : void {
                        $skip = true;
                    })
            );

            $len = 1;
            $words = mb_strlen($param["content"]);
            while ($len <= $words) {
                if ($skip) {
                    $len = $words;
                    $skip = false;
                    continue;
                }
                yield $std->sleep(1);
                $dialog->setDialogueBody(mb_substr(
                    $param["content"],
                    0,
                    $len++
                ));
                $dialog->sendTo($playerSession->getPlayer(), $npc);
            }
            $dialog = self::createDialog();

            $buttonsRaw = $param["buttons"] ?? [];
            foreach ($buttonsRaw as $buttonRaw) {
                $button = NpcDialogueButtonData::create()
                    ->setName($buttonRaw["label"])
                    ->setText($buttonRaw["label"]);
                $dialog->addButton($button);

                $buttons[] = (function () use ($button) {
                    $button->setClickHandler(yield Await::RESOLVE);
                    yield Await::ONCE;
                });
            }
            if (empty($buttonsRaw)) { // Else client will crash
                $button = NpcDialogueButtonData::create()
                    ->setName("")
                    ->setText("");
                $button->setClickHandler(function (Player $player) : void {
                });
                $dialog->addButton($button);
            }
            $dialog->sendTo($playerSession->getPlayer(), $npc);

            while (true) {
                $generators = [];
                foreach ($buttons ?? [] as $generator) {
                    $generators[] = $generator();
                }
                if (empty($generators)) {
                    return;
                }

                [$pressed,] = yield Await::race($generators);
                $buttonRaw = $buttonsRaw[$pressed];
                unset($buttonRaw["label"]);
                foreach ($buttonRaw as $action => $actionParam) {
                    foreach (
                        Quest::getInstance()->buttonActions
                        as $buttonAction
                    ) {
                        if (
                            !is_a(
                                $buttonAction,
                                ButtonActionInterface::class,
                                true
                            )
                            or
                            Quest::cleanIdentifiers(
                                $action
                            ) !== $buttonAction::getIdentifier()
                        ) {
                            continue;
                        }
                        $buttonsRaw = $buttonAction::execute(
                            $playerSession,
                            $this,
                            $buttonsRaw,
                            $pressed,
                            $actionParam
                        );
                    }
                    if ($buttonsRaw !== $param["buttons"]) {
                        $param["buttons"] = $buttonsRaw;
                        $updateDialog($param, yield Await::RESOLVE);
                        $err = yield Await::ONCE;
                        if ($err !== null) {
                            return;
                        }
                        $this->sendDialog(
                            $playerSession,
                            $updateDialog,
                            $param,
                            $npc
                        );
                        return;
                    }
                }
            }
        });
        return true;
    }

    public function createDialog() : NpcDialogue
    {
        $param = $this->data["dialog"];
        $dialog = new NpcDialogue();
        $dialog->setNpcName($param["title"]);
        $dialog->setSceneName($param["title"]);
        $dialog->setDialogueBody($param["content"]);
        return $dialog;
    }

    /**
     * @return TaskInterface[]
     */
    public function getTasks() : array
    {
        return $this->tasks;
    }

    public function getName() : string
    {
        return $this->data["name"];
    }

    public function award(PlayerSession $playerSession) : void
    {
        $actions = $this->data["reward"];
        foreach ($actions as $action => $commands) {
            if (Quest::cleanIdentifiers($action) !== "commands") {
                throw new SetupError(
                    "Unknown reward action $action, only quest:commands is supported in this version of the plugin"
                );
            }
            $name = $playerSession->getPlayer()->getName();
            foreach ($commands as $command) {
                Server::getInstance()->dispatchCommand(
                    Command::makeConsoleCommandSender(),
                    str_replace(
                        "{player}",
                        "\"$name\"",
                        $command
                    )
                );
            }
        }
    }

    /**
     * @return string
     */
    public function getCategory() : string
    {
        return $this->category;
    }

    /**
     * @return int
     */
    public function getIndex() : int
    {
        return $this->index;
    }

    public function getDatabaseIdentifier() : string
    {
        return $this->getCategory() . ":" . $this->getIndex();
    }

    public static function databaseIdentifierToCategoryAndIndex(
        string $identifier
    ) : array
    {
        $nodes = explode(":", $identifier);
        if (count($nodes) < 2) {
            throw new RuntimeException(
                "Invalid database identifier \"$identifier\""
            );
        }
        return [$nodes[0], (int)$nodes[1]];
    }

    public static function getFromDatabaseIdentifier(
        string $identifier
    ) : ?QuestInstance
    {
        $nodes = self::databaseIdentifierToCategoryAndIndex($identifier);
        return Quest::getInstance()->quests[$nodes[0]]
            [(int)$nodes[1]] ?? null;
    }

}