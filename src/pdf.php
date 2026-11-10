<?php
declare(strict_types=1);

function pdf_filename(string $label): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) ?? '';
    $name = trim((string)$name, '-.');
    if ($name === '') {
        $name = 'inventory';
    }
    return substr($name, 0, 80) . '.pdf';
}

function send_pdf(string $filename, string $bytes): never
{
    if (preg_match('/^[A-Za-z0-9._-]+$/', $filename) !== 1) {
        $filename = 'inventory.pdf';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

function pdf_encode(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (function_exists('iconv')) {
        $out = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if (is_string($out)) {
            return $out;
        }
    }
    return preg_replace('/[^\x09\x0A\x20-\x7E]/', '?', $text) ?? '';
}

function pdf_literal(string $bytes): string
{
    $out = '';
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $char = $bytes[$i];
        $ord = ord($char);
        if ($char === '\\' || $char === '(' || $char === ')') {
            $out .= '\\' . $char;
        } elseif ($ord < 32 || $ord > 126) {
            $out .= sprintf('\\%03o', $ord);
        } else {
            $out .= $char;
        }
    }
    return $out;
}

function pdf_char_width(int $ord): int
{
    static $widths = null;
    if ($widths === null) {
        $widths = array_fill(0, 256, 556);
        $known = [
            32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
            40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
            48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
            58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015,
            65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278, 74 => 500,
            75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611,
            85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469,
            95 => 556, 96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
            104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556, 112 => 556,
            113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500,
            122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
        ];
        foreach ($known as $code => $width) {
            $widths[$code] = $width;
        }
    }
    return $widths[$ord] ?? 556;
}

function pdf_string_width(string $bytes, float $size): float
{
    $width = 0;
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $width += pdf_char_width(ord($bytes[$i]));
    }
    return $width / 1000 * $size;
}

function pdf_wrap(string $bytes, float $max, float $size): array
{
    $lines = [];
    foreach (explode("\n", $bytes) as $part) {
        $words = $part === '' ? [''] : (preg_split('/ +/', $part) ?: ['']);
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && pdf_string_width($candidate, $size) > $max) {
                $lines[] = $line;
                $line = '';
                $candidate = $word;
            }
            if ($candidate !== '' && pdf_string_width($candidate, $size) > $max) {
                $chunk = '';
                $length = strlen($candidate);
                for ($i = 0; $i < $length; $i++) {
                    $next = $chunk . $candidate[$i];
                    if ($chunk !== '' && pdf_string_width($next, $size) > $max) {
                        $lines[] = $chunk;
                        $chunk = $candidate[$i];
                    } else {
                        $chunk = $next;
                    }
                }
                $line = $chunk;
                continue;
            }
            $line = $candidate;
        }
        $lines[] = $line;
    }
    if (!$lines) {
        $lines[] = '';
    }
    if (count($lines) > 8) {
        $lines = array_slice($lines, 0, 8);
        $last = rtrim($lines[7], '. ');
        $lines[7] = $last . '...';
    }
    return $lines;
}

function pdf_fit_columns(array $columns, float $width): array
{
    $sum = 0.0;
    foreach ($columns as $column) {
        $sum += (float)$column['weight'];
    }
    if ($sum <= 0) {
        $sum = 1;
    }
    $used = 0.0;
    $last = count($columns) - 1;
    $fitted = [];
    foreach ($columns as $index => $column) {
        $columnWidth = $index === $last ? $width - $used : $width * ((float)$column['weight'] / $sum);
        $fitted[] = ['label' => (string)$column['label'], 'width' => $columnWidth];
        $used += $columnWidth;
    }
    return $fitted;
}

