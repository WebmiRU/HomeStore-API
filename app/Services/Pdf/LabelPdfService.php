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
     * Измерение высоты — measureTextHeight() с пословным переносом
     * и 25% запасом по ширине (GetStringWidth может занижать
     * реальную ширину глифов относительно MultiCell).
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
            $textHeight = $this->measureTextHeight($pdf, $text, $w, $lineHeight);

            if ($textHeight <= $h) {
                $offsetY = ($h - $textHeight) / 2;
                $pdf->SetXY($x, $y + $offsetY);
                $pdf->MultiCell($w, $lineHeight, $text, 0, 'C', false, 0);
                return;
            }
        }

        // Минимальный кегль — рисуем как есть (обрежется снизу)
        $pdf->SetFont($fontFamily, '', $sizeMin);
        $lineHeight = $pdf->getCellHeight($pdf->getFontSize(), false);
        $offsetY = ($h - $this->measureTextHeight($pdf, $text, $w, $lineHeight)) / 2;
        $pdf->SetXY($x, $y + max(0, $offsetY));
        $pdf->MultiCell($w, $lineHeight, $text, 0, 'C', false, 0);
    }

    /**
     * Измеряет высоту текста: пословный перенос + запас 25% по ширине.
     *
     * GetStringWidth() может расходиться с реальной шириной глифов
     * в MultiCell() (особенно на condensed-шрифтах с кириллицей),
     * поэтому эффективная ширина строки берётся с запасом.
     *
     * @param TCPDF $pdf
     * @param string $text
     * @param float  $maxWidthMm  Максимальная ширина строки (мм)
     * @param float  $lineHeightMm Высота одной строки (мм)
     * @return float Высота текста (мм)
     */
    private function measureTextHeight(
        TCPDF $pdf,
        string $text,
        float $maxWidthMm,
        float $lineHeightMm
    ): float {
        // 25% запас компенсирует расхождение GetStringWidth и MultiCell
        $effectiveWidth = $maxWidthMm / 1.25;

        $lines = 1;
        $currentLine = '';

        $segments = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            $segments = [$text];
        }

        foreach ($segments as $segment) {
            $subParts = explode("\n", $segment);
            foreach ($subParts as $i => $part) {
                if ($i > 0) {
                    $lines++;
                    $currentLine = '';
                }
                if ($part === '') {
                    continue;
                }

                $testLine = $currentLine . $part;
                if ($pdf->GetStringWidth($testLine) > $effectiveWidth) {
                    $lines++;
                    $currentLine = $part;
                } else {
                    $currentLine = $testLine;
                }
            }
        }

        return $lines * $lineHeightMm;
    }

}
