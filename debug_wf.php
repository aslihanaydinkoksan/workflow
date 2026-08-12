<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (App\Models\Workflow::whereIn('id', [5,6])->get() as $w) {
    echo "=== WF#{$w->id} {$w->name} status={$w->status} ===\n";
    echo "allowed_roles=" . json_encode($w->allowed_roles) . "\n";
    foreach ($w->nodes ?? [] as $n) {
        $d = $n['data'] ?? [];
        echo "  {$n['id']} type={$n['type']} taskType=".($d['taskType']??'-')." assign=".($d['assignType']??'-')."/".($d['assignValue']??'-')."\n";
    }
    echo "edges=" . json_encode($w->edges, JSON_UNESCAPED_UNICODE) . "\n\n";
}
foreach (App\Models\ProcessInstance::with('tasks')->latest()->take(3)->get() as $pi) {
    echo "PI#{$pi->id} wf={$pi->workflow_id} by={$pi->started_by} status={$pi->status}\n";
    foreach ($pi->tasks as $t) {
        echo "  task#{$t->id} node={$t->node_id} to={$t->assigned_to} role_id={$t->assigned_role_id} role={$t->assigned_role} type={$t->type} status={$t->status}\n";
    }
}
