<?php

declare(strict_types=1);

namespace halloftop;

use pocketmine\plugin\PluginBase;
use halloftop\command\HallTopCommand;
use halloftop\npc\HallTopManager;

final class Loader extends PluginBase {

    protected function onEnable(): void {
        $this->saveDefaultConfig();

        HallTopManager::getInstance()->init($this);

        $this->getServer()->getCommandMap()->register("halloftop", new HallTopCommand());
    }

    protected function onDisable(): void {
        HallTopManager::getInstance()->shutdown();
    }
}
