<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TreeType extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'display_name',
        'description',
        'schema',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'schema'    => 'array',
    ];

    /**
     * @return HasMany<Node>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }
}
