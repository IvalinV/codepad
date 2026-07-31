<?php

namespace App\Models;

use App\Enums\Language;
use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Snippet extends Model
{
    /** @use HasFactory<SnippetFactory> */
    use HasFactory;

    protected $fillable = ['title', 'body', 'language'];

    /** @return HasMany<SnippetRender, $this> */
    public function renders(): HasMany
    {
        return $this->hasMany(SnippetRender::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['language' => Language::class];
    }
}
