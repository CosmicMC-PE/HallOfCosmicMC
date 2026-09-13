<?php

declare(strict_types=1);

namespace halloftop\npc;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use halloftop\integration\VortexPlayerInfo;
use halloftop\Loader;
use halloftop\util\ChunkLoadPromise;
use function array_filter;
use function array_map;
use function array_values;
use function base64_decode;
use function base64_encode;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function mkdir;
use function strtolower;
use function strtoupper;

final class HallTopManager implements Listener {
    use SingletonTrait;

    /** @var array<string, HallTop> */
    private array $tops = [];

    /** @var array<string, Player> */
    private array $onlineByName = [];

    /** @var list<string> */
    private array $validTypes = [];

    private ?Config $storage = null;
    private ?Loader $plugin = null;

    public function init(Loader $plugin): void {
        $this->plugin = $plugin;

        Server::getInstance()->getPluginManager()->registerEvents($this, $plugin);

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $this->onlineByName[strtolower($player->getName())] = $player;
        }

        EntityFactory::getInstance()->register(
            HallTopEntity::class,
            fn(World $world, CompoundTag $nbt): HallTopEntity => new HallTopEntity(
                EntityDataHelper::parseLocation($nbt, $world),
                Human::parseSkinNBT($nbt),
                $nbt
            ),
            ["HallTopEntity", "halloftop:top_npc"]
        );

        $configured = $plugin->getConfig()->get("types", []);
        $this->validTypes = is_array($configured)
            ? array_values(array_map(strtolower(...), array_filter($configured, is_string(...))))
            : [];

        $dataFolder = $plugin->getDataFolder();
        if (!is_dir($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }

        $this->storage = new Config($dataFolder . "halltop.json", Config::JSON);
        $this->loadPersisted();
    }

    private function plugin(): Loader {
        return $this->plugin ?? throw new \RuntimeException("HallTopManager::init() has not been called yet");
    }

    private function storage(): Config {
        return $this->storage ?? throw new \RuntimeException("HallTopManager::init() has not been called yet");
    }

    public function shutdown(): void {
        foreach ($this->tops as $top) {
            $top->getEntity()?->flagForDespawn();
        }
        $this->tops = [];
        $this->onlineByName = [];
    }

    /**
     * @return list<string>
     */
    public function getValidTypes(): array {
        return $this->validTypes;
    }

    public function isValidType(string $type): bool {
        return in_array(strtolower($type), $this->validTypes, true);
    }

    public function has(string $type): bool {
        return isset($this->tops[strtolower($type)]);
    }

    public function get(string $type): ?HallTop {
        return $this->tops[strtolower($type)] ?? null;
    }

    public function findOnlinePlayer(string $name): ?Player {
        return $this->onlineByName[strtolower($name)] ?? null;
    }

    public function create(string $type, Location $location): void {
        $key = strtolower($type);

        if (!$this->isValidType($key)) {
            throw new \InvalidArgumentException("'$type' no es un tipo de top valido.");
        }

        if ($this->has($key)) {
            throw new \InvalidArgumentException("Ya existe un top para '$key'.");
        }

        $this->tops[$key] = new HallTop($key, $location->asLocation());
        $this->persist($key);
    }

    public function delete(string $type): void {
        $key = strtolower($type);
        $top = $this->tops[$key] ?? null;

        if ($top === null) {
            throw new \InvalidArgumentException("No existe un top para '$type'.");
        }

        $top->getEntity()?->flagForDespawn();
        unset($this->tops[$key]);

        $this->storage()->remove($key);
        $this->storage()->save();
    }

    public function assign(string $type, Player $player): void {
        $key = strtolower($type);
        $top = $this->tops[$key] ?? null;

        if ($top === null) {
            throw new \InvalidArgumentException("No existe un top para '$type'. Usa /halltop create primero.");
        }

        $skin = clone $player->getSkin();
        $nametag = $this->buildNameTag($key, $player);

        $entity = $top->getEntity();
        if ($entity === null || $entity->isClosed()) {
            $entity = new HallTopEntity($top->getLocation(), $skin);
            $entity->spawnToAll();
            $top->setEntity($entity);
        } else {
            $entity->setSkin($skin);
            $entity->sendSkin();
        }
        $entity->setNameTag($nametag);

        $top->setPlayerName($player->getName());
        $this->persist($key);
    }

    public function moveToLocation(string $type, Location $location): void {
        $key = strtolower($type);
        $top = $this->tops[$key] ?? null;

        if ($top === null) {
            throw new \InvalidArgumentException("No existe un top para '$type'.");
        }

        $location = $location->asLocation();
        $top->setLocation($location);
        $top->getEntity()?->teleport($location, $location->yaw, $location->pitch);

        $this->persist($key);
    }

