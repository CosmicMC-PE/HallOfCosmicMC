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
     * @return list<string>
     */
    public static function describe(Player $player): array {
        $lines = [];

        $rank = self::call($player, "getRank");
        if (is_object($rank)) {
            $display = self::call($rank, "getDisplay");
            if (is_string($display) && $display !== "") {
                $lines[] = TextFormat::colorize($display);
            }
        }

        $faction = self::call($player, "getFaction");
        if (is_object($faction)) {
            $name = self::call($faction, "getName");
            if (is_string($name) && $name !== "") {
                $lines[] = $name;
            }
        }

        return $lines;
    }

    private static function call(object $target, string $method): mixed {
        if (!method_exists($target, $method)) {
            return null;
        }

        return (new \ReflectionMethod($target, $method))->invoke($target);
    }
}
