<?php

namespace App\Services\Pdf;

use TCPDF;

/**
 * Сервис генерации PDF с этикетками, содержащими DataMatrix-код и адаптивный текст.
 *
 * На вход принимает массив этикеток [{code, title}] и параметры листа/ячейки,
 * возвращает готовый TCPDF-документ для скачивания или сохранения.
 */
class LabelPdfService
{
    /** @var array<string, mixed> Настройки по умолчанию */
    private array $defaults = [
        // Размеры листа (мм)
        'page_width'  => 210.0, // A4
        'page_height' => 297.0, // A4

        // Поля листа (мм)
        'page_margin_top'    => 10.0,
        'page_margin_right'  => 10.0,
        'page_margin_bottom' => 10.0,
        'page_margin_left'   => 10.0,

        // Размеры одной ячейки (мм)
        'cell_width'  => 78.0,
        'cell_height' => 23.0,

        // Поля внутри ячейки (мм)
        'cell_pad_top'    => 3.0,
        'cell_pad_right'  => 5.0,
        'cell_pad_bottom' => 5.0,
        'cell_pad_left'   => 5.0,

        // Позиция DataMatrix-кода: 'left' или 'right'
        'barcode_position' => 'left',

        // Зазор между кодом и текстом (мм)
        'barcode_text_gap' => 2.0,

        // Размер DataMatrix-кода (мм, квадрат)
        'barcode_size' => 13.0,

        // Параметры шрифта
        'font_family'  => 'robotocondensedb',
        'font_size_min' => 5.0,
        'font_size_max' => 24.0,
        'font_size_step' => 0.5,

        // Множитель межстрочного интервала (от высоты шрифта)
        'line_height_factor' => 1.25,
    ];

    /**
     * Генерирует PDF с этикетками.
     *
     * @param array<int, array{code: string, title: string}> $labels
     *        Массив этикеток, каждая с ключами:
     *        - 'code'  — строка для DataMatrix-кода
     *        - 'title' — текст заголовка (адаптивно вписывается)
     * @param array<string, mixed> $options Параметры, переопределяющие defaults
     * @return TCPDF
     */
    public function generate(array $labels, array $options = []): TCPDF
    {
        $cfg = array_merge($this->defaults, $options);

        $pdf = $this->createPdf($cfg);
        $pdf->AddPage();

        $this->drawLabels($pdf, $labels, $cfg);

        return $pdf;
    }

