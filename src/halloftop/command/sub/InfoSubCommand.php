<?php

declare(strict_types=1);

namespace halloftop\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use halloftop\command\SubCommand;
use halloftop\npc\HallTopManager;

final class InfoSubCommand implements SubCommand {

    public function getName(): string {
        return "info";
    }

    public function execute(CommandSender $sender, array $args): void {
        $manager = HallTopManager::getInstance();
        $validTypes = $manager->getValidTypes();

        if ($validTypes === []) {
            $sender->sendMessage(TextFormat::RED . "No hay tipos de top configurados.");
            return;
        }

        $sender->sendMessage(TextFormat::YELLOW . "--- Tops de HallOfCosmic ---");
        foreach ($validTypes as $type) {
            $top = $manager->get($type);

            if ($top === null) {
                $sender->sendMessage(TextFormat::GRAY . "$type: sin crear");
                continue;
            }

            $location = $top->getLocation();
            $worldName = $location->isValid() ? $location->getWorld()->getFolderName() : "desconocido";
            $playerName = $top->getPlayerName();

            $status = $playerName !== null
                ? TextFormat::GREEN . "asignado a $playerName"
                : TextFormat::GRAY . "sin asignar";

            $sender->sendMessage(TextFormat::WHITE . "$type: $status " . TextFormat::GRAY . "($worldName, " . (int) $location->x . ", " . (int) $location->y . ", " . (int) $location->z . ")");
        }
    }
}
