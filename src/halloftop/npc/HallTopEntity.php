<?php

declare(strict_types=1);

namespace halloftop\npc;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\Player;
use pocketmine\world\ChunkLoader;
use function atan2;
use function sqrt;

final class HallTopEntity extends Human implements ChunkLoader {

    private const LOOK_RADIUS_SQUARED = 100.0;

    public static function getNetworkTypeId(): string {
        return "halloftop:top_npc";
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->setCanSaveWithChunk(false);
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
    }

    public function attack(EntityDamageEvent $source): void {
        $source->cancel();
        parent::attack($source);
    }

    public function attemptLookAt(Player $player): void {
        if ($this->getWorld() !== $player->getWorld()) {
            return;
        }

        $location = $this->getLocation();
        if ($player->getPosition()->distanceSquared($location) > self::LOOK_RADIUS_SQUARED) {
            return;
        }

        $target = $player->getEyePos();
        $eyePos = $this->getEyePos();

        $horizontal = sqrt(($target->x - $eyePos->x) ** 2 + ($target->z - $eyePos->z) ** 2);
        $vertical = $target->y - $eyePos->y;
        $pitch = -atan2($vertical, $horizontal) / M_PI * 180;

        $xDist = $target->x - $eyePos->x;
        $zDist = $target->z - $eyePos->z;
        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
        if ($yaw < 0) {
            $yaw += 360.0;
        }

        $packet = MovePlayerPacket::simple(
            $this->getId(),
            $this->getOffsetPosition($location),
            $pitch,
            $yaw,
            $yaw,
            MovePlayerPacket::MODE_NORMAL,
            true,
            0,
            0
        );

        $player->getNetworkSession()->sendDataPacket($packet);
    }
}
