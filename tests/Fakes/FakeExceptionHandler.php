<?php

namespace Venusian\Topside\Tests\Fakes;

use Throwable;
use Voyager\Contracts\Debug\ExceptionHandler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Keeps what was reported.
 */
class FakeExceptionHandler implements ExceptionHandler
{
    /** @var list<Throwable> */
    public array $reported = [];

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function renderForConsole(OutputInterface $output, Throwable $e): void {}

    /** @return list<string> */
    public function messages(): array
    {
        return array_map(static fn (Throwable $e): string => $e->getMessage(), $this->reported);
    }
}
