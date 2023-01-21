<?php
declare(strict_types=1);

namespace Endermanbugzjfc\Quest;

use pocketmine\event\EventPriority;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
poggit\libasynql\DataConnector;
poggit\libasynql\libasynql;
SOFe\AwaitStd\Await;

class Database
{

    protected static ?self $instance = null;

    protected DataConnector $db;

    public function __construct(PluginBase $plugin)
    {
        self::$instance = $this;

        $this->saveDefaultSettings();
        $this->createDb($plugin);

        Await::g2c(Quest::getInstance()->getStd()->awaitEvent(
            PluginDisableEvent::class,
            fn(PluginDisableEvent $event) => $event->getPlugin() === $plugin,
            false,
            EventPriority::MONITOR,
            false
        ), function () : void {
            $this->close();
        });
    }

    public function close()
    {
        $this->getDb()->close();
        self::$instance = null;
    }

    protected function saveDefaultSettings() : void
    {
        Quest::getInstance()->saveResource("database.yml");
    }

    protected function createDb(PluginBase $plugin) : void
    {
        $this->db = libasynql::create(
            $plugin,
            (new Config(
                $plugin->getDataFolder() . "database.yml",
                Config::YAML,
            ))->getAll(),
            ["sqlite" => "sql/sqlite.sql", "mysql" => "sql/mysql.sql"]
        );
    }

    /**
     * @return DataConnector
     */
    public function getDb() : DataConnector
    {
        return $this->db;
    }

    /**
     * @return Database|null
     */
    public static function getInstance() : ?Database
    {
        return self::$instance;
    }

}