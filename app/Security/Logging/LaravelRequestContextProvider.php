<?php

declare(strict_types=1);

namespace App\Security\Logging;

use App\Security\Contracts\RequestContextProvider;
use App\Security\Http\Middleware\AssignRequestId;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

final class LaravelRequestContextProvider implements RequestContextProvider
{
    public function __construct(
        private readonly Container $container,
        private readonly ValueSanitizer $sanitizer,
    ) {
    }

    public function current(): ?Request
    {
        if (!$this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');

        return $request instanceof Request ? $request : null;
    }

    public function describe(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            'source_ip' => $request->ip(),
            'user_agent' => $this->sanitizer->string((string) $request->userAgent(), 256),
            'method' => $request->getMethod(),
            'host' => $this->sanitizer->string($request->getHost(), 128),
            'path' => $this->sanitizer->string('/' . ltrim($request->path(), '/'), 256),
            'route' => $request->route()?->getName(),
            'user_id' => $this->resolveUserId($request),
        ];
    }

    private function resolveUserId(Request $request): int|string|null
    {
        try {
            $id = $request->user()?->getAuthIdentifier();
        } catch (\Throwable) {
            return null;
        }

        return is_int($id) || is_string($id) ? $id : null;
    }
}
