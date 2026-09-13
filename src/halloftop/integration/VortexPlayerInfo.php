<?php

declare(strict_types=1);

namespace halloftop\integration;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

/**
 * CosmicMC (Vortex) is a separate plugin with its own classloader, so it can't be
 * type-hinted or instanceof-checked here without a hard composer dependency.
 * Dynamic method calls are the only honest way to read its rank/faction when present
 * without coupling HallOfCosmic's build to it.
 */
final class VortexPlayerInfo {

    private function __construct() {}

    /**
     * @return array{rank: ?string, faction: ?string}
     */
    public static function describe(Player $player): array {
        $rank = null;
        $rankObject = self::call($player, "getRank");
        if (is_object($rankObject)) {
            $display = self::call($rankObject, "getDisplay");
            if (is_string($display) && $display !== "") {
                $rank = TextFormat::colorize($display);
            }
        }

        $faction = null;
        $factionObject = self::call($player, "getFaction");
        if (is_object($factionObject)) {
            $name = self::call($factionObject, "getName");
            if (is_string($name) && $name !== "") {
                $faction = $name;
            }
        }

        return ["rank" => $rank, "faction" => $faction];
    }

    private static function call(object $target, string $method): mixed {
        if (!method_exists($target, $method)) {
            return null;
        }

        return (new \ReflectionMethod($target, $method))->invoke($target);
    }
}
