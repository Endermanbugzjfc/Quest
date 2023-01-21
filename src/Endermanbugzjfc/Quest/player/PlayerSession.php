<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest\player;

use Closure;
use Endermanbugzjfc\Quest\Database;
use Endermanbugzjfc\Quest\Quest;
use Endermanbugzjfc\Quest\QuestInstance;
use Endermanbugzjfc\Quest\tasks\ProgressTrackerInterface;
use Endermanbugzjfc\Quest\tasks\TaskInterface;
use Endermanbugzjfc\Quest\utils\ItemUtils;
use Endermanbugzjfc\Quest\utils\SetupError;
use Exception;
use Generator;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use poggit\libasynql\SqlError;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitStd\DisposeException;
use function bin2hex;
use function in_array;
use function is_callable;
use function json_decode;
use function json_encode;
use function spl_object_id;

class PlayerSession
{

    public const DATA_VERSION = 1;

    /**
     * @var PlayerSession[]
     */
    protected static array $pool = [];

    /**
     * @var array<string, array>|null
     */
    protected ?array $progress = null;

    /**
     * @var array<string, array>|null
     */
    protected ?array $dialogs = null;

    /**
     * @var QuestInstance[]
     */
    protected array $completed = [];

    protected PlayerCustomizable $customizable;
    protected ?EntityDamageByEntityEvent $lastAttack;

    protected static function add(self $s) : void
    {
        self::$pool[spl_object_id($s->getPlayer())] = $s;
    }

    public static function getByPlayer(Player $p) : ?self
    {
        return self::$pool[spl_object_id($p)] ?? null;
    }

    public static function creationListener(Plugin $plugin) : void
    {
        if (isset(self::$creationListenerOverwrite)) {
            (self::$creationListenerOverwrite)();
        }

        Await::f2c(function () use
        (
            $plugin
        ) : Generator {
            while (true) {
                [, $event] = yield Await::race([
                    Quest::getInstance()->getStd()->awaitEvent(
                        PlayerLoginEvent::class,
                        fn() => true,
                        false,
                        EventPriority::MONITOR,
                        false
                    ),
                    Quest::getInstance()->getStd()->awaitEvent(
                        PluginDisableEvent::class,
                        fn(PluginDisableEvent $event) => $event->getPlugin() === $plugin,
                        false,
                        EventPriority::MONITOR,
                        false
                    )
                ]);
                if (!$event instanceof PlayerLoginEvent) {
                    // Coroutine is interrupted
                    return;
                }

                new self($event->getPlayer());
            }
        });
    }

    /**
     * @var Closure The closure should has 0 arguments and returns nothing
     */
    public static Closure $creationListenerOverwrite;

    public function __construct(
        public Player $player,
    )
    {
        self::add($this);
        $this->customizable = new PlayerCustomizable($this);
        Await::f2c(function () {
            while (true) {
                try {
                    [, $event] = yield Await::race([
                        Quest::getInstance()->getStd()->awaitEvent(
                            PlayerInteractEvent::class,
                            fn(PlayerInteractEvent $event) => $event->getPlayer() === $this->getPlayer(),
                            false,
                            EventPriority::MONITOR,
                            false,
                            $this->getPlayer()
                        ),
                        $this->awaitAttack()
                    ]);
                } catch (DisposeException) {
                    return;
                }
                if ($event instanceof EntityDamageByEntityEvent) {
                    $this->lastAttack = $event;
                }
                if (!$event instanceof PlayerInteractEvent) {
                    continue;
                }
                $tag = $event->getItem()->getNamedTag()->getCompoundTag(
                    Quest::NAMED_TAG_IDENTIFIER
                )?->getListTag("box");
                if ($tag !== null) {
                    $event->cancel();
                    $inventory = $this->getPlayer()->getInventory();
                    foreach ($tag->getValue() as $itemTag) {
                        if (!$itemTag instanceof CompoundTag) {
                            $this->getCustomizable()->onOtherError(
                                new RuntimeException(
                                    "Box item has invalid NBT, corrupted data?"
                                )
                            );
                            break;
                        }
                        $item = Item::nbtDeserialize($itemTag);
                        ItemUtils::dropOrAddToInventory(
                            $this->getPlayer(),
                            $item
                        );
                    }
                    $inventory->setItemInHand(
                        VanillaBlocks::AIR()->asItem()
                    );
                }
            }
        });
        $this->reloadFromDatabase();
    }

