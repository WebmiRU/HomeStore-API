<?php

namespace App\Services\Pdf;

use Com\Tecnick\Pdf\Tcpdf;

/**
 * Сервис генерации PDF с этикетками, содержащими DataMatrix-код и адаптивный текст.
 *
 * На вход принимает массив этикеток [{code, title}] и параметры листа/ячейки,
 * возвращает готовые байты PDF-документа (tc-lib-pdf).
 */
class LabelPdfService
{
    /** @var array<string, mixed> Настройки по умолчанию */
    private array $defaults = [
        // Размеры листа (мм)
        'page_width' => 210.0, // A4
        'page_height' => 297.0, // A4

        // Поля листа (мм)
        'page_margin_top' => 10.0,
        'page_margin_right' => 10.0,
        'page_margin_bottom' => 10.0,
        'page_margin_left' => 10.0,

        // Размеры одной ячейки (мм)
        'cell_width' => 78.0,
        'cell_height' => 23.0,

        // Поля внутри ячейки (мм)
        'cell_pad_top' => 3.0,
        'cell_pad_right' => 5.0,
        'cell_pad_bottom' => 5.0,
        'cell_pad_left' => 5.0,

        // Позиция DataMatrix-кода: 'left' или 'right'
        'barcode_position' => 'left',

        // Зазор между кодом и текстом (мм)
        'barcode_text_gap' => 2.0,

        // Размер DataMatrix-кода (мм, квадрат)
        'barcode_size' => 13.0,

        // Параметры шрифта
        'font_family' => 'robotocondensedb',
        'font_size_min' => 5.0,
        'font_size_max' => 24.0,
        'font_size_step' => 0.5,

        // Множитель межстрочного интервала (от высоты шрифта)
        'line_height_factor' => 1.25,
    ];

    /**
     * Генерирует PDF с этикетками и возвращает готовые байты документа.
     *
     * @param  array<int, array{code: string, title: string}>  $labels
     *                                                                  Массив этикеток, каждая с ключами:
     *                                                                  - 'code'  — строка для DataMatrix-кода
     *                                                                  - 'title' — текст заголовка (адаптивно вписывается)
     * @param  array<string, mixed>  $options  Параметры, переопределяющие defaults
     * @return string Байты PDF
     */
    public function generate(array $labels, array $options = []): string
    {
        $cfg = array_merge($this->defaults, $options);

        $pdf = $this->createPdf($cfg);
        $this->addLabelPage($pdf, $cfg);

        $this->drawLabels($pdf, $labels, $cfg);

        return $pdf->getOutPDFString();
    }

    /**
     * Создаёт и настраивает экземпляр tc-lib-pdf.
     *
     * @param  array<string, mixed>  $cfg
     */
    private function createPdf(array $cfg): Tcpdf
    {
        $pdf = new Tcpdf('mm', true, true, true, '');

        // Отключаем фоновое содержимое страниц (номер в футере и т.п.)
        $pdf->enableDefaultPageContent(false);

        return $pdf;
    }

    /**
     * Добавляет страницу заданного формата с полями.
     * Автоматический перенос страниц отключён — страницы расставляем сами сеткой.
     *
     * @param  array<string, mixed>  $cfg
     */
    private function addLabelPage(Tcpdf $pdf, array $cfg): void
    {
        $top = (float) $cfg['page_margin_top'];
        $bottom = (float) $cfg['page_margin_bottom'];

        $pdf->addPage([
            'width' => (float) $cfg['page_width'],
            'height' => (float) $cfg['page_height'],
            'orientation' => 'P',
            'autobreak' => false,
            'margin' => [
                'PL' => (float) $cfg['page_margin_left'],
                'PR' => (float) $cfg['page_margin_right'],
                'PT' => $top,
                'HB' => $top,
                'CT' => $top,
                'CB' => $bottom,
                'FT' => $bottom,
                'PB' => $bottom,
            ],
        ]);
    }

