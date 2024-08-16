<?php

declare(strict_types=1);

namespace Nesk\Rialto\Traits;

trait UsesBasicResourceAsDefault
{
    /**
     * Return the fully qualified name of the default resource.
     */
    public function defaultResource(): string
    {
        return \Nesk\Rialto\Data\BasicResource::class;
    }
}
