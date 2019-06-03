<?php
declare(strict_types=1);
namespace Delivery\delivery;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\Config;

class Delivery
{

    protected $player;

    protected $db;

    protected $path;

    public const MAX_ITEM_COUNT = 27;

    public function __construct(string $path, Player $player)
    {
        $this->path = $path;
        $this->db = (new Config($path . strtolower($player->getName()) . ".yml", Config::YAML, ["item" => []]))->getAll();
        $this->player = $player;
        $this->save();
    }

    public function save()
    {
        $config = new Config($this->path . strtolower($this->player->getName()) . ".yml", Config::YAML, ["item" => []]);
        $config->setAll($this->db);
        $config->save();
    }

    public function __destruct()
    {
        $this->save();
    }

    public function addItem(Item $item): bool
    {
        if (count($this->db["item"]) >= self::MAX_ITEM_COUNT) {
            return false;
        }
        $this->db["item"] [] = $item->jsonSerialize();
        $this->save();
        return true;
    }

    public function removeItem(int $slot): bool
    {
        if (!isset($this->db["item"] [$slot])) {
            return false;
        }
        unset($this->db["item"] [$slot]);
        $this->db["item"] = array_values($this->db["item"]);
        $this->save();
        return true;
    }

    public function getItem(int $slot): ?Item
    {
        if (!isset($this->db["item"] [$slot])) {
            return null;
        }
        $item = Item::jsonDeserialize($this->db["item"] [$slot]);
        return $item;
    }

    public function getAll(): array
    {
        return $this->db["item"];
    }
}