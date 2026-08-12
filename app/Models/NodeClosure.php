<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $ancestor_id
 * @property int $descendant_id
 * @property int $depth
 */
class NodeClosure extends Model
{
    /**
     * Eloquent'in auto-incrementing 'id' kolonu aramasını engeller.
     * @var bool
     */
    public $incrementing = false;

    /**
     * Eloquent'in created_at / updated_at kolonları aramasını engeller.
     * @var bool
     */
    public $timestamps = false;

    /**
     * Primary key composite olduğu için Eloquent'in tekil bir primary key
     * stratejisi çalıştırmasını engelliyoruz. Sorgular her zaman
     * where('ancestor_id', ...)->where('descendant_id', ...) olarak yapılmalıdır.
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    /**
     * @return BelongsTo<Node, NodeClosure>
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'ancestor_id');
    }

    /**
     * @return BelongsTo<Node, NodeClosure>
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'descendant_id');
    }
}
