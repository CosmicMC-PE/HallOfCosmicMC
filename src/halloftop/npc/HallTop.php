<?php

declare(strict_types=1);

namespace halloftop\npc;

use pocketmine\entity\Location;

final class HallTop {

    public function __construct(
        private string $type,
        private Location $location,
        private ?HallTopEntity $entity = null,
        private ?string $playerName = null
    ) {}

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): void {
        $this->type = $type;
    }

    public function getLocation(): Location {
        return $this->location;
    }

    public function setLocation(Location $location): void {
        $this->location = $location;
    }

    public function getEntity(): ?HallTopEntity {
        return $this->entity;
    }

    public function setEntity(?HallTopEntity $entity): void {
        $this->entity = $entity;
    }

    public function getPlayerName(): ?string {
        return $this->playerName;
    }

    public function setPlayerName(?string $playerName): void {
        $this->playerName = $playerName;
    }
}
