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

        if ($columns === []) {
            return 'protected $fillable = [];';
        }

        $list = implode("',\n    '", $columns);

        return "protected \$fillable = [\n    '{$list}'\n];";
    }

    protected static function renderAttribute(array $columns): string
    {
        if ($columns === []) {
            return '#[Fillable([])]';
        }

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
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $import = 'use Illuminate\Database\Eloquent\Attributes\Fillable;';

        if (! str_contains($header, 'Illuminate\Database\Eloquent\Attributes\Fillable')) {
            if (preg_match_all('/^use\s+[^;]+;\V*\R/m', $header, $uses, PREG_OFFSET_CAPTURE)) {
                $last = $uses[0][array_key_last($uses[0])];
                $position = $last[1] + strlen($last[0]);
                $header = substr($header, 0, $position).$import.$eol.substr($header, $position);
            } elseif (preg_match('/^namespace\s+[^;]+;\V*\R/m', $header, $namespace, PREG_OFFSET_CAPTURE)) {
                $position = $namespace[0][1] + strlen($namespace[0][0]);
                $header = substr($header, 0, $position).$eol.$import.$eol.substr($header, $position);
            } elseif (preg_match('/^<\?php\V*\R/', $header, $openTag, PREG_OFFSET_CAPTURE)) {
                $position = $openTag[0][1] + strlen($openTag[0][0]);
                $header = substr($header, 0, $position).$eol.$import.$eol.substr($header, $position);
            }
        }

        return static::placeSortedAttribute($header, $body, $attribute);
    }

    protected static function placeSortedAttribute(string $header, string $body, string $attribute): string
    {
        $eol = str_contains($header, "\r\n") || str_contains($body, "\r\n") ? "\r\n" : "\n";
        $attribute = str_replace("\n", $eol, str_replace("\r\n", "\n", $attribute));
        $parsed = static::trailingAttributes($header);

        if ($parsed === null) {
            return rtrim($header).$eol.$eol.$attribute.$eol.$body;
        }

        [$prefix, $blocks, $suffix] = $parsed;

        $insertAt = count($blocks);

        foreach ($blocks as $index => $block) {
            if (strcasecmp($block['name'], 'Fillable') > 0) {
                $insertAt = $index;

                break;
            }
        }

        array_splice($blocks, $insertAt, 0, [[
            'name' => 'Fillable',
            'text' => $attribute,
        ]]);

        $prefix = rtrim($prefix);
        $separator = str_ends_with($prefix, '*/') ? $eol : $eol.$eol;
        $rendered = implode($eol, array_column($blocks, 'text'));
        $suffix = trim($suffix);

        return $prefix.$separator.$rendered.($suffix === '' ? '' : $eol.$suffix).$eol.$body;
    }

    protected static function trailingAttributes(string $header): ?array
    {
        if (! defined('T_ATTRIBUTE')) {
            return null;
        }

        $tokens = token_get_all($header);
        $count = count($tokens);
        $i = $count - 1;

        while ($i >= 0 && static::isIgnorableToken($tokens[$i])) {
            $i--;
        }

        if ($i < 0 || $tokens[$i] !== ']') {
            return null;
        }

        $clusterStart = null;

        while ($i >= 0) {
            if (static::isIgnorableToken($tokens[$i])) {
                $i--;

                continue;
            }

            if ($tokens[$i] !== ']') {
                break;
            }

            $attributeStart = static::attributeStartIndex($tokens, $i);

            if ($attributeStart === null) {
                break;
            }

            $clusterStart = $attributeStart;
            $i = $attributeStart - 1;
        }

        if ($clusterStart === null) {
            return null;
        }

        $offsets = static::tokenOffsets($tokens);
        $blocks = [];
        $pending = '';
        $eol = str_contains($header, "\r\n") ? "\r\n" : "\n";

        for ($i = $clusterStart; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $end = static::attributeEndIndex($tokens, $i);
                $text = trim(substr($header, $offsets[$i], $offsets[$end] + strlen($tokens[$end]) - $offsets[$i]));

                if (trim($pending) !== '') {
                    $text = rtrim($pending).$eol.$text;
                }

                $blocks[] = [
                    'name' => static::attributeName($tokens, $i),
                    'text' => $text,
                    'index' => count($blocks),
                ];
                $pending = '';
                $i = $end;

                continue;
            }

            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $pending .= $token[1];
            }
        }

        return [substr($header, 0, $offsets[$clusterStart]), $blocks, $pending];
    }

    protected static function attributeStartIndex(array $tokens, int $endIndex): ?int
    {
        $depth = 0;

        for ($i = $endIndex; $i >= 0; $i--) {
            $token = $tokens[$i];

            if ($token === ']') {
                $depth++;

                continue;
            }

            if ($token === '[') {
                $depth--;

                continue;
            }

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    protected static function attributeEndIndex(array $tokens, int $startIndex): int
    {
        $depth = 0;

        for ($i = $startIndex, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $depth++;

                continue;
            }

            if ($token === '[') {
                $depth++;

                continue;
            }

            if ($token === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $startIndex;
    }

    protected static function attributeName(array $tokens, int $startIndex): string
    {
        $nameTokens = [T_STRING];

        if (defined('T_NAME_QUALIFIED')) {
            $nameTokens[] = T_NAME_QUALIFIED;
        }

        if (defined('T_NAME_FULLY_QUALIFIED')) {
            $nameTokens[] = T_NAME_FULLY_QUALIFIED;
        }

        $name = '';

        for ($i = $startIndex + 1, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_NS_SEPARATOR], true)) {
                continue;
            }

            if (is_array($token) && in_array($token[0], $nameTokens, true)) {
                $parts = preg_split('/\\\\/', ltrim($token[1], '\\')) ?: [];
                $name = (string) end($parts);

                continue;
            }

            break;
        }

        return $name;
    }

    protected static function isIgnorableToken($token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    protected static function tokenOffsets(array $tokens): array
    {
        $offset = 0;
        $offsets = [];

        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }

        return $offsets;
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