    public function changeType(string $type, string $newType): void {
        $oldKey = strtolower($type);
        $newKey = strtolower($newType);

        $top = $this->tops[$oldKey] ?? null;
        if ($top === null) {
            throw new \InvalidArgumentException("No existe un top para '$type'.");
        }

        if (!$this->isValidType($newKey)) {
            throw new \InvalidArgumentException("'$newType' no es un tipo de top valido.");
        }

        if ($this->has($newKey)) {
            throw new \InvalidArgumentException("Ya existe un top para '$newKey'.");
        }

        unset($this->tops[$oldKey]);
        $this->storage()->remove($oldKey);

        $top->setType($newKey);
        $this->tops[$newKey] = $top;

        $this->persist($newKey);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->onlineByName[strtolower($player->getName())] = $player;

        foreach ($this->tops as $top) {
            $top->getEntity()?->attemptLookAt($player);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        unset($this->onlineByName[strtolower($event->getPlayer()->getName())]);
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();

        foreach ($this->tops as $top) {
            $top->getEntity()?->attemptLookAt($player);
        }
    }

    private function buildNameTag(string $type, Player $player): string {
        $header = TextFormat::colorize("&6&lTOP 1 &e" . strtoupper($type));

        $info = VortexPlayerInfo::describe($player);
        $line2 = [];
        if ($info["rank"] !== null) {
            $line2[] = $info["rank"];
        }
        if ($info["faction"] !== null) {
            $line2[] = TextFormat::GRAY . "[" . $info["faction"] . "]";
        }
        $line2[] = TextFormat::WHITE . $player->getName();

        return implode("\n", [$header, implode(" ", $line2)]);
    }

    private function persist(string $key): void {
        $top = $this->tops[$key];
        $location = $top->getLocation();
        $world = $location->getWorld();

        $data = [
            "world" => $world->getFolderName(),
            "x" => $location->x,
            "y" => $location->y,
            "z" => $location->z,
            "yaw" => $location->yaw,
            "pitch" => $location->pitch,
            "playerName" => $top->getPlayerName()
        ];

        $entity = $top->getEntity();
        if ($entity !== null) {
            $skin = $entity->getSkin();
            $data["nametag"] = $entity->getNameTag();
            $data["skinId"] = $skin->getSkinId();
            $data["skinData"] = base64_encode($skin->getSkinData());
            $data["capeData"] = base64_encode($skin->getCapeData());
            $data["geometryName"] = $skin->getGeometryName();
            $data["geometryData"] = base64_encode($skin->getGeometryData());
        }

        $this->storage()->set($key, $data);
        $this->storage()->save();
    }

    private function loadPersisted(): void {
        $worldManager = Server::getInstance()->getWorldManager();

        foreach ($this->storage()->getAll() as $type => $raw) {
            if (!is_string($type) || !is_array($raw)) {
                continue;
            }

            $worldName = (string) ($raw["world"] ?? "");
            if (!$worldManager->isWorldLoaded($worldName) && !$worldManager->loadWorld($worldName)) {
                $this->plugin()->getLogger()->warning("No se pudo cargar el mundo '$worldName' para el top '$type'.");
                continue;
            }

            $world = $worldManager->getWorldByName($worldName);
            if ($world === null) {
                continue;
            }

            $location = new Location(
                (float) ($raw["x"] ?? 0.0),
                (float) ($raw["y"] ?? 0.0),
                (float) ($raw["z"] ?? 0.0),
                $world,
                (float) ($raw["yaw"] ?? 0.0),
                (float) ($raw["pitch"] ?? 0.0)
            );

            $playerName = isset($raw["playerName"]) && is_string($raw["playerName"]) ? $raw["playerName"] : null;
            $top = new HallTop($type, $location, null, $playerName);
            $this->tops[$type] = $top;

            if (!isset($raw["skinData"]) || !is_string($raw["skinData"])) {
                continue;
            }

            $skin = new Skin(
                (string) ($raw["skinId"] ?? ""),
                (string) base64_decode($raw["skinData"], true),
                (string) base64_decode((string) ($raw["capeData"] ?? ""), true),
                (string) ($raw["geometryName"] ?? ""),
                (string) base64_decode((string) ($raw["geometryData"] ?? ""), true)
            );
            $nametag = (string) ($raw["nametag"] ?? "");

            ChunkLoadPromise::create(
                $world,
                $location->getFloorX() >> 4,
                $location->getFloorZ() >> 4,
                function () use ($top, $location, $skin, $nametag): void {
                    $entity = new HallTopEntity($location, $skin);
                    $entity->spawnToAll();
                    $entity->setNameTag($nametag);
                    $top->setEntity($entity);
                }
            );
        }
    }
}
