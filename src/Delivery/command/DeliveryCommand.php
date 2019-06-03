<?php
declare(strict_types=1);
namespace Delivery\command;

use Delivery\DeliveryAPI;
use Delivery\EventListener;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\command\PluginIdentifiableCommand;

class DeliveryCommand extends PluginCommand implements PluginIdentifiableCommand
{

    public function __construct(string $name, string $description)
    {
        parent::__construct($name, DeliveryAPI::getInstance());
        $this->setDescription($description);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        //if ($sender instanceof Player)
        EventListener::getInstance()->sendDeliveryUI($sender);
        return true;
    }
}