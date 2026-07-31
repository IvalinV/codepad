<?php

namespace App\Enums;

use Phiki\Grammar\Grammar;

enum Language: string
{
    case PlainText = 'plaintext';
    case Php = 'php';
    case JavaScript = 'javascript';
    case TypeScript = 'typescript';
    case Python = 'python';
    case Go = 'go';
    case Rust = 'rust';
    case Java = 'java';
    case CSharp = 'csharp';
    case Ruby = 'ruby';
    case Sql = 'sql';
    case Bash = 'bash';
    case Json = 'json';
    case Yaml = 'yaml';
    case Html = 'html';
    case Css = 'css';

    public function grammar(): Grammar
    {
        return match ($this) {
            self::PlainText => Grammar::Txt,
            self::Php => Grammar::Php,
            self::JavaScript => Grammar::Javascript,
            self::TypeScript => Grammar::Typescript,
            self::Python => Grammar::Python,
            self::Go => Grammar::Go,
            self::Rust => Grammar::Rust,
            self::Java => Grammar::Java,
            self::CSharp => Grammar::Csharp,
            self::Ruby => Grammar::Ruby,
            self::Sql => Grammar::Sql,
            self::Bash => Grammar::Shellscript,
            self::Json => Grammar::Json,
            self::Yaml => Grammar::Yaml,
            self::Html => Grammar::Html,
            self::Css => Grammar::Css,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PlainText => 'Plain text',
            self::Php => 'PHP',
            self::JavaScript => 'JavaScript',
            self::TypeScript => 'TypeScript',
            self::Python => 'Python',
            self::Go => 'Go',
            self::Rust => 'Rust',
            self::Java => 'Java',
            self::CSharp => 'C#',
            self::Ruby => 'Ruby',
            self::Sql => 'SQL',
            self::Bash => 'Bash',
            self::Json => 'JSON',
            self::Yaml => 'YAML',
            self::Html => 'HTML',
            self::Css => 'CSS',
        };
    }
}
