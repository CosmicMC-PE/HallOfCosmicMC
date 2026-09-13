<?php

declare(strict_types=1);

namespace halloftop\command;

use pocketmine\command\CommandSender;

interface SubCommand {

    public function getName(): string;

    /**
     * @param list<string> $args
     */
    public function execute(CommandSender $sender, array $args): void;
}
