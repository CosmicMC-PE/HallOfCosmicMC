<?php

declare(strict_types=1);

namespace halloftop\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use halloftop\command\SubCommand;
use halloftop\npc\HallTopManager;
use function implode;
use function strtolower;

final class CreateSubCommand implements SubCommand {

    public function getName(): string {
        return "create";
    }

    public function execute(CommandSender $sender, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Este comando solo puede usarse en juego.");
            return;
        }

        $type = strtolower($args[0] ?? "");
        if ($type === "") {
            $sender->sendMessage(TextFormat::RED . "Uso: /halltop create <tipo>");
            return;
        }

        $manager = HallTopManager::getInstance();
        if (!$manager->isValidType($type)) {
            $sender->sendMessage(TextFormat::RED . "Tipo invalido. Tipos validos: " . implode(", ", $manager->getValidTypes()));
            return;
        }

        try {
            $manager->create($type, $sender->getLocation());
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
            return;
        }

        $sender->sendMessage(TextFormat::GREEN . "Top '$type' creado en tu posicion. Usa /halltop set $type <jugador> para asignarlo.");
    }
}
