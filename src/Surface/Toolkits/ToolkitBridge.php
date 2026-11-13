<?php

namespace Surface\Toolkits;

use ReflectionException;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\Contracts\Toolkits\ToolkitBridge as DriverContract;


abstract class ToolkitBridge implements DriverContract
{
    protected string $session_class;

    protected ?ToolkitSession $session = null;

    public function __construct(
        protected TheServiceContainer $app
    ) {
        if(!isset($this->session_class)) {
            throw ToolkitRuntimeException::missingVariable('$session_class');
        }
    }

    protected function connected(): void {}

    protected function instanceSession(): void
    {
        $session_class  = $this->session_class;
        $this->session ??= new $session_class();
    }

    /**
     * @return ToolkitSession
     * @throws ReflectionException
     */
    public function connect(): ToolkitSession
    {
        $this->instanceSession();
        $this->session->connect();
        $this->connected();

        return $this->session;
    }

    public function session(): ?ToolkitSession
    {
        return $this->session;
    }


}