    /**
     * Создаёт и настраивает экземпляр TCPDF.
     *
     * @param array<string, mixed> $cfg
     * @return TCPDF
     */
    private function createPdf(array $cfg): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', [
            $cfg['page_width'],
            $cfg['page_height'],
        ], true, 'UTF-8');

        $pdf->SetMargins(
            $cfg['page_margin_left'],
            $cfg['page_margin_top'],
            $cfg['page_margin_right']
        );
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        return $pdf;
    }

    /**
     * Рисует все этикетки сеткой на странице.
     *
     * @param TCPDF $pdf
     * @param array<int, array{code: string, title: string}> $labels
     * @param array<string, mixed> $cfg
     */
    private function drawLabels(TCPDF $pdf, array $labels, array $cfg): void
    {
        $usableW = $cfg['page_width'] - $cfg['page_margin_left'] - $cfg['page_margin_right'];
        $cols = max(1, (int) floor($usableW / $cfg['cell_width']));

        $usableH = $cfg['page_height'] - $cfg['page_margin_top'] - $cfg['page_margin_bottom'];
        $rowsPerPage = max(1, (int) floor($usableH / $cfg['cell_height']));

        $startX = $cfg['page_margin_left'];
        $startY = $cfg['page_margin_top'];

        $pageRow = 0; // номер строки на текущей странице

        foreach ($labels as $index => $label) {
            // переход на новую страницу при заполнении строк
            if ($pageRow >= $rowsPerPage) {
                $pdf->AddPage();
                $pageRow = 0;
            }

            $col = $index % $cols;
            $x = $startX + $col * $cfg['cell_width'];
            $y = $startY + $pageRow * $cfg['cell_height'];

            $this->drawCell($pdf, $x, $y, $label['code'], $label['title'], $cfg);

            // увеличиваем счётчик строк только при переходе на новую строку
            if ($col === $cols - 1 || $index === count($labels) - 1) {
                $pageRow++;
            }
        }
    }

    /**
     * Рисует одну ячейку: DataMatrix + адаптивный текст.
     *
     * @param TCPDF $pdf
     * @param float $x       X-координата левого верхнего угла ячейки (мм)
     * @param float $y       Y-координата левого верхнего угла ячейки (мм)
     * @param string $code   Данные для DataMatrix
     * @param string $title  Текст заголовка
     * @param array<string, mixed> $cfg
     */
    private function drawCell(TCPDF $pdf, float $x, float $y, string $code, string $title, array $cfg): void
    {
        // Рамка ячейки (тонкая, 0.2 мм)
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $cfg['cell_width'], $cfg['cell_height'], 'D');

        $barcodeSize = $cfg['barcode_size'];
        $gap = $cfg['barcode_text_gap'];

        // Определяем позиции кода и текста
        if ($cfg['barcode_position'] === 'left') {
            $barcodeX = $x + $cfg['cell_pad_left'];
            $textX = $barcodeX + $barcodeSize + $gap;
        } else {
            $textX = $x + $cfg['cell_pad_left'];
            $barcodeX = $textX + $this->textAreaWidth($cfg) + $gap;
        }

        // DataMatrix центрируется по вертикали внутри полезной области ячейки
        $usableHeight = $cfg['cell_height'] - $cfg['cell_pad_top'] - $cfg['cell_pad_bottom'];
        $barcodeY = $y + $cfg['cell_pad_top'] + ($usableHeight - $barcodeSize) / 2;

        // --- DataMatrix ---
        $pdf->write2DBarcode(
            $code,
            'DATAMATRIX',
            $barcodeX,
            $barcodeY,
            $barcodeSize,
            $barcodeSize,
            ['border' => false, 'padding' => 0],
            ''
        );

        // --- Адаптивный текст ---
        $textY = $y + $cfg['cell_pad_top'];
        $textW = $this->textAreaWidth($cfg);
        $textH = $cfg['cell_height'] - $cfg['cell_pad_top'] - $cfg['cell_pad_bottom'];

        $this->drawAdaptiveText($pdf, $textX, $textY, $textW, $textH, $title, $cfg);
    }

    /**
     * Ширина текстовой области внутри ячейки (мм).
     *
     * @param array<string, mixed> $cfg
     * @return float
     */
    private function textAreaWidth(array $cfg): float
    {
        return $cfg['cell_width']
            - $cfg['cell_pad_left']
            - $cfg['cell_pad_right']
            - $cfg['barcode_size']
            - $cfg['barcode_text_gap'];
    }

    /**
     * Рисует текст с адаптивным подбором кегля.
     * Начинает с font_size_max, уменьшает с шагом font_size_step,
     * пока текст не поместится в заданную область.
     *
     * Использует сбалансированный перенос строк (wrapText),
     * чтобы строки были примерно одинаковой ширины.
     *
     * @param TCPDF $pdf
     * @param float $x      X левого верхнего угла текстовой области (мм)
     * @param float $y      Y левого верхнего угла текстовой области (мм)
     * @param float $w      Ширина текстовой области (мм)
     * @param float $h      Высота текстовой области (мм)
     * @param string $text  Текст
     * @param array<string, mixed> $cfg
     */
    private function drawAdaptiveText(
        TCPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        array $cfg
    ): void {
        $fontFamily = $cfg['font_family'];
        $sizeMin  = (float) $cfg['font_size_min'];
        $sizeMax  = (float) $cfg['font_size_max'];
        $sizeStep = (float) $cfg['font_size_step'];
        $lineHFactor = (float) $cfg['line_height_factor'];

        // Синхронизируем cellheightratio, чтобы getCellHeight()
        // и MultiCell() использовали одинаковый межстрочный интервал.
        $pdf->setCellHeightRatio($lineHFactor);

        for ($size = $sizeMax; $size >= $sizeMin; $size -= $sizeStep) {
            $pdf->SetFont($fontFamily, '', $size);

            $lineHeight = $pdf->getCellHeight($pdf->getFontSize(), false);
            $wrapResult = $this->wrapText($pdf, $text, $w);
            $textHeight = $wrapResult['lines'] * $lineHeight;

            if ($textHeight <= $h) {
                $offsetY = ($h - $textHeight) / 2;
                $pdf->SetXY($x, $y + $offsetY);
                $pdf->MultiCell($w, $lineHeight, $wrapResult['text'], 0, 'C', false, 0);
                return;
            }
        }

        // Минимальный кегль — рисуем как есть (обрежется снизу)
        $pdf->SetFont($fontFamily, '', $sizeMin);
        $lineHeight = $pdf->getCellHeight($pdf->getFontSize(), false);
        $wrapResult = $this->wrapText($pdf, $text, $w);
        $offsetY = ($h - $wrapResult['lines'] * $lineHeight) / 2;
        $pdf->SetXY($x, $y + max(0, $offsetY));
        $pdf->MultiCell($w, $lineHeight, $wrapResult['text'], 0, 'C', false, 0);
    }

    /**
     * Оборачивает текст сбалансированно: строки примерно одинаковой ширины.
     *
     * В отличие от жадного алгоритма (первая строка под завязку,
     * последняя — короткая), этот метод распределяет слова так,
     * чтобы ширина строк была выровнена.
     *
     * @param TCPDF $pdf
     * @param string $text  Исходный текст (одна строка, без \n)
     * @param float  $maxWidthMm Максимальная ширина строки (мм)
     * @return array{text: string, lines: int} Текст с явными \n и количество строк
     */
    private function wrapText(TCPDF $pdf, string $text, float $maxWidthMm): array
    {
        // 25% запас компенсирует расхождение GetStringWidth и MultiCell
        $effectiveWidth = $maxWidthMm / 1.25;

        // Разбиваем на слова
        $words = preg_split('/\s+/u', $text);
        if ($words === false || count($words) <= 1) {
            return ['text' => $text, 'lines' => 1];
        }

        // Если весь текст на одной строке — возвращаем как есть
        if ($pdf->GetStringWidth($text) <= $effectiveWidth) {
            return ['text' => $text, 'lines' => 1];
        }

        // Измеряем ширину каждого слова
        $wordWidths = array_map(fn($w) => $pdf->GetStringWidth($w), $words);
        $spaceWidth = $pdf->GetStringWidth(' ');

        // Определяем минимальное количество строк (жадный подсчёт)
        $minLines = $this->countGreedyLines($wordWidths, $spaceWidth, $effectiveWidth);

        // Балансируем перенос
        $balanced = $this->balanceLines($words, $wordWidths, $spaceWidth, $effectiveWidth, $minLines);

        return [
            'text'  => implode("\n", $balanced),
            'lines' => count($balanced),
        ];
    }

    /**
     * Жадный подсчёт строк (без балансировки).
     *
     * @param float[] $wordWidths
     * @param float $spaceWidth
     * @param float $maxWidth
     * @return int
     */
    private function countGreedyLines(array $wordWidths, float $spaceWidth, float $maxWidth): int
    {
        $lines = 1;
        $lineWidth = 0.0;

        foreach ($wordWidths as $ww) {
            $needWidth = $lineWidth > 0 ? $lineWidth + $spaceWidth + $ww : $ww;
            if ($needWidth > $maxWidth) {
                $lines++;
                $lineWidth = $ww;
            } else {
                $lineWidth = $needWidth;
            }
        }

        return $lines;
    }

    /**
     * Балансирует перенос строк: для 2 строк — оптимальный сплит,
     * для 3+ — жадный с выравниванием на целевое среднее.
     *
     * @param string[] $words
     * @param float[]  $wordWidths
     * @param float    $spaceWidth
     * @param float    $maxWidth
     * @param int      $targetLines Минимально необходимое количество строк
     * @return string[] Массив строк (без \n)
     */
    private function balanceLines(
        array $words,
        array $wordWidths,
        float $spaceWidth,
        float $maxWidth,
        int $targetLines
    ): array {
        $n = count($words);

        // 2 строки: ищем оптимальную точку разрыва
        if ($targetLines === 2) {
            // Вычисляем префиксные ширины
            $prefix = [0];
            foreach ($wordWidths as $ww) {
                $prev = end($prefix);
                $prefix[] = $prev > 0 ? $prev + $spaceWidth + $ww : $ww;
            }

            $totalWidth = $prefix[$n];

            $bestSplit = 1;
            $bestDiff = PHP_FLOAT_MAX;

            for ($i = 1; $i < $n; $i++) {
                $line1 = $prefix[$i];
                // line2 = total - line1 - spaceWidth (убираем пробел-разделитель между строками)
                // Но при рендеринге пробела между строками нет, так что просто:
                $line2 = $totalWidth - ($prefix[$i] + $spaceWidth) + $wordWidths[$i];
                // Упрощённо: оцениваем ширину второй строки как сумму оставшихся слов
                $remainingWidth = 0;
                for ($j = $i; $j < $n; $j++) {
                    $remainingWidth += ($remainingWidth > 0 ? $spaceWidth : 0) + $wordWidths[$j];
                }

                if ($line1 <= $maxWidth && $remainingWidth <= $maxWidth) {
                    $diff = abs($line1 - $remainingWidth);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestSplit = $i;
                    }
                }
            }

            $line1 = array_slice($words, 0, $bestSplit);
            $line2 = array_slice($words, $bestSplit);
            return [implode(' ', $line1), implode(' ', $line2)];
        }

        // 3+ строк: жадный с целевой шириной = среднее
        $totalWidth = 0;
        foreach ($wordWidths as $ww) {
            $totalWidth += ($totalWidth > 0 ? $spaceWidth : 0) + $ww;
        }
        $targetWidth = $totalWidth / $targetLines;

        $lines = [];
        $lineWords = [];
        $lineWidth = 0.0;
        $remaining = $n;

        for ($i = 0; $i < $n; $i++) {
            $remaining--;
            $needWidth = $lineWidth > 0 ? $lineWidth + $spaceWidth + $wordWidths[$i] : $wordWidths[$i];

            // Форсируем перенос если не влезает
            if ($needWidth > $maxWidth && count($lineWords) > 0) {
                $lines[] = implode(' ', $lineWords);
                $lineWords = [$words[$i]];
                $lineWidth = $wordWidths[$i];
                continue;
            }

            // Можем ли разорвать здесь для баланса?
            $linesSoFar = count($lines);
            $canBreak = $linesSoFar < $targetLines - 1 && $remaining >= ($targetLines - $linesSoFar - 1);

            if ($canBreak && count($lineWords) > 0) {
                $deviationStay = abs($needWidth - $targetWidth);
                $deviationBreak = abs($lineWidth - $targetWidth);

                if ($deviationBreak <= $deviationStay) {
                    // Текущая строка ближе к цели — переносим
                    $lines[] = implode(' ', $lineWords);
                    $lineWords = [$words[$i]];
                    $lineWidth = $wordWidths[$i];
                    continue;
                }
            }

            $lineWords[] = $words[$i];
            $lineWidth = $needWidth;
        }

        if (count($lineWords) > 0) {
            $lines[] = implode(' ', $lineWords);
        }

        return $lines;
    }

}
