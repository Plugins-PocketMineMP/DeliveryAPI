<?php
declare(strict_types=1);
namespace Delivery;

use Delivery\command\DeliveryCommand;
use Delivery\delivery\Delivery;
use Delivery\delivery\OfflineDelivery;
use Delivery\inventory\DeliveryInventory;
use Delivery\lang\PluginLang;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\Player;
use pocketmine\tile\Chest;

class EventListener implements Listener
{

    /** @var Vector3[] */
    protected $chest = [];

    private static $instance = null;

    public const FORM_ID_MAIN = 163512;

    public const FORM_ID_SEND_UI = 12634;

    public const FORM_ID_HOTTIME = 17412;

    /** @var PluginLang */
    protected $lang;

    public function __construct()
    {
        self::$instance = $this;
        $this->lang = DeliveryAPI::getInstance()->getLanguage();
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function onTransaction(InventoryTransactionEvent $event)
    {
        foreach ($event->getTransaction()->getActions() as $action) {
            if ($action instanceof SlotChangeAction) {
                if ($action->getInventory() instanceof DeliveryInventory) {
                    $item = $action->getSourceItem();
                    $player = $event->getTransaction()->getSource();
                    $dev = DeliveryAPI::getInstance()->getDelivery($player);
                    if ($dev instanceof Delivery) {
                        if (!$dev->getItem($action->getSlot()) instanceof Item) {
                            $event->setCancelled();
                        }
                        if ($dev->getItem($action->getSlot()) instanceof Item) {
                            $dev->removeItem($action->getSlot());
                        }
                    }
                }
            }
        }
    }

    public function sendDeliveryWindow(Player $player)
    {
        $pos = $player->add(0, 5, 0)->floor();
        $block = BlockFactory::get(BlockIds::CHEST);
        $x = (int)$pos->getX();
        $y = (int)$pos->getY() > 0 ? (int)$pos->getY() : 0;
        $z = (int)$pos->getZ();
        $block->x = $x;
        $block->y = $y;
        $block->z = $z;
        $vec = new Vector3($x, $y, $z);
        $this->chest[$player->getName()] = $vec;
        $block->level = $player->getLevel();
        $block->getLevel()->sendBlocks([$player], [$block]);
        $nbt = Chest::createNBT($block->asVector3());
        $nbt->setString(Chest::TAG_CUSTOM_NAME, $player->getName() . $this->lang->translateString('delivery-boxname'));
        $chest = Chest::createTile(Chest::CHEST, $player->getLevel(), $nbt);
        $inv = new DeliveryInventory($chest);
        $delivery = DeliveryAPI::getInstance()->getDelivery($player);

        $count = 0;
        foreach ($delivery->getAll() as $slot => $itemData) {
            $item = Item::jsonDeserialize($itemData);
            $inv->setItem($count, $item);
            $count++;
        }
        $player->addWindow($inv);
    }

    public function onClose(InventoryCloseEvent $event)
    {
        $player = $event->getPlayer();
        $inv = $event->getInventory();
        if ($inv instanceof DeliveryInventory) {
            $block = BlockFactory::get(BlockIds::AIR);
            $block->x = $this->chest[$player->getName()]->x;
            $block->y = $this->chest[$player->getName()]->y;
            $block->z = $this->chest[$player->getName()]->z;
            $block->level = $player->getLevel();
            $block->level->sendBlocks([$player], [$block]);
            unset($this->chest[$player->getName()]);

            /**
            $count = 0;
            $delivery = DeliveryAPI::getInstance()->getDelivery($player);
            foreach ($delivery->getAll() as $itemData) {
                $delivery->removeItem($count);
                $count++;
            }
            foreach ($inv->getContents(false) as $item) {
                $delivery->addItem($item);
            }
            $delivery->save();
             */
        }
    }

    public function sendDeliveryUI(Player $player): bool
    {
        $encode = [
            "type" => "form",
            "title" => $this->translateUILang('basetitle', false),
            "content" => $this->translateUILang('basecontent', false),
            "buttons" => [
                [
                    "text" => $this->translateUILang('button-exit', false)
                ],
                [
                    "text" => $this->translateUILang('button-send', false)
                ],
                [
                    "text" => $this->translateUILang('button-receive', false)
                ]
            ]
        ];
        if ($player->isOp()) {
            $encode["buttons"] [] = ["text" => $this->translateUILang('button-hottime', false)];
        }
        $packet = new ModalFormRequestPacket();
        $packet->formId = self::FORM_ID_MAIN;
        $packet->formData = json_encode($encode);
        return $player->sendDataPacket($packet);
    }

    public function sendDeliverySendUI(Player $player): bool
    {
        $encode = [
            "type" => "custom_form",
            "title" => $this->translateUILang('send-title', false),
            "content" => [
                [
                    "type" => "input",
                    "text" => $this->translateUILang('send-name', false)
                ]
            ]
        ];
        $packet = new ModalFormRequestPacket();
        $packet->formId = self::FORM_ID_SEND_UI;
        $packet->formData = json_encode($encode);
        return $player->sendDataPacket($packet);
    }

    public function sendHottimeUI(Player $player): bool
    {
        $encode = [
            "type" => "custom_form",
            "title" => $this->lang->translateString('delivery-hottime-title', false),
            "content" => [
                [
                    "type" => "input",
                    "text" => $this->lang->translateString('delivery-hottime-name', false)
                ]
            ]
        ];
        $packet = new ModalFormRequestPacket();
        $packet->formId = self::FORM_ID_HOTTIME;
        $packet->formData = json_encode($encode);
        return $player->sendDataPacket($packet);
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event)
    {
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        if ($packet instanceof ModalFormResponsePacket) {
            $id = $packet->formId;
            $data = json_decode($packet->formData, true);
            if ($id === self::FORM_ID_MAIN) {
                switch ($data) {
                    case 0:
                        break;
                    case 1:
                        $this->sendDeliverySendUI($player);
                        break;
                    case 2:
                        $this->sendDeliveryWindow($player);
                        break;
                    case 3:
                        if (!$player->isOp()) {
                            break;
                        }
                        $this->sendHottimeUI($player);
                        break;
                    default:
                        break;
                }
            }
            if ($id === self::FORM_ID_SEND_UI) {
                if (!isset($data[0])) {
                    $player->sendMessage($this->translateMessageLang("invalidname"));
                    return;
                }
                if (strtolower($data[0]) === strtolower($player->getName())) {
                    $player->sendMessage($this->translateMessageLang('sametarget'));
                    return;
                }
                $target = DeliveryAPI::getInstance()->getServer()->getPlayerExact(strtolower($data[0]));
                if ($target instanceof Player) {
                    $delivery = DeliveryAPI::getInstance()->getDelivery($target);
                    if (!$delivery instanceof Delivery) {
                        $player->sendMessage($this->translateMessageLang('invalidname'));
                        return;
                    }
                    $hand = $player->getInventory()->getItemInHand();
                    if ($hand->getId() === 0) {
                        $player->sendMessage($this->translateMessageLang('invaliditem'));
                        return;
                    }
                    $delivery->addItem($hand);
                    $player->getInventory()->removeItem($hand);
                    $player->sendMessage($this->translateMessageLang('success'));
                } else {
                    $delivery = DeliveryAPI::getInstance()->getOfflineDelivery(strtolower($data[0]));
                    if (!$delivery instanceof OfflineDelivery) {
                        $player->sendMessage($this->translateMessageLang('invalidname'));
                        return;
                    }
                    $hand = $player->getInventory()->getItemInHand();
                    if ($hand->getId() === 0) {
                        $player->sendMessage($this->translateMessageLang('invaliditem'));
                        return;
                    }
                    $delivery->addItem($hand);
                    $player->getInventory()->removeItem($hand);
                    $player->sendMessage($this->translateMessageLang('success'));
                }
                if ($target instanceof Player) {
                    $target->sendMessage($this->translateMessageLang('target'));
                }
            }
            if ($id === self::FORM_ID_HOTTIME) {
                if (!isset($data[0]) or !is_numeric($data[0])) {
                    $player->sendMessage($this->translateMessageLang('invalidcount'));
                    return;
                }
                $hand = $player->getInventory()->getItemInHand();
                if ($hand->getId() === 0) {
                    $player->sendMessage($this->translateMessageLang('invaliditem'));
                    return;
                }
                $item = clone $hand;
                $item->setCount((int)$data[0]);
                foreach (DeliveryAPI::getInstance()->getServer()->getOnlinePlayers() as $onlinePlayer) {
                    $dev = DeliveryAPI::getInstance()->getDelivery($onlinePlayer);
                    if ($dev instanceof Delivery) {
                        $dev->addItem($item);
                    }
                }
                DeliveryAPI::getInstance()->getServer()->broadcastMessage($this->translateMessageLang('broadcast'));
            }
        }
    }

    public function translateUILang(string $name, bool $prefix = true): string
    {
        if ($this->lang->translateString('delivery-ui-' . $name, $prefix) !== "") {
            return $this->lang->translateString('delivery-ui-' . $name, $prefix);
        }
        return '';
    }

    public function translateMessageLang(string $name, bool $prefix = true): string
    {
        if ($this->lang->translateString('delivery-message-' . $name, $prefix) !== "") {
            return $this->lang->translateString('delivery-message-' . $name, $prefix);
        }
        return '';
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        DeliveryAPI::getInstance()->addPlayer($player);
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        DeliveryAPI::getInstance()->removePlayer($player);
    }
}