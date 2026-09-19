<?php

namespace Domain\Requests\Support;

final class SimplePdf
{
    /** @var list<string> */
    private array $pages = [];

    /** @var list<string> */
    private array $ops = [];

    private float $y;

    private float $pageW;

    private float $pageH;

    private float $margin = 36;

    private string $title;

    private string $code;

    private string $procedure;

    private string $revision;

    private int $pageNo = 0;

    private string $generatedAt;

    public function __construct(
        string $title,
        string $code = '',
        ?string $procedure = null,
        bool $landscape = false,
        string $revision = 'Rev. 01'
    ) {
        $this->title = $title;
        $this->code = $code;
        $this->procedure = $procedure ?: 'Dirección de Transporte y Movilidad';
        $this->revision = $revision;
        $this->pageW = $landscape ? 842.0 : 595.0;
        $this->pageH = $landscape ? 595.0 : 842.0;
        $this->generatedAt = now()->format('d/m/Y H:i');
        $this->startPage();
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(26);
        $this->fillRect($this->margin, $this->y - 16, $this->innerWidth(), 16, 0.90, 0.93, 0.96);
        $this->ops[] = $this->textOp($this->margin + 6, $this->y - 12, 9, true, mb_strtoupper($text));
        $this->y -= 22;
    }

    public function pair(string $label, mixed $value): void
    {
        $this->fields([[$label, $value]], 1);
    }

    /**
     * @param  list<array{0: string, 1: mixed}>  $pairs
     */
    public function fields(array $pairs, int $cols = 2): void
    {
        if ($pairs === []) {
            return;
        }
        $cols = max(1, $cols);
        $gap = 6;
        $width = ($this->innerWidth() - ($gap * ($cols - 1))) / $cols;
        $rowH = 28;
        foreach (array_chunk($pairs, $cols) as $chunk) {
            $this->ensureSpace($rowH + 4);
            $x = $this->margin;
            foreach ($chunk as [$label, $value]) {
                $this->strokeRect($x, $this->y - $rowH, $width, $rowH);
                $this->ops[] = $this->textOp($x + 4, $this->y - 10, 7, true, $label);
                $this->ops[] = $this->textOp($x + 4, $this->y - 22, 9, false, $this->clip($this->plain($value), $width - 10, 9));
                $x += $width + $gap;
            }
            $this->y -= $rowH + 4;
        }
    }

    /**
     * @param  array<string, bool>  $items
     */
    public function checks(array $items, int $cols = 2): void
    {
        if ($items === []) {
            return;
        }
        $cols = max(1, $cols);
        $width = $this->innerWidth() / $cols;
        $rowH = 16;
        $i = 0;
        $rowItems = [];
        foreach ($items as $label => $checked) {
            $rowItems[] = [$label, $checked];
            $i++;
            if ($i % $cols === 0) {
                $this->drawCheckRow($rowItems, $width, $rowH);
                $rowItems = [];
            }
        }
        if ($rowItems !== []) {
            $this->drawCheckRow($rowItems, $width, $rowH);
        }
        $this->y -= 4;
    }