function pdf_document(string $title, string $subtitle, array $sections): string
{
    $pageWidth = 792.0;
    $pageHeight = 612.0;
    $margin = 32.0;
    $contentWidth = $pageWidth - ($margin * 2);
    $bottom = $pageHeight - 30.0;
    $font = 7.5;
    $leading = 9.0;
    $pad = 3.0;
    $pages = [];
    $ops = '';
    $y = $margin;
    $pageNumber = 1;

    $text = static function (string $fontName, float $size, float $r, float $g, float $b, float $x, float $baseline, string $bytes) use (&$ops): void {
        $ops .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $fontName,
            $size,
            $r,
            $g,
            $b,
            $x,
            $baseline,
            pdf_literal($bytes)
        );
    };
    $fill = static function (float $x, float $y, float $w, float $h, float $r, float $g, float $b) use (&$ops, $pageHeight): void {
        $ops .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $r, $g, $b, $x, $pageHeight - $y - $h, $w, $h);
    };
    $rule = static function (float $x, float $y, float $w) use (&$ops, $pageHeight): void {
        $line = $pageHeight - $y;
        $ops .= sprintf("0.78 0.80 0.76 RG %.2F %.2F m %.2F %.2F l S\n", $x, $line, $x + $w, $line);
    };
    $finish = static function () use (&$pages, &$ops): void {
        $pages[] = $ops;
        $ops = '';
    };
    $open = static function (bool $first) use (&$y, &$pageNumber, $margin, $text, $finish, $title, $pageWidth): void {
        if ($pageNumber > 1 || !$first) {
            $finish();
            $pageNumber++;
        }
        $y = $margin;
        if ($first && $pageNumber === 1) {
            return;
        }
        $text('F2', 9, 0.08, 0.12, 0.09, $margin, 612 - $y - 9, pdf_encode($title));
        $y += 18;
    };

    $text('F2', 16, 0.08, 0.12, 0.09, $margin, $pageHeight - $y - 16, pdf_encode($title));
    $y += 22;
    $text('F1', 9, 0.35, 0.38, 0.34, $margin, $pageHeight - $y - 9, pdf_encode($subtitle));
    $y += 18;

    $drawRows = static function (string $label, array $columns, array $rows) use (
        &$y, $bottom, $margin, $contentWidth, $font, $leading, $pad, $pageHeight, $text, $fill, $rule, $open
    ): void {
        $fitted = pdf_fit_columns($columns, $contentWidth);
        $headerLines = [];
        $headerHeight = $leading + ($pad * 2);
        foreach ($fitted as $index => $column) {
            $lines = pdf_wrap(pdf_encode($column['label']), $column['width'] - ($pad * 2), $font);
            $headerLines[$index] = $lines;
            $headerHeight = max($headerHeight, (count($lines) * $leading) + ($pad * 2));
        }
        $measured = [];
        foreach ($rows as $row) {
            $cellLines = [];
            $height = $leading + ($pad * 2);
            foreach ($fitted as $index => $column) {
                $value = pdf_encode((string)($row[$index] ?? ''));
                $lines = pdf_wrap($value, $column['width'] - ($pad * 2), $font);
                $cellLines[$index] = $lines;
                $height = max($height, (count($lines) * $leading) + ($pad * 2));
            }
            $measured[] = ['lines' => $cellLines, 'height' => $height];
        }
        $paintHeader = static function () use (&$y, $fitted, $headerLines, $headerHeight, $margin, $contentWidth, $font, $leading, $pad, $pageHeight, $text, $fill): void {
            $fill($margin, $y, $contentWidth, $headerHeight, 0.09, 0.14, 0.11);
            $x = $margin;
            foreach ($fitted as $index => $column) {
                $baseline = $pageHeight - $y - $pad - $font;
                foreach ($headerLines[$index] as $lineNumber => $line) {
                    $text('F2', $font, 1, 1, 1, $x + $pad, $baseline - ($lineNumber * $leading), $line);
                }
                $x += $column['width'];
            }
            $y += $headerHeight;
        };
        $paintLabel = static function () use (&$y, $label, $margin, $pageHeight, $text): void {
            if ($label === '') {
                return;
            }
            $text('F2', 10, 0.08, 0.16, 0.08, $margin, $pageHeight - $y - 10, pdf_encode($label));
            $y += 16;
        };
        $breakPage = static function () use ($open, $paintLabel, $paintHeader): void {
            $open(false);
            $paintLabel();
            $paintHeader();
        };

        if ($y + ($label !== '' ? 16 : 0) + $headerHeight + 14 > $bottom) {
            $open(false);
        }
        $paintLabel();
        $paintHeader();
        if (!$measured) {
            $text('F1', $font, 0.35, 0.38, 0.34, $margin + $pad, $pageHeight - $y - $pad - $font, pdf_encode('None'));
            $y += $leading + ($pad * 2);
            $rule($margin, $y, $contentWidth);
            return;
        }
        foreach ($measured as $index => $row) {
            if ($y + $row['height'] > $bottom) {
                $breakPage();
            }
            if ($index % 2 === 1) {
                $fill($margin, $y, $contentWidth, $row['height'], 0.95, 0.96, 0.94);
            }
            $x = $margin;
            foreach ($fitted as $cell => $column) {
                $baseline = $pageHeight - $y - $pad - $font;
                foreach ($row['lines'][$cell] as $lineNumber => $line) {
                    $text('F1', $font, 0.1, 0.12, 0.1, $x + $pad, $baseline - ($lineNumber * $leading), $line);
                }
                $x += $column['width'];
            }
            $y += $row['height'];
            $rule($margin, $y, $contentWidth);
        }
        $y += 12;
    };

    if (!$sections) {
        $text('F1', 10, 0.35, 0.38, 0.34, $margin, $pageHeight - $y - 10, pdf_encode('Nothing to export.'));
    }
    foreach ($sections as $section) {
        $heading = (string)($section['heading'] ?? '');
        if ($heading !== '') {
            if ($y + 28 > $bottom) {
                $open(false);
            }
            $text('F2', 12, 0.08, 0.12, 0.09, $margin, $pageHeight - $y - 12, pdf_encode($heading));
            $y += 18;
        }
        foreach ($section['tables'] as $table) {
            $drawRows((string)($table['label'] ?? ''), $table['columns'], $table['rows']);
        }
    }
    $finish();

    $objects = [];
    $fontRegular = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $fontBold = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $pageIds = [];
    $next = 5;
    $contentIds = [];
    foreach ($pages as $index => $content) {
        $pageIds[] = $next;
        $contentIds[] = $next + 1;
        $footer = sprintf(
            "BT /F1 8 Tf 0.40 0.42 0.38 rg 1 0 0 1 %.2F 16 Tm (%s) Tj ET\nBT /F1 8 Tf 0.40 0.42 0.38 rg 1 0 0 1 %.2F 16 Tm (%s) Tj ET\n",
            $margin,
            pdf_literal(pdf_encode('PT Inventory Manager')),
            $pageWidth - $margin - pdf_string_width(pdf_encode('Page ' . ($index + 1)), 8),
            pdf_literal(pdf_encode('Page ' . ($index + 1)))
        );
        $stream = $content . $footer;
        $objects[$next + 1] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $next += 2;
    }
    foreach ($pageIds as $index => $pageId) {
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentIds[$index] . ' 0 R >>';
    }
    $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds));
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Count ' . count($pageIds) . ' /Kids [' . $kids . '] >>';
    $objects[3] = $fontRegular;
    $objects[4] = $fontBold;

    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $start = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
    for ($id = 1; $id < $count; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$start\n%%EOF\n";
    return $pdf;
}

