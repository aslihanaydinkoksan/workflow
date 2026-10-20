<?php

declare(strict_types=1);

namespace App\Security\Contracts;

use Illuminate\Http\Request;

interface RequestContextProvider
{
    /**
     * The request currently being handled, or null outside HTTP (queue, console).
     */
    public function current(): ?Request;

    /**
     * Correlation fields describing who sent the request and where it went.
     *
     * @return array<string, mixed>
     */
    public function describe(Request $request): array;
}
