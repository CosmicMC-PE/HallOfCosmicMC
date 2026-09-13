<?php

declare(strict_types=1);

namespace halloftop\npc;

use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;

final class HallTopEntity extends Human {

    public static function getNetworkTypeId(): string {
        return "halloftop:top_npc";
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->setCanSaveWithChunk(false);
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
    }
}
