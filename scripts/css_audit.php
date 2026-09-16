<?php
declare(strict_types=1);

/**
 * Audit the three production custom stylesheets.
 *
 * The command fails if the physical-line budget is exceeded or an exact
 * selector/declaration block occurs twice in the same at-rule context.
 */

const SMARTQMS_CSS_LINE_BUDGET = 7650;

$root = dirname(__DIR__);
$relativeFiles = [
    'assets/css/style.css',
    'assets/css/admin.css',
    'assets/css/display.css',
];

function cssNormalize(string $value): string
{
    $value = preg_replace('~/\*.*?\*/~s', '', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function cssBraceBalance(string $css): int
{
    $depth = 0;
    $quote = null;
    $length = strlen($css);
    for ($index = 0; $index < $length; $index++) {
        if ($quote === null && substr($css, $index, 2) === '/*') {
            $end = strpos($css, '*/', $index + 2);
            $index = $end === false ? $length : $end + 1;
            continue;
        }
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
        } elseif ($character === '{') {
            $depth++;
        } elseif ($character === '}') {
            $depth--;
            if ($depth < 0) {
                return $depth;
            }
        }
    }
    return $depth;
}

/** @return array<int, array{selector:string, body:string, context:string}> */
function cssRules(string $css, array $context = []): array
{
    $rules = [];
    $length = strlen($css);
    $start = 0;

    for ($index = 0; $index < $length;) {
        if (substr($css, $index, 2) === '/*') {
            $end = strpos($css, '*/', $index + 2);
            $index = $end === false ? $length : $end + 2;
            if ($start < $index) {
                $start = $index;
            }
            continue;
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
                $start = $index + 1;
                $index++;
                continue 2;
            }
            if ($character === '{') {
                break;
            }
        }

        if ($index >= $length) {
            break;
        }

        $prelude = cssNormalize(substr($css, $start, $index - $start));
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
        $start = $index;
        if ($prelude === '') {
            continue;
        }

        if (preg_match('/^@(media|supports|container|layer|document|keyframes|-webkit-keyframes)\b/i', $prelude)) {
            $rules = array_merge($rules, cssRules($body, [...$context, $prelude]));
            continue;
        }

        $rules[] = [
            'selector' => $prelude,
            'body' => cssNormalize($body),
            'context' => implode(' > ', array_map('cssNormalize', $context)),
        ];
    }

    return $rules;
}

function cssWithoutPrintBlocks(string $css): string
{
    $result = '';
    $offset = 0;
    while (preg_match('/@media\s+print\s*\{/i', $css, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $begin = $match[0][1];
        $open = $begin + strlen($match[0][0]) - 1;
        $depth = 1;
        $index = $open + 1;
        $length = strlen($css);
        while ($index < $length && $depth > 0) {
            if (substr($css, $index, 2) === '/*') {
                $end = strpos($css, '*/', $index + 2);
                $index = $end === false ? $length : $end + 2;
                continue;
            }
            if ($css[$index] === '{') {
                $depth++;
            } elseif ($css[$index] === '}') {
                $depth--;
            }
            $index++;
        }
        $result .= substr($css, $offset, $begin - $offset);
        $offset = $index;
    }
    return $result . substr($css, $offset);
}

$totalLines = 0;
$totalImportant = 0;
$totalNonPrintImportant = 0;
$totalMedia = 0;
$totalColors = 0;
$duplicates = [];
$unbalanced = [];

foreach ($relativeFiles as $relativeFile) {
    $path = $root . '/' . $relativeFile;
    $css = file_get_contents($path);
    if ($css === false) {
        fwrite(STDERR, "Missing stylesheet: {$relativeFile}\n");
        exit(1);
    }

    $lines = substr_count($css, "\n") + (str_ends_with($css, "\n") ? 0 : 1);
    $important = substr_count(strtolower($css), '!important');
    $nonPrintImportant = substr_count(strtolower(cssWithoutPrintBlocks($css)), '!important');
    $media = preg_match_all('/@media\b/i', $css);
    $colors = preg_match_all('/#[0-9a-f]{3,8}\b/i', $css);
    $totalLines += $lines;
    $totalImportant += $important;
    $totalNonPrintImportant += $nonPrintImportant;
    $totalMedia += $media;
    $totalColors += $colors;
    $balance = cssBraceBalance($css);
    if ($balance !== 0) {
        $unbalanced[] = "{$relativeFile} (balance {$balance})";
    }

    $seen = [];
    foreach (cssRules($css) as $rule) {
        $key = $rule['context'] . "\0" . $rule['selector'] . "\0" . $rule['body'];
        if (isset($seen[$key])) {
            $duplicates[] = "{$relativeFile}: {$rule['selector']}" . ($rule['context'] !== '' ? " [{$rule['context']}]" : '');
        } else {
            $seen[$key] = true;
        }
    }

    printf(
        "%s: lines=%d important=%d non_print_important=%d media=%d hardcoded_colors=%d\n",
        $relativeFile,
        $lines,
        $important,
        $nonPrintImportant,
        $media,
        $colors
    );
}

printf(
    "TOTAL: lines=%d/%d important=%d non_print_important=%d media=%d hardcoded_colors=%d duplicates=%d\n",
    $totalLines,
    SMARTQMS_CSS_LINE_BUDGET,
    $totalImportant,
    $totalNonPrintImportant,
    $totalMedia,
    $totalColors,
    count($duplicates)
);

$failed = false;
if ($totalLines > SMARTQMS_CSS_LINE_BUDGET) {
    fwrite(STDERR, "CSS line budget exceeded by " . ($totalLines - SMARTQMS_CSS_LINE_BUDGET) . " lines.\n");
    $failed = true;
}
if ($duplicates !== []) {
    fwrite(STDERR, "Exact duplicate selector blocks:\n - " . implode("\n - ", $duplicates) . "\n");
    $failed = true;
}
if ($unbalanced !== []) {
    fwrite(STDERR, "Unbalanced stylesheet braces: " . implode(', ', $unbalanced) . "\n");
    $failed = true;
}

exit($failed ? 1 : 0);
