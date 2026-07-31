<?php

use App\Enums\Language;
use Phiki\Grammar\Grammar;

/*
| These two datasets pin each mapping to its expected value rather than to
| `toBeInstanceOf(Grammar::class)`, which every possible mapping satisfies.
| The looser assertion was not merely weak: `GrammarBundleClosureTest` seeds
| the bundle keep-list from `$case->grammar()->value`, so a transposed mapping
| drops the real grammar out of the seed set and makes that guard vacuous for
| that language — the exact way a wrong mapping ships a device-only crash.
|
| Both datasets are keyed by case name, and `it('pins every case ...')` below
| asserts the keys cover `Language::cases()` exactly, so a 17th case cannot be
| added without pinning it here too.
*/

/** @return array<string, array{Language, Grammar}> */
function expectedLanguageGrammars(): array
{
    return [
        'PlainText' => [Language::PlainText, Grammar::Txt],
        'Php' => [Language::Php, Grammar::Php],
        'JavaScript' => [Language::JavaScript, Grammar::Javascript],
        'TypeScript' => [Language::TypeScript, Grammar::Typescript],
        'Python' => [Language::Python, Grammar::Python],
        'Go' => [Language::Go, Grammar::Go],
        'Rust' => [Language::Rust, Grammar::Rust],
        'Java' => [Language::Java, Grammar::Java],
        'CSharp' => [Language::CSharp, Grammar::Csharp],
        'Ruby' => [Language::Ruby, Grammar::Ruby],
        'Sql' => [Language::Sql, Grammar::Sql],
        // Deliberate: Phiki has no `Grammar::Bash`. `Shellscript` (value
        // `shellscript`) carries `bash` as an alias, so the file the bundle
        // must keep is shellscript.json, not bash.json.
        'Bash' => [Language::Bash, Grammar::Shellscript],
        'Json' => [Language::Json, Grammar::Json],
        'Yaml' => [Language::Yaml, Grammar::Yaml],
        'Html' => [Language::Html, Grammar::Html],
        'Css' => [Language::Css, Grammar::Css],
    ];
}

/** @return array<string, array{Language, string}> */
function expectedLanguageLabels(): array
{
    return [
        'PlainText' => [Language::PlainText, 'Plain text'],
        'Php' => [Language::Php, 'PHP'],
        'JavaScript' => [Language::JavaScript, 'JavaScript'],
        'TypeScript' => [Language::TypeScript, 'TypeScript'],
        'Python' => [Language::Python, 'Python'],
        'Go' => [Language::Go, 'Go'],
        'Rust' => [Language::Rust, 'Rust'],
        'Java' => [Language::Java, 'Java'],
        'CSharp' => [Language::CSharp, 'C#'],
        'Ruby' => [Language::Ruby, 'Ruby'],
        'Sql' => [Language::Sql, 'SQL'],
        'Bash' => [Language::Bash, 'Bash'],
        'Json' => [Language::Json, 'JSON'],
        'Yaml' => [Language::Yaml, 'YAML'],
        'Html' => [Language::Html, 'HTML'],
        'Css' => [Language::Css, 'CSS'],
    ];
}

it('maps every case to the expected phiki grammar', function (Language $language, Grammar $expected) {
    expect($language->grammar())->toBe($expected);
})->with(expectedLanguageGrammars());

it('gives every case the expected human label', function (Language $language, string $expected) {
    expect($language->label())->toBe($expected);
})->with(expectedLanguageLabels());

it('pins every case in both datasets', function () {
    $caseNames = array_map(fn (Language $case): string => $case->name, Language::cases());

    expect(array_keys(expectedLanguageGrammars()))->toBe($caseNames)
        ->and(array_keys(expectedLanguageLabels()))->toBe($caseNames);
});

it('resolves from a stored string value', function () {
    expect(Language::tryFrom('php'))->toBe(Language::Php)
        ->and(Language::tryFrom('nonsense'))->toBeNull();
});
