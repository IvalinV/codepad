<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Models\Snippet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Snippet> */
class SnippetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'body' => "<?php\n\nfunction handle(): void\n{\n    echo 'hi';\n}",
            'language' => Language::Php,
        ];
    }

    public function untitled(): static
    {
        return $this->state(fn (array $attributes): array => ['title' => null]);
    }
}
