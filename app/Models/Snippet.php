<?php

namespace App\Models;

use App\Enums\Language;
use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    /**
     * The label shown for this snippet in the list.
     *
     * Falls back to the first non-blank line of the body when no title has
     * been set. This derived label is never persisted back to the `title`
     * column, so the user's intent and our guess stay distinguishable.
     */
    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        foreach (preg_split('/\R/u', (string) $this->body) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '') {
                return Str::limit($trimmed, 57);
            }
        }

        return 'Untitled snippet';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['language' => Language::class];
    }

    /** @param  Builder<self>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $escaped = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $query->where(function (Builder $query) use ($escaped): void {
            $query->whereRaw('lower(title) like lower(?) escape ?', [$escaped, '\\'])
                ->orWhereRaw('lower(body) like lower(?) escape ?', [$escaped, '\\']);
        });
    }

    /** @param  Builder<self>  $query */
    public function scopeForLanguage(Builder $query, ?Language $language): void
    {
        if ($language instanceof Language) {
            $query->where('language', $language->value);
        }
    }

    /** @param  Builder<self>  $query */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }
}
