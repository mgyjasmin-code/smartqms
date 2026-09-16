<?php
declare(strict_types=1);

/**
 * Normalize production CSS to one rule per line and discard exact duplicate
 * rules in the same at-rule context. This is intentionally build-free: the
 * committed files remain the browser-ready source files.
 */

$root = dirname(__DIR__);
$files = [
    $root . '/assets/css/style.css',
    $root . '/assets/css/admin.css',
    $root . '/assets/css/display.css',
];

function compactWhitespace(string $value): string
{
    $output = '';
    $pendingSpace = false;
    $quote = null;
    $length = strlen($value);

    for ($index = 0; $index < $length; $index++) {
        if ($quote === null && substr($value, $index, 2) === '/*') {
            $end = strpos($value, '*/', $index + 2);
            $index = $end === false ? $length : $end + 1;
            $pendingSpace = true;
            continue;
        }

        $character = $value[$index];
        if ($quote !== null) {
            $output .= $character;
            if ($character === '\\' && $index + 1 < $length) {
                $output .= $value[++$index];
            } elseif ($character === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($character === '"' || $character === "'") {
            if ($pendingSpace && $output !== '' && !str_ends_with($output, ' ')) {
                $output .= ' ';
            }
            $pendingSpace = false;
            $quote = $character;
            $output .= $character;
            continue;
        }

        if (ctype_space($character)) {
            $pendingSpace = true;
            continue;
        }

        if ($pendingSpace && $output !== '' && !str_ends_with($output, ' ')) {
            $output .= ' ';
        }
        $pendingSpace = false;
        $output .= $character;
    }

    return trim($output);
}

function isGroupedAtRule(string $prelude): bool
{
    return (bool) preg_match('/^@(media|supports|container|layer|document|keyframes|-webkit-keyframes)\b/i', $prelude);
}

function contextKeepsImportant(array $context): bool
{
    foreach ($context as $item) {
        if (preg_match('/^@media\s+(?:print\b|.*prefers-reduced-motion)/i', $item)) {
            return true;
        }
    }
    return false;
}

/** @return array<int, string> */
function serializeCss(string $css, array $context, array &$seen, int &$duplicates): array
{
    $lines = [];
    $length = strlen($css);
    $start = 0;

    for ($index = 0; $index < $length;) {
        while ($index < $length) {
            if (ctype_space($css[$index])) {
                $index++;
                continue;
            }
            if (substr($css, $index, 2) === '/*') {
                $end = strpos($css, '*/', $index + 2);
                $index = $end === false ? $length : $end + 2;
                continue;
            }
            break;
        }
        $start = $index;
        if ($index >= $length) {
            break;
        }

        $quote = null;
        for (; $index < $length; $index++) {
            $character = $css[$index];
            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === ';') {
                $statement = compactWhitespace(substr($css, $start, $index - $start));
                if ($statement !== '') {
                    $lines[] = $statement . ';';
                }
                $index++;
                continue 2;
            }
            if ($character === '{') {
                break;
            }
        }

        if ($index >= $length) {
            $tail = compactWhitespace(substr($css, $start));
            if ($tail !== '') {
                $lines[] = $tail;
            }
            break;
        }

        $prelude = compactWhitespace(substr($css, $start, $index - $start));
        $bodyStart = ++$index;
        $depth = 1;
        $quote = null;
        while ($index < $length && $depth > 0) {
            if ($quote === null && substr($css, $index, 2) === '/*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                $index = $commentEnd === false ? $length : $commentEnd + 2;
                continue;
            }
            $character = $css[$index];
            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
            }
            $index++;
        }

        $body = substr($css, $bodyStart, max(0, $index - $bodyStart - 1));
        if ($prelude === '') {
            continue;
        }

        if (isGroupedAtRule($prelude)) {
            $childLines = serializeCss($body, [...$context, $prelude], $seen, $duplicates);
            if ($childLines !== []) {
                $lines[] = $prelude . ' {';
                foreach ($childLines as $childLine) {
                    $lines[] = '  ' . $childLine;
                }
                $lines[] = '}';
            }
            continue;
        }

        $compactBody = compactWhitespace($body);
        if (!contextKeepsImportant($context)) {
            $compactBody = preg_replace('/\s*!important\b/i', '', $compactBody) ?? $compactBody;
        }
        $key = implode(' > ', $context) . "\0" . $prelude . "\0" . $compactBody;
        if (isset($seen[$key])) {
            $duplicates++;
            continue;
        }
        $seen[$key] = true;
        $lines[] = $prelude . ' { ' . $compactBody . ' }';
    }

    return $lines;
}

foreach ($files as $file) {
    $css = file_get_contents($file);
    if ($css === false) {
        fwrite(STDERR, "Unable to read {$file}.\n");
        exit(1);
    }

    $seen = [];
    $duplicates = 0;
    $lines = serializeCss($css, [], $seen, $duplicates);
    $output = implode(PHP_EOL, $lines) . PHP_EOL;
    if (file_put_contents($file, $output, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write {$file}.\n");
        exit(1);
    }
    printf("Compacted %s to %d lines; removed %d exact duplicates.\n", basename($file), count($lines), $duplicates);
}
