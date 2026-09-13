<?php

declare(strict_types=1);

namespace halloftop\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use halloftop\command\SubCommand;
use halloftop\npc\HallTopManager;
use function strtolower;

final class DeleteSubCommand implements SubCommand {

    public function getName(): string {
        return "delete";
    }

    public function execute(CommandSender $sender, array $args): void {
        $type = strtolower($args[0] ?? "");
        if ($type === "") {
            $sender->sendMessage(TextFormat::RED . "Uso: /halltop delete <tipo>");
            return;
        }

        try {
            HallTopManager::getInstance()->delete($type);
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }

        $sender->sendMessage(TextFormat::GREEN . "Top '$type' eliminado.");
    }
}
