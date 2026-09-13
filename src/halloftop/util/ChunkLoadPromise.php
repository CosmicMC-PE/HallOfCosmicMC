<?php

declare(strict_types=1);

namespace halloftop\util;

use pocketmine\math\Vector3;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkLoader;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class ChunkLoadPromise implements ChunkLoader, ChunkListener {

    /** @var array<int, list<\Closure(): void>> */
    private static array $beingGenerated = [];

    /**
     * @param \Closure(): void $callback
     */
    private function __construct(
        private readonly World $world,
        private readonly int $chunkX,
        private readonly int $chunkZ,
        private readonly \Closure $callback
    ) {}

    /**
     * @param \Closure(): void $callback
     */
    public static function create(World $world, int $chunkX, int $chunkZ, \Closure $callback): void {
        if ($world->isChunkPopulated($chunkX, $chunkZ)) {
            $callback();
            return;
        }

        $hash = World::chunkHash($chunkX, $chunkZ);
        if (!isset(self::$beingGenerated[$hash])) {
            self::$beingGenerated[$hash] = [];

            $instance = new self($world, $chunkX, $chunkZ, $callback);
            $world->registerChunkLoader($instance, $chunkX, $chunkZ, true);
            $world->registerChunkListener($instance, $chunkX, $chunkZ);
            $world->orderChunkPopulation($chunkX, $chunkZ, $instance);
        } else {
            self::$beingGenerated[$hash][] = $callback;
        }
    }

    private function onComplete(): void {
        ($this->callback)();

        $hash = World::chunkHash($this->chunkX, $this->chunkZ);
        foreach (self::$beingGenerated[$hash] ?? [] as $callback) {
            $callback();
        }
        unset(self::$beingGenerated[$hash]);

        $this->world->unregisterChunkLoader($this, $this->chunkX, $this->chunkZ);
        $this->world->unregisterChunkListenerFromAll($this);
    }

    public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk): void {
        $this->onComplete();
    }

    public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk): void {
        $this->onComplete();
    }

    public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk): void {}

    public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk): void {}

    public function onBlockChanged(Vector3 $block): void {}
}
