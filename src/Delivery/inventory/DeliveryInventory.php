<?php
declare(strict_types=1);
namespace Delivery\inventory;

use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\CustomInventory;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;
use pocketmine\tile\Chest;

class DeliveryInventory extends ChestInventory
{
    /**

    public function onOpen(Player $who): void
    {
        BaseInventory::onOpen($who);
    }

    public function getName(): string
    {
        return '';
    }

    public function getDefaultSize(): int
    {
        return 27;
    }

    public function getNetworkType(): int
    {
        return WindowTypes::CONTAINER;
    }
     */
}