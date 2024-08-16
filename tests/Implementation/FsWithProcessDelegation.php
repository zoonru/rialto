<?php

declare(strict_types=1);

namespace Nesk\Rialto\Tests\Implementation;

use Nesk\Rialto\{AbstractEntryPoint, ProcessSupervisor};

class FsWithProcessDelegation extends AbstractEntryPoint
{
    protected $forbiddenOptions = ['stop_timeout', 'foo'];

    public function __construct(array $userOptions = [])
    {
        parent::__construct(__DIR__ . '/FsConnectionDelegate.mjs', new FsProcessDelegate(), [], $userOptions);
    }

    public function getProcessSupervisor(): ProcessSupervisor
    {
        return parent::getProcessSupervisor();
    }
}