    /**
     * Рисует все этикетки сеткой на странице.
     *
     * @param  array<int, array{code: string, title: string}>  $labels
     * @param  array<string, mixed>  $cfg
     */
    private function drawLabels(Tcpdf $pdf, array $labels, array $cfg): void
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
                $this->addLabelPage($pdf, $cfg);
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
     * @param  float  $x  X-координата левого верхнего угла ячейки (мм)
     * @param  float  $y  Y-координата левого верхнего угла ячейки (мм)
     * @param  string  $code  Данные для DataMatrix
     * @param  string  $title  Текст заголовка
     * @param  array<string, mixed>  $cfg
     */
    private function drawCell(Tcpdf $pdf, float $x, float $y, string $code, string $title, array $cfg): void
    {
        // Рамка ячейки (тонкая, 0.2 мм)
        $pdf->page->addContent($pdf->graph->getBasicRect(
            $x,
            $y,
            (float) $cfg['cell_width'],
            (float) $cfg['cell_height'],
            'D',
            ['lineWidth' => 0.2, 'lineColor' => 'black'],
        ));

        $barcodeSize = (float) $cfg['barcode_size'];
        $gap = (float) $cfg['barcode_text_gap'];

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
        $pdf->page->addContent($pdf->getBarcode(
            'DATAMATRIX',
            $code,
            $barcodeX,
            $barcodeY,
            (int) round($barcodeSize),
            (int) round($barcodeSize),
            [0, 0, 0, 0],
            ['lineWidth' => 0.0, 'lineColor' => 'black', 'fillColor' => 'black'],
        ));

        // --- Адаптивный текст ---
        $textY = $y + $cfg['cell_pad_top'];
        $textW = $this->textAreaWidth($cfg);
        $textH = $cfg['cell_height'] - $cfg['cell_pad_top'] - $cfg['cell_pad_bottom'];

        $this->drawAdaptiveText($pdf, $textX, $textY, $textW, $textH, $title, $cfg);
    }

    /**
     * Ширина текстовой области внутри ячейки (мм).
     *
     * @param  array<string, mixed>  $cfg
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
     * @param  float  $x  X левого верхнего угла текстовой области (мм)
     * @param  float  $y  Y левого верхнего угла текстовой области (мм)
     * @param  float  $w  Ширина текстовой области (мм)
     * @param  float  $h  Высота текстовой области (мм)
     * @param  string  $text  Текст
     * @param  array<string, mixed>  $cfg
     */
    private function drawAdaptiveText(
        Tcpdf $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        array $cfg
    ): void {
        $fontFamily = $cfg['font_family'];
        $sizeMin = (float) $cfg['font_size_min'];
        $sizeMax = (float) $cfg['font_size_max'];
        $sizeStep = (float) $cfg['font_size_step'];
        $lineHFactor = (float) $cfg['line_height_factor'];

        for ($size = $sizeMax; $size >= $sizeMin; $size -= $sizeStep) {
            $metric = $this->insertFont($pdf, $fontFamily, $size);

            $lineHeight = $this->lineHeight($pdf, $size, $lineHFactor);
            $wrapResult = $this->wrapText($pdf, $text, $w);
            $textHeight = $wrapResult['lines'] * $lineHeight;

            // 0.5mm safety margin — текст рендерится чуть выше lines * lineHeight
            if ($textHeight <= $h - 0.5) {
                $offsetY = ($h - $textHeight) / 2;
                $this->drawText($pdf, $x, $y + $offsetY, $w, $wrapResult['text'], $metric, $size, $lineHeight, $lineHFactor);

                return;
            }

            // Не подошёл кегль — снимаем шрифт со стека и пробуем меньше
            $this->popFont($pdf);
        }

        // Минимальный кегль — рисуем как есть (обрежется снизу)
        $size = $sizeMin;
        $metric = $this->insertFont($pdf, $fontFamily, $size);
        $lineHeight = $this->lineHeight($pdf, $size, $lineHFactor);
        $wrapResult = $this->wrapText($pdf, $text, $w);
        $textHeight = $wrapResult['lines'] * $lineHeight;
        $offsetY = ($h - $textHeight) / 2;
        $this->drawText($pdf, $x, $y + max(0, $offsetY), $w, $wrapResult['text'], $metric, $size, $lineHeight, $lineHFactor);
    }

    /**
     * Вставляет шрифт текущего кегля в стек и возвращает его метрики.
     * После вызова измерения ширины работают на этом шрифте.
     *
     * @return array<string, mixed>
     */
    private function insertFont(Tcpdf $pdf, string $family, float $sizePt): array
    {
        return $pdf->font->insert($pdf->pon, $family, '', $sizePt);
    }

