<?php
declare(strict_types=1);
namespace Delivery\lang;

use Delivery\DeliveryAPI;
use pocketmine\utils\Config;

class PluginLang
{

    protected $language = "eng";

    protected $plugin;

    protected $config;

    protected $prefix = "";

    protected $availableLanguage = [
        "eng" => "eng",
        "kor" => "kor"
    ];

    public function __construct(DeliveryAPI $plugin, Config $config)
    {
        $this->plugin = $plugin;
        $this->config = $config;
    }

    public function init()
    {
        if (isset($this->availableLanguage[$this->plugin->getServer()->getLanguage()->getLang()])) {
            $this->language = $this->plugin->getServer()->getLanguage()->getLang();
        } else {
            $this->language = "eng";
        }
        $this->prefix = $this->translateString('delivery-prefix');
        $this->plugin->getLogger()->notice($this->translateString('init-success'));
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function translateString(string $string, bool $prefix = false): string
    {
        if ($this->getConfig()->getNested($this->language . "-" . $string, false) !== false){
            if ($prefix) {
                return $this->prefix . $this->getConfig()->getNested($this->language . "-" .  $string);
            }
            return $this->getConfig()->getNested($this->language . "-" . $string);
        }
        return '';
    }
}