    /**
     * @return Player
     */
    public function getPlayer() : Player
    {
        return $this->player;
    }

    protected function softClose()
    {
        // TODO: Mutex
        foreach ($this->progressTrackers as $tracker) {
            $tracker->stopProgressTracking($this);
        }
        $this->progress = [];
        $this->dialogs = [];
        $this->completed = [];
    }

    protected function close() : void
    {
        $this->softClose();
        unset(self::$pool[spl_object_id($this->getPlayer())]);
    }

    /**
     * @throws SetupError
     */
    public function reloadFromDatabase() : void
    {
        $promise = $this->getCustomizable()->onWaitingForDatabase();

        $this->softClose();
        Database::getInstance()->getDb()->executeSelect(
            "progress.get_all",
            ["uuid" => $this->getUniqueIdBytes()],
            function (array $rows) use
            (
                $promise
            ) {
                $promise();
                foreach ($rows as $row) {
                    if ((int)($row["version"] ?? null) !== self::DATA_VERSION) {
                        $this->getCustomizable()->onOtherError(
                            new SetupError("Please update your " . Quest::getInstance()->getName() . " plugin")
                        );
                        continue;
                    }
                    $quest = QuestInstance::getFromDatabaseIdentifier(
                        $questIdentifier = $row["quest_identifier"]
                    );
                    if ($quest === null) {
                        [$category, $index] = QuestInstance
                            ::databaseIdentifierToCategoryAndIndex(
                                $questIdentifier
                            );
                        $this->getCustomizable()->onOtherError(
                            new Exception(
                                "Quest $questIdentifier no longer exists, but there are still its progress records in the database, please run '/quest reset \"{$this->getPlayer()->getName()}\" $category $index'"
                            )
                        );
                        continue;
                    }
                    $tasksData = isset($row["task_data"])
                        ? json_decode(
                            $row["task_data"],
                            true
                        )
                        : [];
                    foreach ($quest->getTasks() as $task) {
                        $this->setTaskProgress(
                            $quest,
                            $task,
                            $tasksData[$task::getIdentifier()]
                            ?? []
                        );
                        $this->startProgressTracker($quest, $task);
                    }
                    $this->setDialogData($quest,
                        isset($row["dialog_data"]) ? json_decode(
                            $row["dialog_data"],
                            true
                        ) : []
                    );
                    if ($row["completed"]) {
                        $this->completed[] = $quest;
                    }
                }
            },
            fn(SqlError $err) => $this
                    ->getCustomizable()
                    ->onDatabaseError($err)
                and $promise()
        );
    }

    /**
     * @var ProgressTrackerInterface[]
     */
    protected array $progressTrackers = [];

    public function assignQuest(
        QuestInstance $quest,
        ?callable     $callback = null
    ) : void
    {
        Await::f2c(function () use
        (
            $callback,
            $quest
        ) {
            foreach ($this->getQuests() as $sQuest) {
                if ($sQuest->getCategory() === $quest->getCategory()) {
                    $this->removeQuest($sQuest, yield Await::RESOLVE);
                    $err = yield Await::ONCE;
                    if ($err !== null) {
                        $callback($err);
                        return;
                    }
                }
            }
            $identifier = $quest->getDatabaseIdentifier();
            Database::getInstance()->getDb()->executeChange(
                "progress.create",
                [
                    "uuid" => $this->getUniqueIdBytes(),
                    "quest_identifier" => $identifier,
                    "version" => self::DATA_VERSION
                ],
                function () use
                (
                    $callback,
                    $quest
                ) {
                    foreach ($quest->getTasks() as $task) {
                        $this->setTaskProgress($quest, $task, []);
                        $this->startProgressTracker($quest, $task);
                    }
                    if (is_callable($callback)) {
                        $callback();
                    }
                },
                function (SqlError $err) use
                (
                    $callback
                ) {
                    $this->getCustomizable()->onDatabaseError($err);
                    if (is_callable($callback)) {
                        $callback($err);
                    }
                }
            );
        });
    }

