<ul class="{{ isset($isChild) && $isChild ? 'tree-ul mt-3' : 'space-y-4' }}">
    @foreach ($items as $item)
        @php $node = $item['node']; @endphp
        <li class="relative">

            <div class="node-container relative flex items-center group" draggable="true"
                ondragstart="handleDragStart(event, {{ $node->id }})" ondragend="handleDragEnd(event)"
                ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)"
                ondrop="handleDrop(event, {{ $node->id }})">

                <div
                    class="glass-panel flex items-center px-5 py-2.5 pr-28 rounded-xl transition-all duration-300 cursor-grab active:cursor-grabbing w-full">
                    <span class="font-medium tracking-wide break-words">
                        {{ $node->label }}
                        <span class="text-xs opacity-50 ml-1">({{ $node->node_subtype }})</span>
                    </span>
                </div>

                <div class="node-actions absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    <button onclick="openModal('create', {{ $node->id }}, '', '', {{ $node->tree_type_id }})"
                        class="glass-panel w-8 h-8 flex items-center justify-center rounded-full hover:bg-green-500/30 transition text-sm"
                        title="Alt Düğüm Ekle">+</button>
                    <button data-meta="{{ json_encode($node->metadata ?? new \stdClass()) }}"
                        onclick="openModal('edit', {{ $node->id }}, '{{ addslashes($node->label) }}', '{{ $node->node_subtype }}', {{ $node->tree_type_id }}, this.getAttribute('data-meta'))"
                        class="glass-panel w-8 h-8 flex items-center justify-center rounded-full hover:bg-blue-500/30 transition text-sm"
                        title="Düzenle">✎</button>
                    <button onclick="confirmDeleteNode({{ $node->id }})"
                        class="glass-panel w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-500/30 transition text-sm"
                        title="Sil">-</button>
                </div>

            </div>

            @if (!empty($item['children']))
                @include('hierarchy.partials.tree', [
                    'items' => $item['children'],
                    'isChild' => true,
                    'treeTypeId' => $treeTypeId ?? 1,
                ])
            @endif
        </li>
    @endforeach
</ul>
