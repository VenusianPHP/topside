<?php

namespace Surface\Core\Console;

use Voyager\Console\Command;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Views\ViewException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sharp:cache')]
class SharpCacheCommand extends Command
{
    protected ?string $name = 'sharp:cache';

    protected string $description = 'Compile every sharp template under resources/';

    public function handle(Sharp $sharp): int
    {
        try {
            $files = $sharp->compileAll();
        } catch (ViewException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(count($files) === 1 ? '1 sharp template compiled.' : count($files).' sharp templates compiled.');

        return self::SUCCESS;
    }
}
