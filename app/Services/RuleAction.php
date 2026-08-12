<?php

namespace App\Services;

class RuleAction
{
    public readonly string $type;
    public readonly array $params;

    public function __construct(array $actionData)
    {
        $this->type = $actionData['type'] ?? 'default';
        $this->params = $actionData['params'] ?? [];
    }
}