    /**
     * Убирает верхний шрифт со стека после измерения.
     */
    private function popFont(Tcpdf $pdf): void
    {
        $pdf->font->popLastFont();
    }

    /**
     * Высота строки текста (мм) для заданного кегля и коэффициента межстрочного интервала.
     */
    private function lineHeight(Tcpdf $pdf, float $sizePt, float $lineHFactor): float
    {
        return $pdf->toUnit($sizePt) * $lineHFactor;
    }

    /**
     * Рисует текст внутри прямоугольной области (эквивалент MultiCell
     * с border=0, align='C', fill=false, top-выравниванием).
     *
     * @param  array<string, mixed>  $metric
     */
    private function drawText(
        Tcpdf $pdf,
        float $x,
        float $y,
        float $w,
        string $text,
        array $metric,
        float $sizePt,
        float $lineHeight,
        float $lineHFactor
    ): void {
        // Переводим фокус страницы на выбранный шрифт
        $pdf->page->addContent($metric['out']);

        // Межстрочный интервал: общая высота строки ($lineHeight) минус
        // собственная высота глифов шрифта.
        $fontHeightMm = $pdf->toUnit($metric['height']);
        $linespace = $lineHeight - $fontHeightMm;

        // Как в TCPDF-совместимом модуле: верхняя половина межстрочного
        // интервала добавляется как верхний паддинг ячейки.
        $cell = Tcpdf::ZEROCELL;
        $cell['padding']['T'] = $pdf->toPoints($linespace / 2.0);

        $pdf->addTextCellXY(
            $text,
            -1,
            $x,
            $y,
            $w,
            $lineHeight,
            0,
            $linespace,
            'T',
            'C',
            $cell,
            [],
            0,
            0,
            0,
            0,
            true,
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            '',
            null,
            '',
        );
    }

    /**
     * Оборачивает текст сбалансированно: строки примерно одинаковой ширины.
     *
     * В отличие от жадного алгоритма (первая строка под завязку,
     * последняя — короткая), этот метод распределяет слова так,
     * чтобы ширина строк была выровнена.
     *
     * @param  string  $text  Исходный текст (одна строка, без \n)
     * @param  float  $maxWidthMm  Максимальная ширина строки (мм)
     * @return array{text: string, lines: int} Текст с явными \n и количество строк
     */
    private function wrapText(Tcpdf $pdf, string $text, float $maxWidthMm): array
    {
        // 10% запас компенсирует расхождение GetStringWidth и MultiCell
        $effectiveWidth = $maxWidthMm / 1.10;

        // Разбиваем на слова
        $words = preg_split('/\s+/u', $text);
        if ($words === false || count($words) <= 1) {
            return ['text' => $text, 'lines' => 1];
        }

        // Если весь текст на одной строке — возвращаем как есть
        if ($this->stringWidthMm($pdf, $text) <= $effectiveWidth) {
            return ['text' => $text, 'lines' => 1];
        }

        // Измеряем ширину каждого слова
        $wordWidths = array_map(fn ($w) => $this->stringWidthMm($pdf, $w), $words);
        $spaceWidth = $this->stringWidthMm($pdf, ' ');

        // Определяем минимальное количество строк (жадный подсчёт)
        $minLines = $this->countGreedyLines($wordWidths, $spaceWidth, $effectiveWidth);

        // Балансируем перенос
        $balanced = $this->balanceLines($words, $wordWidths, $spaceWidth, $effectiveWidth, $minLines);

        return [
            'text' => implode("\n", $balanced),
            'lines' => count($balanced),
        ];
    }

    /**
     * Ширина строки (мм) на текущем шрифте в стеке.
     */
    private function stringWidthMm(Tcpdf $pdf, string $str): float
    {
        return $pdf->toUnit($pdf->font->getOrdArrWidth($pdf->uniconv->strToOrdArr($str)));
    }

    /**
     * Жадный подсчёт строк (без балансировки).
     *
     * @param  float[]  $wordWidths
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
     * @param  string[]  $words
     * @param  float[]  $wordWidths
     * @param  int  $targetLines  Минимально необходимое количество строк
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