function pdf_app_table(array $rows, bool $withZone, bool $withTag): array
{
    $columns = [['label' => 'VM Name', 'weight' => 14]];
    if ($withZone) {
        $columns[] = ['label' => 'Environment', 'weight' => 9];
    }
    $columns[] = ['label' => 'Deployment', 'weight' => 9];
    if ($withTag) {
        $columns[] = ['label' => 'Tag', 'weight' => 8];
    }
    array_push(
        $columns,
        ['label' => 'OS', 'weight' => 7],
        ['label' => 'Internal IP', 'weight' => 11],
        ['label' => 'External IP', 'weight' => 11],
        ['label' => 'Host', 'weight' => 11],
        ['label' => 'CV', 'weight' => 5],
        ['label' => 'URL', 'weight' => 16],
        ['label' => 'Applications', 'weight' => 10]
    );
    $body = [];
    foreach ($rows as $row) {
        $cells = [(string)$row['name']];
        if ($withZone) {
            $cells[] = (string)($row['zone_name'] ?? '');
        }
        $cells[] = (string)($row['environment_name'] ?? '');
        if ($withTag) {
            $cells[] = (string)($row['stack_name'] ?? '');
        }
        $urls = $row['urls'] ?? [];
        if (!$urls && trim((string)($row['url'] ?? '')) !== '') {
            $urls = [(string)$row['url']];
        }
        array_push(
            $cells,
            (string)($row['os_name'] ?? ''),
            (string)($row['internal_ip'] ?? ''),
            (string)($row['external_ip'] ?? ''),
            (string)($row['host'] ?? ''),
            (int)($row['cv_backup'] ?? 0) === 1 ? 'Yes' : 'No',
            implode("\n", $urls),
            joined_names($row['applications'] ?? [])
        );
        $body[] = $cells;
    }
    return ['label' => 'APP', 'columns' => $columns, 'rows' => $body];
}

