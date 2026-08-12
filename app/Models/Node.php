<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_type_id
 * @property int|null $user_id
 * @property string $key
 * @property string|null $node_subtype
 * @property string $label
 * @property array|null $metadata
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Node extends Model
{
    /**
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'tree_type_id',
        'user_id',
        'key',
        'node_subtype',
        'label',
        'metadata',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<TreeType, Node>
     */
    public function treeType(): BelongsTo
    {
        return $this->belongsTo(TreeType::class);
    }

    /**
     * @return BelongsTo<User, Node>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<NodeClosure>
     */
    public function descendantClosures(): HasMany
    {
        return $this->hasMany(NodeClosure::class, 'ancestor_id');
    }

    /**
     * @return HasMany<NodeClosure>
     */
    public function ancestorClosures(): HasMany
    {
        return $this->hasMany(NodeClosure::class, 'descendant_id');
    }

    /**
     * @return BelongsToMany<Node>
     */
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_closures', 'descendant_id', 'ancestor_id')
            ->withPivot('depth')
            ->wherePivot('depth', '>', 0)
            ->orderByPivot('depth');
    }

    /**
     * @return BelongsToMany<Node>
     */
    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_closures', 'ancestor_id', 'descendant_id')
            ->withPivot('depth')
            ->wherePivot('depth', '>', 0)
            ->orderByPivot('depth');
    }
}
