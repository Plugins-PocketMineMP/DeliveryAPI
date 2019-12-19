<?php
declare(strict_types=1);
namespace Delivery\task;

use Delivery\DeliveryAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class CheckVersionAsyncTask extends AsyncTask
{

    protected $needUpdate = false;

    protected $forceUpdate;

    protected $message = null;

    protected $version;

    public function __construct(string $version, bool $forceUpdate)
    {
        $this->version = $version;
        $this->forceUpdate = $forceUpdate;
    }

    public function onRun(): void
    {
        $url = Internet::getURL("https://raw.githubusercontent.com/Plugins-PocketMineMP/DeliveryAPI/master/update.json");
        if ($url !== false) {
            $json = json_decode($url, true);
            $lastVersion = $json['version'];
            if ($this->version !== $lastVersion) {
                $this->needUpdate = true;
                $this->message = $json['changelog'] [$lastVersion] ['message'];
                if ($json['changelog'] [$lastVersion] ['force-update']) {
                    $this->forceUpdate = true;
                }
            }
        }
    }

    public function onCompletion(Server $server): void
    {
        $plugin = DeliveryAPI::getInstance();
        if ($this->needUpdate) {
            $plugin->getLogger()->notice($plugin->getLanguage()->translateString("need-update"));
            if ($this->forceUpdate) {
                $plugin->getLogger()->notice($plugin->getLanguage()->translateString("need-update-force"));
                $ref = new \ReflectionClass(PluginBase::class);
                $property = $ref->getProperty('file');
                $property->setAccessible(true);
                $this->removeAll($property->getValue($plugin));
                $file = file_get_contents('https://raw.githubusercontent.com/alvin0319/DeliveryAPI/master/DeliveryAPI.phar');
                file_put_contents($server->getPluginPath() . "DeliveryAPI.phar", $file);
                $server->shutdown();
            }
        } else {
            $plugin->getLogger()->notice($plugin->getLanguage()->translateString("not-need-update"));
        }
    }

    public function removeAll(string $path): void
    {
        if (substr($path, -1) !== "/") {
            $path .= "/";
        }
        
        if (is_dif($path)) {
            foreach (array_diff(scandir($path), [".", ".."]) as $file) {
                $realPath = $path . $file;
                if (file_exists($file)) {
                    if (is_file($realPath)) {
                        unlink($realPath);
                    } elseif (is_dir($realPath)) {
                        $this->removeAll($realPath);
                    } else {
                        continue;
                    }
                }
            }
        } else {
            unlink($realPath);
        }
    }
}
