<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'action',
    'resource_type',
    'resource_id',
    'resource_label',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Monta as linhas de comparação campo a campo entre old_values e
     * new_values, para exibição legível na tela de detalhes.
     *
     * @return array<int, array{key: string, label: string, old: string, new: string, changed: bool}>
     */
    public function diffRows(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = collect(array_keys($old))
            ->merge(array_keys($new))
            ->unique()
            ->sort()
            ->values();

        return $keys->map(function (string $key) use ($old, $new): array {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            return [
                'key' => $key,
                'label' => Str::of($key)
                    ->replace(['_', '-'], ' ')
                    ->title()
                    ->toString(),
                'old' => $this->formatValue($oldValue),
                'new' => $this->formatValue($newValue),
                'changed' => $this->formatValue($oldValue)
                    !== $this->formatValue($newValue),
            ];
        })->all();
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '—';
        }

        return (string) $value;
    }
}
