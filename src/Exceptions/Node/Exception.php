<?php

declare(strict_types=1);

namespace Nesk\Rialto\Exceptions\Node;

class Exception extends \RuntimeException
{
    use HandlesNodeErrors;

    /**
     * Constructor.
     */
    public function __construct($error, bool $appendStackTraceToMessage = false)
    {
        $message = $this->setTraceAndGetMessage($error, $appendStackTraceToMessage);

        parent::__construct($message);
    }
}
