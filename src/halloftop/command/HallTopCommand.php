<?php

declare(strict_types=1);

namespace halloftop\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use halloftop\command\sub\CreateSubCommand;
use halloftop\command\sub\DeleteSubCommand;
use halloftop\command\sub\EditSubCommand;
use halloftop\command\sub\InfoSubCommand;
use halloftop\command\sub\SetSubCommand;
use function array_slice;
use function strtolower;

final class HallTopCommand extends Command {

    private const USAGE = "/halltop <create|delete|set|edit|info>";

    /** @var array<string, SubCommand> */
    private array $subCommands = [];

    public function __construct() {
        parent::__construct("halltop", "Administra los NPCs de Hall of Cosmic", self::USAGE);
        $this->setPermission("halltop.command.admin");

        foreach ([new CreateSubCommand(), new DeleteSubCommand(), new SetSubCommand(), new EditSubCommand(), new InfoSubCommand()] as $sub) {
            $this->subCommands[$sub->getName()] = $sub;
        }
    }

    /**
     * @param list<string> $args
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermission($sender)) {
            return;
        }

        if (($args[0] ?? null) === null) {
            $sender->sendMessage(TextFormat::RED . self::USAGE);
            return;
        }

        $sub = $this->subCommands[strtolower($args[0])] ?? null;
        if ($sub === null) {
            $sender->sendMessage(TextFormat::RED . "Subcomando desconocido. " . self::USAGE);
            return;
        }

        $sub->execute($sender, array_slice($args, 1));
    }
}
