<?php

declare(strict_types=1);

namespace Endermanbugzjfc\Quest;

CortexPE\Commando\exception\HookAlreadyRegistered;
CortexPE\Commando\PacketHooker;
use Endermanbugzjfc\Quest\commands\QuestCommand;
use Endermanbugzjfc\Quest\dialog\action\AssignButtonAction;
use Endermanbugzjfc\Quest\dialog\action\ButtonRemoveButtonAction;
use Endermanbugzjfc\Quest\dialog\action\GiveBoxButtonAction;
use Endermanbugzjfc\Quest\player\PlayerSession;
use Endermanbugzjfc\Quest\tasks\CollectTask;
use Endermanbugzjfc\Quest\tasks\InteractTask;
use Endermanbugzjfc\Quest\tasks\ObtainTask;
use http\Exception\RuntimeException;
use pocketmine\plugin\PluginBase;
ref\libNpcDialogue\libNpcDialogue;
SOFe\AwaitStd\AwaitStd;
use function array_diff;
use function array_values;
use function scandir;
use function str_replace;
use function strtolower;
use function trim;
use const E_ERROR;

class Quest extends PluginBase
{

    /**
     * WARNING: Changing this might break the plugin
     */
    public const IDENTIFIERS_FALLBACK_PREFIX = "quest:";
    public const NAMED_TAG_IDENTIFIER = "Quest";

    protected AwaitStd $std;

    protected function onEnable() : void
    {
        $this->saveDefaultQuests();

        if (!libNpcDialogue::isRegistered()) {
            libNpcDialogue::register($this);
        }
        if (!PacketHooker::isRegistered()) {
            try {
                PacketHooker::register($this);
            } catch (HookAlreadyRegistered $err) {
                throw new RuntimeException(
                    "Failed to register Commando Packet Hooker",
                    E_ERROR,
                    $err
                );
            }
        }
        $this->getServer()->getCommandMap()->register(
            $this->getName(),
            new QuestCommand(
                $this,
                "quest",
                "List my quests",
                ["quests"]
            )
        );
        $this->loadQuests();
        $this->std = AwaitStd::init($this);
        $this->getLogger()->info("Initializing database, this might take half to a minute...");

        new Database($this);
        $db = Database::getInstance()->getDb();
        $db->executeGeneric("init.player_quest_progress");
        $db->waitAll();

        PlayerSession::creationListener($this);
    }

    /**
     * @var array<string, string>
     * @phpstan-var array<string, class-string<TaskInterface>>
     */
    public array $tasks = [
        CollectTask::class,
        ObtainTask::class,
        InteractTask::class
    ];

    /**
     * @var QuestInstance[][]
     */
    public array $quests = [];

    /**
     * @var array<string, string>
     * @phpstan-var array<string, class-string<ButtonActionInterface>>
     */
    public array $buttonActions = [
        AssignButtonAction::class,
        ButtonRemoveButtonAction::class,
        GiveBoxButtonAction::class
    ];

    protected function saveDefaultQuests() : void
    {
        foreach ($this->getResources() as $path => $dir) {
            if ($dir->isDir() or !str_starts_with($path, "quests/")) {
                continue;
            }
            $this->saveResource($path);
        }
    }

    public function loadQuests() : void
    {
        foreach (array_diff(
                     scandir($path = $this->getDataFolder() . "quests/"),
                     [".", ".."]
                 ) as $dir) {
            foreach (array_values(array_diff(
                scandir($path . $dir),
                [".", ".."]
            )) as $index => $file) {
                $this->quests[$dir][] = QuestInstance::fromFile(
                    $dir,
                    $index,
                    $path . $dir . "/" . $file
                );
            }
        }
    }

    public static function cleanIdentifiers(string $identifier) : string
    {
        return strtolower(str_replace(
            [" ", self::IDENTIFIERS_FALLBACK_PREFIX],
            ["_", ""],
            trim($identifier)
        ));
    }

    /**
     * @return AwaitStd
     */
    public
    function getStd() : AwaitStd
    {
        return $this->std;
    }

    protected
    static self $instance;

    protected function onLoad() : void
    {
        self::$instance = $this;
    }

    /**
     * @return Quest
     */
    public static function getInstance() : Quest
    {
        return self::$instance;
    }

}