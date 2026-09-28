<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get typed value according to the type column.
     */
    public function getParsedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $this->value,
            'json', 'array' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Helper to retrieve a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return $setting->parsed_value;
    }

    /**
     * Helper to set or update a setting value.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $description = null): static
    {
        $type = $type ?? (is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string'));

        $storedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        $payload = ['value' => $storedValue, 'type' => $type];
        if ($description !== null) {
            $payload['description'] = $description;
        }

        return static::updateOrCreate(['key' => $key], $payload);
    }
}
