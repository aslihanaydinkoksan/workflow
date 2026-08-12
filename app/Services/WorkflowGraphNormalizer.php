<?php

namespace App\Services;

class WorkflowGraphNormalizer
{
    /**
     * Tasarımcıda ters çizilmiş kenarları düzeltir ve başlangıç düğümünü normalize eder.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function normalize(array $nodes, array $edges): array
    {
        $nodes = $this->normalizeNodes($nodes);
        $edges = $this->normalizeEdges($nodes, $edges);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNodes(array $nodes): array
    {
        return array_map(function (array $node) {
            $type = $node['type'] ?? null;
            $data = $node['data'] ?? [];

            if (in_array($type, ['start', 'input'], true) && empty($data['taskType'])) {
                $data['taskType'] = 'start';
                $data['label'] = $data['label'] ?? 'Başlangıç';
            }

            if ($type === 'end' && empty($data['taskType'])) {
                $data['taskType'] = 'end';
            }

            if (isset($data['assignValue']) && is_numeric($data['assignValue'])) {
                $data['assignValue'] = (string) $data['assignValue'];
            }

            $node['data'] = $data;

            return $node;
        }, $nodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEdges(array $nodes, array $edges): array
    {
        if ($edges === []) {
            return $edges;
        }

        $startId = $this->findStartNodeId($nodes);
        if (! $startId) {
            return $edges;
        }

        $hasOutgoingFromStart = collect($edges)->contains(fn ($edge) => ($edge['source'] ?? null) === $startId);
        if ($hasOutgoingFromStart) {
            return $edges;
        }

        $hasIncomingToStart = collect($edges)->contains(fn ($edge) => ($edge['target'] ?? null) === $startId);
        if (! $hasIncomingToStart) {
            return $edges;
        }

        return collect($edges)
            ->map(function (array $edge) {
                $source = $edge['source'] ?? null;
                $target = $edge['target'] ?? null;
                $sourceHandle = $edge['sourceHandle'] ?? null;
                $targetHandle = $edge['targetHandle'] ?? null;

                return array_merge($edge, [
                    'source' => $target,
                    'target' => $source,
                    'sourceHandle' => $targetHandle,
                    'targetHandle' => $sourceHandle,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function findStartNodeId(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            $type = $node['type'] ?? null;
            $taskType = $node['data']['taskType'] ?? null;

            if (in_array($type, ['input', 'start'], true) || $taskType === 'start') {
                return $node['id'] ?? null;
            }
        }

        return $nodes[0]['id'] ?? null;
    }
}