    public function removeQuest(
        QuestInstance $quest,
        ?callable $callback = null
    ) : void
    {
        $identifier = $quest->getDatabaseIdentifier();
        Database::getInstance()->getDb()->executeChange(
            "progress.remove",
            ["uuid" => $this->getUniqueIdBytes(), "quest_identifier" => $identifier],
            function () use
            (
                $quest,
                $callback
            ) {
                $this->flushQuestData($quest);
                if (is_callable($callback)) {
                    $callback();
                }
            },
            function (SqlError $err) use
            (
                $callback
            ) {
                $this->getCustomizable()->onDatabaseError($err);
                if (is_callable($callback)) {
                    $callback($err);
                }
            }
        );
    }

    protected function updateProgress(QuestInstance $quest) : void
    {
        foreach ($quest->getTasks() as $task) {
            $data[$task::getIdentifier()] = $this->getTaskProgress(
                $quest,
                $task
            );
        }
        Database::getInstance()->getDb()->executeInsert(
            "progress.set_task",
            [
                "uuid" => $this->getUniqueIdBytes(),
                "quest_identifier" => $quest->getDatabaseIdentifier(),
                "task_data" => json_encode($data ?? [])
            ],
            null,
            fn(SqlError $err) => $this
                ->getCustomizable()
                ->onDatabaseError($err)
        );
    }

    public function listQuests() : void
    {
        $this->getCustomizable()->listQuests();
    }

    /**
     * @var array<int|string, callable>
     */
    protected array $stoppers = [];

    public function newStopper(int|string $identifier) : Generator
    {
        $this->stoppers[$identifier] = yield Await::RESOLVE;
        yield Await::ONCE;
    }

    /**
     * @throws SetupError
     */
    public function resolveStopper(int|string $identifier) : void
    {
        $stopper = $this->stoppers[$identifier] ?? null;
        unset($this->stoppers[$identifier]);
        if ($stopper === null) {
            return;
        }
        $stopper();
    }

    /**
     * @return QuestInstance[]
     */
    public function getQuests() : array
    {
        foreach ($this->progress as $category => $categoryData) {
            foreach ($categoryData as $index => $indexData) {
                $quest = Quest::getInstance()->quests[$category]
                    [$index]
                    ?? null;
                if ($quest === null) {
                    continue;
                }
                $quests[] = $quest;
            }
        }
        return $quests ?? [];
    }

    public function getTaskProgress(
        QuestInstance $quest,
        TaskInterface $task
    ) : ?array
    {
        return $this->progress[$quest->getCategory()]
            [$quest->getIndex()]
            [$task::getIdentifier()]
            ?? null;
    }

    protected function setTaskProgress(
        QuestInstance $quest,
        TaskInterface $task,
        array         $progress
    ) : void
    {
        $this->progress[$quest->getCategory()]
        [$quest->getIndex()]
        [$task::getIdentifier()] = $progress;
    }

    protected function getDialogData(QuestInstance $quest) : ?array
    {
        return $this->dialogs[$quest->getCategory()]
            [$quest->getIndex()]
            ?? null;
    }

    protected function setDialogData(
        QuestInstance $quest,
        array         $data
    ) : void
    {
        $this->dialogs[$quest->getCategory()]
        [$quest->getIndex()] = $data;
    }

    public function flushQuestData(QuestInstance $quest)
    {

        unset(
            $this->progress[$quest->getCategory()]
            [$quest->getIndex()]
        );
        unset(
            $this->dialogs[$quest->getCategory()]
            [$quest->getIndex()]
        );
        foreach ($this->completed as $index => $completed) {
            if ($completed === $quest) {
                unset($this->completed[$index]);
            }
        }
    }

    /**
     * @return PlayerCustomizable
     */
    public function getCustomizable() : PlayerCustomizable
    {
        return $this->customizable;
    }

