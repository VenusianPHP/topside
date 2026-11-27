<?php

namespace Surface\Toolkits\Managers;

use ReflectionException;
use Surface\Toolkits\ToolkitException;
use Voyager\NutsAndBolts\Manager;
use Surface\Contracts\Toolkits\ToolkitAdapterDriver;

class ToolkitAdapterManager extends Manager
{
    /**
     * Primitive classes packages gave a toolkit, by toolkit and contract: each driver takes
     * its own when it is made, or at once when it already is.
     *
     * @var array<string, array<class-string, class-string>>
     */
    protected array $primitives = [];

    public function createNullDriver(): never
    {
        throw new ToolkitException("No default toolkit set.");
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return config('toolkits.default','null');
    }

    /**
     * Make $class the primitive for $contract on the toolkit named $driver, whenever that
     * toolkit's driver is made.
     *
     * @param class-string $contract
     * @param class-string $class
     * @throws ToolkitException When $class does not implement $contract.
     */
    public function primitive(string $driver, string $contract, string $class): static
    {
        if (! is_subclass_of($class, $contract)) {
            throw new ToolkitException("{$class} does not implement {$contract}, so it cannot be the '{$driver}' toolkit's primitive for it.");
        }

        $this->primitives[$driver][$contract] = $class;

        if (($this->drivers[$driver] ?? null) instanceof ToolkitAdapterDriver) {
            $this->drivers[$driver]->registerPrimitive($contract, $class);
        }

        return $this;
    }

    protected function createDriver(string $driver): mixed
    {
        $made = parent::createDriver($driver);

        if ($made instanceof ToolkitAdapterDriver) {
            foreach ($this->primitives[$driver] ?? [] as $contract => $class) {
                $made->registerPrimitive($contract, $class);
            }
        }

        return $made;
    }
}