function pdf_db_table(array $rows, bool $withZone, bool $withTag): array
{
    $columns = [['label' => 'VM Name', 'weight' => 14]];
    if ($withZone) {
        $columns[] = ['label' => 'Environment', 'weight' => 10];
    }
    $columns[] = ['label' => 'Deployment', 'weight' => 10];
    if ($withTag) {
        $columns[] = ['label' => 'Tag', 'weight' => 9];
    }
    array_push(
        $columns,
        ['label' => 'OS', 'weight' => 8],
        ['label' => 'Internal IP', 'weight' => 12],
        ['label' => 'Host', 'weight' => 12],
        ['label' => 'CV', 'weight' => 5],
        ['label' => 'Redis', 'weight' => 6],
        ['label' => 'APP VMs', 'weight' => 16]
    );
    $body = [];
    foreach ($rows as $row) {
        $cells = [(string)$row['name']];
        if ($withZone) {
            $cells[] = (string)($row['zone_name'] ?? '');
        }
        $cells[] = (string)($row['environment_name'] ?? '');
        if ($withTag) {
            $cells[] = (string)($row['stack_name'] ?? '');
        }
        array_push(
            $cells,
            (string)($row['os_name'] ?? ''),
            (string)($row['internal_ip'] ?? ''),
            (string)($row['host'] ?? ''),
            (int)($row['cv_backup'] ?? 0) === 1 ? 'Yes' : 'No',
            (int)($row['redis'] ?? 0) === 1 ? 'Yes' : 'No',
            joined_names($row['app_vms'] ?? [])
        );
        $body[] = $cells;
    }
    return ['label' => 'DB', 'columns' => $columns, 'rows' => $body];
}

function pdf_tag_sections(array $groups): array
{
    $sections = [];
    $looseApps = [];
    $looseDbs = [];
    foreach ($groups as $group) {
        if ((int)($group['stack_id'] ?? 0) <= 0) {
            foreach ($group['apps'] as $row) {
                $looseApps[] = $row;
            }
            foreach ($group['dbs'] as $row) {
                $looseDbs[] = $row;
            }
            continue;
        }
        $tables = [];
        if ($group['apps']) {
            $tables[] = pdf_app_table($group['apps'], false, false);
        }
        if ($group['dbs']) {
            $tables[] = pdf_db_table($group['dbs'], false, false);
        }
        if ($tables) {
            $sections[] = ['heading' => 'Tag ' . (string)$group['name'], 'tables' => $tables];
        }
    }
    if ($looseApps || $looseDbs) {
        $tables = [];
        if ($looseApps) {
            $tables[] = pdf_app_table($looseApps, false, false);
        }
        if ($looseDbs) {
            $tables[] = pdf_db_table($looseDbs, false, false);
        }
        $sections[] = ['heading' => 'No tag', 'tables' => $tables];
    }
    return $sections;
}