    public function paragraph(string $text): void
    {
        $this->ensureSpace(18);
        foreach ($this->wrap($text, $this->innerWidth() - 8, 9) as $line) {
            $this->ensureSpace(14);
            $this->ops[] = $this->textOp($this->margin + 2, $this->y - 10, 9, false, $line);
            $this->y -= 12;
        }
        $this->y -= 4;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<float>|null  $widths
     */
    public function table(array $headers, array $rows, ?array $widths = null): void
    {
        $colCount = max(1, count($headers));
        $widths = $this->normalizeWidths($widths, $colCount);
        $headerH = 18;
        $this->ensureSpace($headerH + 20);
        $this->drawTableRow($headers, $widths, $headerH, true);
        if ($rows === []) {
            $this->drawTableRow(array_fill(0, $colCount, 'Sin registros'), $widths, 16, false);
            $this->y -= 6;

            return;
        }
        foreach ($rows as $row) {
            $cells = [];
            for ($i = 0; $i < $colCount; $i++) {
                $cells[] = $this->plain($row[$i] ?? '');
            }
            $linesPerCol = [];
            $maxLines = 1;
            foreach ($cells as $i => $cell) {
                $wrapped = $this->wrap($cell, $widths[$i] - 6, 7);
                $linesPerCol[] = $wrapped;
                $maxLines = max($maxLines, count($wrapped));
            }
            $rowH = max(14, ($maxLines * 9) + 6);
            $this->ensureSpace($rowH + 4);
            $this->drawWrappedRow($linesPerCol, $widths, $rowH);
        }
        $this->y -= 8;
    }

    public function signatures(string ...$names): void
    {
        $slots = [];
        foreach ($names as $i => $name) {
            $slots[] = ['key' => 'slot_'.$i, 'label' => $name];
        }
        $this->signatureStamps($slots, []);
    }

    /**
     * @param  list<array{key: string, label: string}>  $slots
     * @param  array<string, array{name?: string, national_id?: string, signed_at?: string, hash?: string}>  $signed
     */
    public function signatureStamps(array $slots, array $signed = []): void
    {
        if ($slots === []) {
            return;
        }
        $this->heading('Firmas digitales');
        $this->note('Firma digital RSA-SHA256 del sistema de movilidad. Verificar validez en Documentos. No es el certificado acreditado de ULEAM.');
        $gap = 8;
        $width = min(175, ($this->innerWidth() - ($gap * (count($slots) - 1))) / max(1, count($slots)));
        $boxH = 78;
        $this->ensureSpace($boxH + 8);
        $x = $this->margin;
        $top = $this->y;
        foreach ($slots as $i => $slot) {
            if ($x + $width > $this->pageW - $this->margin + 2) {
                $this->y = $top - $boxH - 8;
                $this->ensureSpace($boxH + 8);
                $x = $this->margin;
                $top = $this->y;
            }
            $data = $signed[$slot['key']] ?? null;
            if ($data) {
                $this->fillRect($x, $top - $boxH, $width, $boxH, 0.93, 0.97, 0.94);
            }
            $this->strokeRect($x, $top - $boxH, $width, $boxH);
            $this->ops[] = $this->textOp($x + 6, $top - 12, 7, true, $slot['label']);
            if ($data) {
                $this->ops[] = $this->textOp($x + 6, $top - 26, 8, true, 'FIRMA DIGITAL');
                $this->ops[] = $this->textOp($x + 6, $top - 38, 8, false, $this->clip((string) ($data['name'] ?? ''), $width - 12, 8));
                $this->ops[] = $this->textOp($x + 6, $top - 50, 7, false, 'CI: '.$this->plain($data['national_id'] ?? ''));
                $this->ops[] = $this->textOp($x + 6, $top - 62, 7, false, (string) ($data['signed_at'] ?? ''));
                $this->ops[] = $this->textOp($x + 6, $top - 73, 6, false, 'Huella '.substr((string) ($data['hash'] ?? ''), 0, 16));
            } else {
                $this->ops[] = $this->textOp($x + 6, $top - 40, 8, false, 'Pendiente de firma');
            }
            $x += $width + $gap;
        }
        $this->y = $top - $boxH - 10;
    }

    public function note(string $text): void
    {
        $this->ensureSpace(16);
        $this->ops[] = $this->textOp($this->margin, $this->y - 10, 7, false, $text);
        $this->y -= 14;
    }

    public function output(): string
    {
        $this->flushPage();

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $pageCount = count($this->pages);
        $nextId = 3;
        $pageIds = [];
        $contentIds = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageIds[] = $nextId++;
            $contentIds[] = $nextId++;
        }
        $fontRegular = $nextId++;
        $fontBold = $nextId;
        $kidsRefs = implode(' ', array_map(fn (int $id) => $id.' 0 R', $pageIds));
        $objects[] = "<< /Type /Pages /Kids [{$kidsRefs}] /Count {$pageCount} >>";

        foreach ($this->pages as $i => $content) {
            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> >>',
                $this->pageW,
                $this->pageH,
                $contentIds[$i],
                $fontRegular,
                $fontBold
            );
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function startPage(): void
    {
        $this->pageNo++;
        $this->ops = [];
        $this->y = $this->pageH - $this->margin;
        $this->drawLetterhead();
    }

    private function drawLetterhead(): void
    {
        $top = $this->pageH - 18;
        $headerH = 52;
        $this->fillRect($this->margin, $top - $headerH, $this->innerWidth(), $headerH, 0.00, 0.16, 0.33);
        $this->ops[] = '1 1 1 rg';
        $this->ops[] = $this->textOp($this->margin + 8, $top - 16, 9, true, 'UNIVERSIDAD LAICA ELOY ALFARO DE MANABI');
        $this->ops[] = $this->textOp($this->margin + 8, $top - 28, 8, false, $this->procedure);
        $this->ops[] = $this->textOp($this->margin + 8, $top - 42, 11, true, mb_strtoupper($this->title));
        $right = $this->pageW - $this->margin - 120;
        if ($this->code !== '') {
            $this->ops[] = $this->textOp($right, $top - 16, 9, true, $this->code);
        }
        $this->ops[] = $this->textOp($right, $top - 28, 8, false, $this->revision);
        $this->ops[] = $this->textOp($right, $top - 42, 7, false, 'Respaldo fisico');
        $this->ops[] = '0 0 0 rg';
        $this->y = $top - $headerH - 10;
    }

    private function drawFooter(): void
    {
        $y = 22;
        $this->ops[] = sprintf('0.70 0.75 0.80 RG %.2f %.2f m %.2f %.2f l S 0 0 0 RG', $this->margin, 32, $this->pageW - $this->margin, 32);
        $left = 'ULEAM · Archivo institucional · Generado '.$this->generatedAt;
        if ($this->code !== '') {
            $left .= ' · '.$this->code;
        }
        $this->ops[] = $this->textOp($this->margin, $y, 7, false, $left);
        $this->ops[] = $this->textOp($this->pageW - $this->margin - 70, $y, 7, true, 'Pagina '.$this->pageNo);
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $items
     */
    private function drawCheckRow(array $items, float $width, float $rowH): void
    {
        $this->ensureSpace($rowH);
        $x = $this->margin;
        foreach ($items as [$label, $checked]) {
            $this->strokeRect($x, $this->y - 11, 9, 9);
            if ($checked) {
                $this->ops[] = sprintf('%.2f %.2f m %.2f %.2f l %.2f %.2f m %.2f %.2f l S', $x + 1.5, $this->y - 6.5, $x + 4, $this->y - 10, $x + 4, $this->y - 10, $x + 7.5, $this->y - 2.5);
            }
            $this->ops[] = $this->textOp($x + 13, $this->y - 10, 8, false, $this->clip($label, $width - 16, 8));
            $x += $width;
        }
        $this->y -= $rowH;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    private function drawTableRow(array $cells, array $widths, float $rowH, bool $header): void
    {
        $x = $this->margin;
        foreach ($cells as $i => $cell) {
            $w = $widths[$i] ?? 40;
            if ($header) {
                $this->fillRect($x, $this->y - $rowH, $w, $rowH, 0.00, 0.16, 0.33);
                $this->ops[] = '1 1 1 rg';
                $this->ops[] = $this->textOp($x + 3, $this->y - 12, 7, true, $this->clip($cell, $w - 6, 7));
                $this->ops[] = '0 0 0 rg';
            } else {
                $this->strokeRect($x, $this->y - $rowH, $w, $rowH);
                $this->ops[] = $this->textOp($x + 3, $this->y - 11, 7, false, $this->clip($cell, $w - 6, 7));
            }
            $x += $w;
        }
        $this->y -= $rowH;
    }

    /**
     * @param  list<list<string>>  $linesPerCol
     * @param  list<float>  $widths
     */
    private function drawWrappedRow(array $linesPerCol, array $widths, float $rowH): void
    {
        $x = $this->margin;
        foreach ($linesPerCol as $i => $lines) {
            $w = $widths[$i] ?? 40;
            $this->strokeRect($x, $this->y - $rowH, $w, $rowH);
            $ty = $this->y - 10;
            foreach ($lines as $line) {
                $this->ops[] = $this->textOp($x + 3, $ty, 7, false, $this->clip($line, $w - 6, 7));
                $ty -= 9;
            }
            $x += $w;
        }
        $this->y -= $rowH;
    }

    /**
     * @param  list<float>|null  $widths
     * @return list<float>
     */
    private function normalizeWidths(?array $widths, int $colCount): array
    {
        if ($widths === null || count($widths) !== $colCount) {
            $even = $this->innerWidth() / $colCount;

            return array_fill(0, $colCount, $even);
        }
        $sum = array_sum($widths);
        if ($sum <= 0) {
            return array_fill(0, $colCount, $this->innerWidth() / $colCount);
        }
        $scale = $this->innerWidth() / $sum;

        return array_map(fn (float $w) => $w * $scale, $widths);
    }

    private function innerWidth(): float
    {
        return $this->pageW - (2 * $this->margin);
    }

    private function strokeRect(float $x, float $y, float $w, float $h): void
    {
        $this->ops[] = sprintf('0.55 0.62 0.70 RG %.2f %.2f %.2f %.2f re S 0 0 0 RG', $x, $y, $w, $h);
    }

    private function fillRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void
    {
        $this->ops[] = sprintf('%.2f %.2f %.2f rg %.2f %.2f %.2f %.2f re f 0 0 0 rg', $r, $g, $b, $x, $y, $w, $h);
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < 48) {
            $this->flushPage();
            $this->startPage();
        }
    }

    private function flushPage(): void
    {
        $this->drawFooter();
        $this->pages[] = implode("\n", $this->ops);
        $this->ops = [];
    }

    private function textOp(float $x, float $y, int $size, bool $bold, string $text): string
    {
        $font = $bold ? '/F2' : '/F1';
        $safe = $this->escape($text);

        return sprintf('BT %s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $font, $size, $x, $y, $safe);
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $this->plain($text));
        if ($converted === false) {
            $converted = utf8_decode($this->plain($text));
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }

    private function plain(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return trim((string) $value);
    }

    private function clip(string $text, float $width, int $size): string
    {
        $max = max(4, (int) floor($width / max(3.2, $size * 0.48)));
        $text = $this->plain($text);
        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, $max - 1)).'...';
    }

    /** @return list<string> */
    private function wrap(string $text, float $width, int $size): array
    {
        $max = max(8, (int) floor($width / max(3.2, $size * 0.48)));
        $words = preg_split('/\s+/', $this->plain($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $attempt = $current === '' ? $word : $current.' '.$word;
            if (strlen($attempt) > $max) {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = strlen($word) > $max ? substr($word, 0, $max) : $word;
            } else {
                $current = $attempt;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? ['—'] : $lines;
    }
}
