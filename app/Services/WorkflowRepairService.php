<?php

namespace App\Services;

use App\Models\Workflow;

class WorkflowRepairService
{
    public function __construct(private WorkflowGraphNormalizer $graphNormalizer)
    {
    }

    /**
     * Tüm akışları normalize eder; geçerli taslakları yayına alır.
     *
     * @return array{repaired: int, published: int, skipped: int}
     */
    public function repairAll(bool $publishValidDrafts = true): array
    {
        $stats = ['repaired' => 0, 'published' => 0, 'skipped' => 0];

        foreach (Workflow::query()->orderBy('id')->get() as $workflow) {
            $result = $this->repairWorkflow($workflow, $publishValidDrafts);
            $stats[$result]++;
        }

        return $stats;
    }

    public function repairWorkflow(Workflow $workflow, bool $publishIfValidDraft = true): string
    {
        $normalized = $this->graphNormalizer->normalize(
            $workflow->nodes ?? [],
            $workflow->edges ?? []
        );

        $payload = [
            'nodes' => $normalized['nodes'],
            'edges' => $normalized['edges'],
        ];

        $changed = ($workflow->nodes !== $payload['nodes']) || ($workflow->edges !== $payload['edges']);

        if ($publishIfValidDraft && $workflow->status === 'draft' && $this->hasRunnableGraph($payload['nodes'], $payload['edges'])) {
            $payload['status'] = 'active';
            $changed = true;
        }

        if (! $changed) {
            return 'skipped';
        }

        $wasDraft = $workflow->status === 'draft';
        $workflow->update($payload);

        if ($wasDraft && ($payload['status'] ?? null) === 'active') {
            return 'published';
        }

        return 'repaired';
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    public function hasRunnableGraph(array $nodes, array $edges): bool
    {
        if ($nodes === [] || $edges === []) {
            return false;
        }

        $startId = collect($nodes)->first(function ($node) {
            $type = $node['type'] ?? null;
            $taskType = $node['data']['taskType'] ?? null;

            return in_array($type, ['input', 'start'], true) || $taskType === 'start';
        })['id'] ?? null;

        if (! $startId) {
            return false;
        }

        $hasOutgoingFromStart = collect($edges)->contains(fn ($edge) => ($edge['source'] ?? null) === $startId);

        return $hasOutgoingFromStart;
    }
}
