<?php
declare(strict_types=1);
namespace Delivery;

use Delivery\command\DeliveryCommand;
use Delivery\delivery\Delivery;
use Delivery\delivery\OfflineDelivery;
use Delivery\lang\PluginLang;
use Delivery\task\CheckVersionAsyncTask;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class DeliveryAPI extends PluginBase
{

    /** @var Delivery[] */
    protected $players = [];

    /** @var DeliveryAPI|null */
    private static $instance = null;

    /** @var PluginLang */
    protected $lang;

    public function onLoad()
    {
        self::$instance = $this;
    }

    public function onEnable()
    {
        @mkdir($this->getDataFolder() . "players/");
        $this->saveResource('config.yml');
        $lang = new PluginLang($this, $this->getConfig());
        $lang->init();
        $this->lang = $lang;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getServer()->getCommandMap()->register("delivery", new DeliveryCommand($lang->translateString('command-name'), $lang->translateString('command-description')));
        /**
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (int $unused) : void
        {
            $this->getServer()->getAsyncPool()->submitTask(new CheckLicenseAsyncTask($this->getDataFolder(), $this->getDescription()->getName()));
        }), 1200 * 30);
         */
        $this->getServer()->getAsyncPool()->submitTask(new CheckVersionAsyncTask($this->getDescription()->getVersion(), $this->getConfig()->getNested("force-update", true)));
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getDelivery(Player $player): ?Delivery
    {
        return $this->players[strtolower($player->getName())] ?? null;
    }

    public function getOfflineDelivery(string $player): ?OfflineDelivery
    {

        if (!file_exists($this->getDataFolder() . "players/" . strtolower($player) . ".yml")) {
            return null;
        }
        $offlineDelivery = new OfflineDelivery($this->getDataFolder() . "players/", $player);
        return $offlineDelivery;
    }

    public function getLanguage(): PluginLang
    {
        return $this->lang;
    }

    public function addPlayer(Player $player)
    {
        $this->players[strtolower($player->getName())] = new Delivery($this->getDataFolder() . "players/", $player);
    }

    public function removePlayer(Player $player)
    {
        unset($this->players[strtolower($player->getName())]);
    }
}