<?php

declare(strict_types=1);

namespace halloftop\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use halloftop\command\SubCommand;
use halloftop\npc\HallTopManager;
use function strtolower;

final class SetSubCommand implements SubCommand {

    public function getName(): string {
        return "set";
    }

    public function execute(CommandSender $sender, array $args): void {
        $type = strtolower($args[0] ?? "");
        $playerName = $args[1] ?? "";

        if ($type === "" || $playerName === "") {
            $sender->sendMessage(TextFormat::RED . "Uso: /halltop set <tipo> <jugador>");
            return;
        }

        $manager = HallTopManager::getInstance();
        $target = $manager->findOnlinePlayer($playerName);
        if ($target === null) {
            $sender->sendMessage(TextFormat::RED . "El jugador '$playerName' no esta conectado.");
            return;
        }

        try {
            $manager->assign($type, $target);
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }

        $sender->sendMessage(TextFormat::GREEN . "Top '$type' asignado a " . $target->getName() . ".");
    }
}
