<?php
declare(strict_types=1);
namespace Delivery\task;

use Delivery\DeliveryAPI;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class CheckLicenseAsyncTask extends AsyncTask
{

    protected $path;

    protected $name;

    protected $check = false;

    protected $lastDate = null;

    public function __construct(string $path, string $name)
    {
        $this->path = $path;
        $this->name = $name;
        date_default_timezone_set('Asia/Seoul');
    }

    public function onRun(): void
    {
        $ip = Internet::getIP();
        $file = Internet::getURL('https://raw.githubusercontent.com/alvin0319/TEST/master/Server.yml');
        $file = yaml_parse($file);
        if (isset($file[$ip])) {
            if ($file[$ip] ['name'] === $this->name) {
                //if ((int)$file[$ip] ['last-date'] >= (int)date('Ymd')) {
                    $this->lastDate = (int)$file[$ip] ['last-date'];
                    if ($file[$ip] ['license'] === true) {
                        $this->check = true;
                    }
                //}
            }
        }
    }

    public function onCompletion(Server $server)
    {
        $plugin = DeliveryAPI::getInstance();
        if ($this->check) {
            $plugin->getLogger()->notice('플러그인이 인증되었습니다.');
        } else {
            $plugin->getLogger()->error('플러그인 인증에 실패하였습니다.');
            $plugin->getServer()->getPluginManager()->disablePlugin($plugin);
        }
    }
}