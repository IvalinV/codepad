<?php

namespace App\Models;

use App\Enums\ThemeVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnippetRender extends Model
{
    protected $fillable = ['theme', 'content', 'hash'];

    /** @return BelongsTo<Snippet, $this> */
    public function snippet(): BelongsTo
    {
        return $this->belongsTo(Snippet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'theme' => ThemeVariant::class,
            'content' => 'array',
        ];
    }
}
