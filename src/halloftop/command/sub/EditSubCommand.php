<?php

declare(strict_types=1);

namespace halloftop\command\sub;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use halloftop\command\SubCommand;
use halloftop\npc\HallTopManager;
use function strtolower;

final class EditSubCommand implements SubCommand {

    public function getName(): string {
        return "edit";
    }

    public function execute(CommandSender $sender, array $args): void {
        $type = strtolower($args[0] ?? "");
        $field = strtolower($args[1] ?? "");

        if ($type === "" || $field === "") {
            $sender->sendMessage(TextFormat::RED . "Uso: /halltop edit <tipo> <position|type> [nuevoTipo]");
            return;
        }

        $manager = HallTopManager::getInstance();

        try {
            match ($field) {
                "position" => $this->editPosition($sender, $manager, $type),
                "type" => $this->editType($sender, $manager, $type, strtolower($args[2] ?? "")),
                default => throw new \InvalidArgumentException("Campo invalido. Usa 'position' o 'type'.")
            };
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(TextFormat::RED . $e->getMessage());
        }
    }

    private function editPosition(CommandSender $sender, HallTopManager $manager, string $type): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Este comando solo puede usarse en juego.");
            return;
        }

        $manager->moveToLocation($type, $sender->getLocation());
        $sender->sendMessage(TextFormat::GREEN . "Top '$type' movido a tu posicion.");
    }

    private function editType(CommandSender $sender, HallTopManager $manager, string $type, string $newType): void {
        if ($newType === "") {
            $sender->sendMessage(TextFormat::RED . "Uso: /halltop edit <tipo> type <nuevoTipo>");
            return;
        }

        $manager->changeType($type, $newType);
        $sender->sendMessage(TextFormat::GREEN . "Top '$type' ahora es '$newType'.");
    }
}