    public function setQuestCompleted(
        QuestInstance $quest,
        ?callable $callback = null
    )
    {
        Database::getInstance()->getDb()->executeChange(
            "progress.set_completed",
            [
                "uuid" => $this->getUniqueIdBytes(),
                "quest_identifier" => $quest->getDatabaseIdentifier(),
                "version" => self::DATA_VERSION
            ],
            function () use
            (
                $quest,
                $callback
            ) {
                $this->completed[] = $quest;
                if (is_callable($callback)) {
                    $callback();
                }
            },
            function (SqlError $err) use
            (
                $callback
            ) {
                $this->getCustomizable()->onDatabaseError($err);
                $callback($err);
            }
        );
    }

    public function startProgressTracker(
        QuestInstance $quest,
        TaskInterface $task
    ) : bool
    {
        if (!$task instanceof ProgressTrackerInterface) {
            return false;
        }
        $this->progressTrackers[] = $task;

        $task->startProgressTracking(
            $this,
            function (array $progress) use
            (
                $task,
                $quest
            ) {
                $this->setTaskProgress($quest, $task, $progress);
                $this->updateProgress($quest);
            },
            $this->getTaskProgress($quest, $task) ?? []
        );
        return true;
    }

    public function dialogApi(
        string $questCategory,
        Entity $npc
    ) : void
    {
        Await::f2c(function () use
        (
            $npc,
            $questCategory
        ) {
            foreach ($this->getQuests() as $quest) {
                if ($quest->getCategory() !== $questCategory) {
                    continue;
                }
                $cdd = $quest;
                foreach ($quest->getTasks() as $task) {
                    if (!$task->hasCompleted(
                        $this,
                        $this->getTaskProgress($quest, $task) ?? []
                    )) {
                        break 2;
                    }
                }
                if (!in_array($quest, $this->completed, true)) {
                    $this->setQuestCompleted($quest, yield Await::RESOLVE);
                    yield Await::ONCE;
                    $quest->award($this);
                }
                $cdd = Quest::getInstance()->quests[$questCategory]
                    [$quest->getIndex() + 1]
                    ?? null;
                if ($cdd === null) {
                    return;
                }
            }
            if (!isset($cdd)) {
                $cdd = Quest::getInstance()->quests[$questCategory]
                    [0]
                    ?? null;
                if (!isset($cdd)) {
                    return;
                }
            }
            $cdd->sendDialog(
                $this,
                function (
                    array     $data,
                    ?callable $callback = null
                ) use
                (
                    $cdd
                ) {
                    Await::f2c(function () use
                    (
                        $data,
                        $callback,
                        $cdd
                    ) {
                        if (!in_array(
                            $cdd,
                            $this->getQuests(),
                            true
                        )) {
                            $this->assignQuest($cdd, yield Await::RESOLVE);
                            $err = yield Await::ONCE;
                            if ($err !== null) {
                                $callback($err);
                                return;
                            }
                        }
                        $this->setDialogData($cdd, $data);
                        Database::getInstance()->getDb()->executeChange(
                            "progress.set_dialog",
                            [
                                "uuid" => $this->getUniqueIdBytes(),
                                "quest_identifier" => $cdd
                                    ->getDatabaseIdentifier(),
                                "dialog_data" => json_encode(
                                    $this->getDialogData($cdd)
                                )
                            ],
                            fn() => $callback(),
                            fn(SqlError $err) => $this
                                    ->getCustomizable()
                                    ->onDatabaseError($err)
                                and $callback($err)
                        );
                    });
                },
                $this->getDialogData($cdd) ?? [],
                $npc
            );
        });
    }

    protected function getUniqueIdBytes() : string
    {
        return bin2hex($this->getPlayer()->getUniqueId()->getBytes());
    }

    /**
     * @return EntityDamageByEntityEvent|null
     */
    public function getLastAttack() : ?EntityDamageByEntityEvent
    {
        return $this->lastAttack;
    }

    public function awaitAttack() : Generator
    {
        return Quest::getInstance()->getStd()->awaitEvent(
            EntityDamageByEntityEvent::class,
            fn(EntityDamageByEntityEvent $event) => $event->getDamager() === $this->getPlayer(),
            false,
            EventPriority::MONITOR,
            true,
            $this->getPlayer()
        );
    }

}