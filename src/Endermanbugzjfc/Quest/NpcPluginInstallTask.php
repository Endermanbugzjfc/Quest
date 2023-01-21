<?php

namespace Endermanbugzjfc\Quest;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;
use function basename;
use function dirname;
use function file_put_contents;
use function is_callable;
use function mkdir;

class NpcPluginInstallTask extends AsyncTask
{

    protected string $path;

    public function __construct(
        ?callable $callback
    )
    {
        $this->storeLocal("callback", $callback);

        $this->path = Server::getInstance()->getDataPath() . "plugins/";
    }

    public function onRun() : void
    {
        $url = "https://github.com/brokiem/SimpleNPC/releases/latest/download/SimpleNPC.phar";
        $path = $this->path . basename($url);
        $result = Internet::getURL($url);
        if ($result !== null and $result->getCode() === 200) {
            $ok = true;
            @mkdir(dirname($path));
            file_put_contents($path, $result->getBody());
        } else {
            $ok = false;
        }
        $this->setResult([$ok, $path]);
    }

    public function onCompletion() : void
    {
        $result = $this->getResult();
        $ok = (bool)$result[0];
        if ($ok) {
            $manager = Server::getInstance()->getPluginManager();
            $manager->loadPlugins(
                (string)$result[1]
            );
            $plugin = $manager->getPlugin("SimpleNPC");
            $manager->enablePlugin($plugin);
        }

        $callback = $this->fetchLocal("callback");
        if (is_callable($callback)) {
            $callback($ok);
        }
    }
}