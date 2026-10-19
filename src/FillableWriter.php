<?php

namespace Mitoop\LaravelGenFillable;

class FillableWriter
{
    public static function usesAttribute(): bool
    {
        return class_exists(\Illuminate\Database\Eloquent\Attributes\Fillable::class);
    }

    public static function alreadyDefined(string $content): ?string
    {
        if (static::usesAttribute() && preg_match('/#\[\s*(?:\\\\Illuminate\\\\Database\\\\Eloquent\\\\Attributes\\\\)?Fillable\b/', $content) === 1) {
            return 'The model already has a Fillable attribute.';
        }

        if (str_contains($content, 'protected $fillable')) {
            return 'The model already has a $fillable property.';
        }

        return null;
    }

    public static function render(array $columns): string
    {
        $columns = array_values($columns);

        if (static::usesAttribute()) {
            return static::renderAttribute($columns);
        }

        $list = implode("',\n    '", $columns);

        return "protected \$fillable = [\n    '{$list}'\n];";
    }

    protected static function renderAttribute(array $columns): string
    {
        $inline = "#[Fillable(['".implode("', '", $columns)."'])]";

        if (strlen($inline) <= 120) {
            return $inline;
        }

        $list = implode("',\n    '", $columns);

        return "#[Fillable([\n    '{$list}',\n])]";
    }

    public static function clipboard(array $columns): string
    {
        $fillable = static::render($columns);

        if (! static::usesAttribute()) {
            return $fillable;
        }

        return "use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;\n\n".$fillable;
    }

    public static function insert(string $content, array $columns): string
    {
        $fillable = static::render($columns);

        if (static::usesAttribute()) {
            return static::insertAttribute($content, $fillable);
        }

        return static::insertProperty($content, $fillable);
    }

    protected static function insertAttribute(string $content, string $attribute): string
    {
        if (! preg_match('/^(?:(?:abstract|final|readonly)\s+)*class\s+\w+/m', $content, $match, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $header = substr($content, 0, $match[0][1]);
        $body = substr($content, $match[0][1]);
        $import = 'use Illuminate\Database\Eloquent\Attributes\Fillable;';

        if (! str_contains($header, 'Illuminate\Database\Eloquent\Attributes\Fillable')) {
            if (preg_match_all('/^use\s+[^;]+;\V*\R/m', $header, $uses, PREG_OFFSET_CAPTURE)) {
                $last = $uses[0][array_key_last($uses[0])];
                $position = $last[1] + strlen($last[0]);
                $header = substr($header, 0, $position).$import."\n".substr($header, $position);
            } elseif (preg_match('/^namespace\s+[^;]+;\V*\R/m', $header, $namespace, PREG_OFFSET_CAPTURE)) {
                $position = $namespace[0][1] + strlen($namespace[0][0]);
                $header = substr($header, 0, $position)."\n".$import."\n".substr($header, $position);
            } elseif (preg_match('/^<\?php\V*\R/', $header, $openTag, PREG_OFFSET_CAPTURE)) {
                $position = $openTag[0][1] + strlen($openTag[0][0]);
                $header = substr($header, 0, $position)."\n".$import."\n".substr($header, $position);
            }
        }

        $header = rtrim($header);
        $separator = str_ends_with($header, ']') ? "\n" : "\n\n";

        return $header.$separator.$attribute."\n".$body;
    }

    protected static function insertProperty(string $content, string $fillable): string
    {
        return preg_replace_callback(
            '/(class\s+\w+\s+[^{]*\{)(\s*(?:use\s+[^;]+;\s*)*)/m',
            function ($matches) use ($fillable) {
                $classStart = $matches[1];
                $useBlock = $matches[2];

                return $classStart.$useBlock."\n\n    ".$fillable."\n";
            },
            $content,
            1
        ) ?? $content;
    }
}
