<?php

declare(strict_types=1);

namespace ModulaPdfService;

use Com\Tecnick\Pdf\Tcpdf;

final class PdfRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, string $fontFamily): string
    {
        $kind = strtolower((string) ($payload['kind'] ?? 'invoice'));
        if ($kind === 'document') {
            return $this->renderDocument($payload, $fontFamily);
        }

        return $this->renderInvoice($payload, $fontFamily);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function renderInvoice(array $payload, string $fontFamily): string
    {
        $pdf = new Tcpdf(
            unit: 'mm',
            isunicode: true,
            subsetfont: true,
            compress: true,
            mode: '',
            objEncrypt: null,
        );

        $pdf->setCreator('modula-pdf-service');
        $pdf->setAuthor((string) ($payload['seller']['name'] ?? 'Modula CMS'));
        $pdf->setSubject((string) ($payload['subject'] ?? 'Facture'));
        $pdf->setTitle((string) ($payload['title'] ?? 'Facture'));
        $pdf->setKeywords('modula pdf tc-lib-pdf invoice');
        $pdf->setPDFFilename((string) ($payload['filename'] ?? 'invoice.pdf'));
        $pdf->setViewerPreferences(['DisplayDocTitle' => true]);

        $font = $pdf->font->insert($pdf->pon, $fontFamily, '', 10);
        $pdf->addPage(['format' => 'A4']);
        $pdf->page->addContent($font['out']);
        $pdf->addHTMLCell(html: $this->buildInvoiceHtml($payload, $fontFamily), posx: 10, posy: 10, width: 190);
        $this->renderFixedFooter($pdf, $payload, $fontFamily);

        return $pdf->getOutPDFString();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function renderDocument(array $payload, string $fontFamily): string
    {
        $pdf = new Tcpdf(
            unit: 'mm',
            isunicode: true,
            subsetfont: true,
            compress: true,
            mode: '',
            objEncrypt: null,
        );

        $pdf->setCreator('modula-pdf-service');
        $pdf->setAuthor((string) ($payload['seller']['title'] ?? 'Modula CMS'));
        $pdf->setSubject((string) ($payload['subject'] ?? 'Document'));
        $pdf->setTitle((string) ($payload['title'] ?? 'Document'));
        $pdf->setKeywords('modula pdf tc-lib-pdf document');
        $pdf->setPDFFilename((string) ($payload['filename'] ?? 'document.pdf'));
        $pdf->setViewerPreferences(['DisplayDocTitle' => true]);

        $font = $pdf->font->insert($pdf->pon, $fontFamily, '', 10);
        $pdf->addPage(['format' => 'A4']);
        $pdf->page->addContent($font['out']);
        $pdf->addHTMLCell(html: $this->buildDocumentHtml($payload, $fontFamily), posx: 10, posy: 10, width: 190);
        $this->renderFixedFooter($pdf, $payload, $fontFamily);

        return $pdf->getOutPDFString();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildInvoiceHtml(array $payload, string $fontFamily): string
    {
        $seller = is_array($payload['seller'] ?? null) ? $payload['seller'] : [];
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $logoDataUri = trim((string) ($payload['logoDataUri'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $taxRows = is_array($totals['taxRows'] ?? null) ? $totals['taxRows'] : [];
        $labels = is_array($payload['labels'] ?? null) ? $payload['labels'] : [];
        $sellerTitle = trim((string) ($seller['title'] ?? '')) ?: 'Issuer';
        $customerTitle = trim((string) ($customer['title'] ?? '')) ?: 'Customer';
        $notesTitle = trim((string) ($labels['notesTitle'] ?? '')) ?: 'Notes';
        $noNotesLabel = trim((string) ($labels['noNotes'] ?? '')) ?: 'No notes';
        $totalsTitle = trim((string) ($labels['totalsTitle'] ?? '')) ?: 'Totals';
        $totalHtLabel = trim((string) ($labels['totalHt'] ?? '')) ?: 'Total excl. VAT';
        $totalVatLabel = trim((string) ($labels['totalVat'] ?? '')) ?: 'Total VAT';
        $totalTtcLabel = trim((string) ($labels['totalTtc'] ?? '')) ?: 'Total incl. VAT';
        $emptyLinesLabel = trim((string) ($labels['emptyLines'] ?? '')) ?: 'No lines';

        $sellerLines = array_values(array_filter([
            $seller['name'] ?? null,
            $seller['email'] ?? null,
            $seller['address'] ?? null,
            $seller['city'] ?? null,
        ], static fn ($value): bool => is_string($value) && trim($value) !== ''));

        $customerLines = array_values(array_filter([
            $customer['name'] ?? null,
            $customer['email'] ?? null,
            $customer['phone'] ?? null,
            $customer['address'] ?? null,
        ], static fn ($value): bool => is_string($value) && trim($value) !== ''));

        if ($columns === []) {
            $columns = [
                ['key' => 'lineNumber', 'label' => 'N°'],
                ['key' => 'designation', 'label' => 'Désignation'],
                ['key' => 'reference', 'label' => 'Réf.'],
                ['key' => 'quantity', 'label' => 'Qté'],
                ['key' => 'unitPriceHt', 'label' => 'PU HT'],
                ['key' => 'totalHt', 'label' => 'Total HT'],
                ['key' => 'vatRate', 'label' => 'TVA'],
                ['key' => 'vatAmount', 'label' => 'TVA Montant'],
                ['key' => 'totalTtc', 'label' => 'Total TTC'],
            ];
        }

        $headerCells = '';
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = (string) ($column['key'] ?? '');
            $label = (string) ($column['label'] ?? $key);
            $headerCells .= '<th class="' . $this->escapeAttribute($this->invoiceColumnClass($key, true)) . '" style="' . $this->escapeAttribute($this->invoiceColumnStyle($key, true)) . '">'
                . $this->escape($label)
                . '</th>';
        }

        $rows = '';
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $values = is_array($item['values'] ?? null) ? $item['values'] : [];
            $rows .= '<tr>';
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $key = (string) ($column['key'] ?? '');
                if ($key === 'designation') {
                    $rows .= '<td class="' . $this->escapeAttribute($this->invoiceColumnClass($key, false)) . '" style="' . $this->escapeAttribute($this->invoiceColumnStyle($key, false)) . '">'
                        . '<div class="line-title">' . $this->escape((string) (($values[$key] ?? null) ?: ($item['name'] ?? ('Article ' . ($index + 1))))) . '</div>'
                        . (!empty($item['description']) ? '<div class="line-description">' . $this->escape((string) $item['description']) . '</div>' : '')
                        . '</td>';
                    continue;
                }

                $value = (string) (($values[$key] ?? null) ?: '-');
                $rows .= '<td class="' . $this->escapeAttribute($this->invoiceColumnClass($key, false)) . '" style="' . $this->escapeAttribute($this->invoiceColumnStyle($key, false)) . '">'
                    . $this->escape($value)
                    . '</td>';
            }
            $rows .= '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td class="cell center" colspan="' . max(1, count($columns)) . '">' . $this->escape($emptyLinesLabel) . '</td></tr>';
        }

        $taxRowsHtml = '';
        foreach ($taxRows as $taxRow) {
            if (!is_array($taxRow)) {
                continue;
            }

            $taxRowsHtml .= '<tr><td class="total-label">' . $this->escape((string) ($taxRow['label'] ?? 'TVA')) . '</td><td class="total-value">' . $this->escape((string) ($taxRow['amountLabel'] ?? '')) . '</td></tr>';
        }

        return '
<style>
  * { font-family: ' . $this->escape($fontFamily) . '; color: #18212f; box-sizing: border-box; }
  .page { font-size: 10pt; line-height: 1.45; padding-bottom: 22mm; }
  .page-body { padding-bottom: 34px; }
  .topbar { height: 4px; background: #4b56d2; margin-bottom: 14px; }
  .header-table { width: 100%; border-collapse: collapse; }
  .brand-block { width: 58%; vertical-align: top; }
  .meta-block { width: 42%; vertical-align: top; text-align: right; }
  .brand-row { width: 100%; border-collapse: collapse; }
  .logo-cell { width: 128px; vertical-align: top; }
  .copy-cell { vertical-align: top; }
  .logo { width: 124px; }
  .brand-name { font-size: 24pt; font-weight: bold; line-height: 1.05; margin: 0; }
  .document-title { color: #627086; font-size: 11pt; margin-top: 6px; }
  .meta-title { color: #4b56d2; font-size: 12pt; font-weight: bold; margin: 0 0 4px; }
  .meta-line { color: #627086; font-size: 9pt; margin: 0 0 2px; }
  .status-chip { display: inline-block; margin-top: 8px; padding: 4px 8px; background: #eef2ff; color: #4b56d2; font-size: 8pt; font-weight: bold; }
  .party-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 12px; }
  .party-card { width: 48%; vertical-align: top; border: 1px solid #d9deea; background: #f7f9fc; padding: 12px 14px; }
  .party-gap { width: 4%; }
  .party-title { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.08em; color: #627086; font-weight: bold; margin-bottom: 8px; }
  .party-line { margin: 0 0 3px; }
  .invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 12px; border: 1px solid #d9deea; }
  .invoice-table th { background: #f7f9fc; color: #627086; font-size: 7.2pt; font-weight: bold; padding: 7px 4px; border-bottom: 1px solid #d9deea; text-align: right; white-space: normal; word-break: break-word; line-height: 1.2; }
  .invoice-table .cell { padding: 7px 4px; border-bottom: 1px solid #d9deea; vertical-align: top; white-space: normal; word-break: break-word; line-height: 1.2; font-size: 9pt; }
  .invoice-table tr:last-child .cell { border-bottom: none; }
  .center { text-align: center; }
  .right { text-align: right; }
  .small { font-size: 8.5pt; color: #627086; }
  .col-lineNumber { text-align: center !important; padding-left: 1px !important; padding-right: 1px !important; }
  .col-designation { text-align: left !important; }
  .col-vatRate { color: #627086; font-size: 8.1pt; line-height: 1.15; }
  .line-title { font-size: 9.4pt; font-weight: bold; }
  .line-description { font-size: 8.5pt; color: #627086; margin-top: 4px; }
  .total-cell { font-weight: bold; }
  .summary-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .notes-cell { width: 55%; vertical-align: top; }
  .notes-title { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.08em; color: #627086; font-weight: bold; margin-bottom: 8px; }
  .totals-cell { width: 40%; vertical-align: top; }
  .summary-gap { width: 5%; }
  .totals-box { width: 100%; border: 1px solid #d9deea; background: #fff; }
  .totals-inner { width: 100%; border-collapse: collapse; }
  .totals-inner td { padding: 7px 12px; border-bottom: 1px solid #d9deea; }
  .totals-inner tr:last-child td { border-bottom: none; }
  .total-label { color: #18212f; }
  .total-value { text-align: right; font-weight: bold; }
  .grand-total td { color: #4b56d2; font-size: 11pt; font-weight: bold; }
</style>
<div class="page">
  <div class="page-body">
  <div class="topbar"></div>
  <table class="header-table" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td class="brand-block">
        <table class="brand-row" border="0" cellspacing="0" cellpadding="0">
          <tr>
            ' . ($logoDataUri !== '' ? '<td class="logo-cell"><img class="logo" src="' . $this->escapeAttribute($logoDataUri) . '" alt="Logo" /></td>' : '') . '
            <td class="copy-cell">
              <div class="brand-name">' . $this->escape((string) ($seller['name'] ?? 'Modula CMS')) . '</div>
              <div class="document-title">' . $this->escape((string) ($payload['documentTitle'] ?? 'Facture')) . '</div>
            </td>
          </tr>
        </table>
      </td>
      <td class="meta-block">
        <div class="meta-title">' . $this->escape((string) ($payload['documentNumber'] ?? 'FAC-TEST-001')) . '</div>
        <div class="meta-line">' . $this->escape((string) ($payload['issuedAt'] ?? date('d/m/Y'))) . '</div>
        ' . (!empty($payload['statusLabel']) ? '<div class="status-chip">' . $this->escape((string) $payload['statusLabel']) . '</div>' : '') . '
      </td>
    </tr>
  </table>

  <table class="party-table" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td class="party-card">
        <div class="party-title">' . $this->escape($sellerTitle) . '</div>
        ' . $this->renderParagraphLines($sellerLines, 'party-line') . '
      </td>
      <td class="party-gap"></td>
      <td class="party-card">
        <div class="party-title">' . $this->escape($customerTitle) . '</div>
        ' . $this->renderParagraphLines($customerLines, 'party-line') . '
      </td>
    </tr>
  </table>

  <table class="invoice-table" border="0" cellspacing="0" cellpadding="0">
    <thead>
      <tr>' . $headerCells . '</tr>
    </thead>
    <tbody>' . $rows . '</tbody>
  </table>

  <table class="summary-table" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td class="notes-cell">
        <div class="notes-title">' . $this->escape($notesTitle) . '</div>
        <div>' . $this->escape($notes !== '' ? $notes : $noNotesLabel) . '</div>
      </td>
      <td class="summary-gap"></td>
      <td class="totals-cell">
        <div class="totals-box">
          <div class="notes-title" style="padding:10px 12px 0;">' . $this->escape($totalsTitle) . '</div>
          <table class="totals-inner" border="0" cellspacing="0" cellpadding="0">
            <tr><td class="total-label">' . $this->escape($totalHtLabel) . '</td><td class="total-value">' . $this->escape((string) ($totals['subtotalHtLabel'] ?? '')) . '</td></tr>
            ' . $taxRowsHtml . '
            <tr><td class="total-label">' . $this->escape($totalVatLabel) . '</td><td class="total-value">' . $this->escape((string) ($totals['totalVatLabel'] ?? '')) . '</td></tr>
            <tr class="grand-total"><td>' . $this->escape($totalTtcLabel) . '</td><td class="total-value">' . $this->escape((string) ($totals['grandTotalLabel'] ?? '')) . '</td></tr>
          </table>
        </div>
      </td>
    </tr>
  </table>
  </div>
</div>';
    }

    private function invoiceColumnClass(string $key, bool $header): string
    {
        $base = $header ? '' : 'cell ';
        $align = $key === 'designation' ? 'left' : ($key === 'lineNumber' ? 'center small' : 'right');
        $weight = $key === 'totalTtc' && !$header ? ' total-cell' : '';

        return trim($base . 'col-' . $key . ' ' . $align . $weight);
    }

    private function invoiceColumnStyle(string $key, bool $header): string
    {
        $widthMap = [
            'lineNumber' => '4%',
            'designation' => '27%',
            'reference' => '7%',
            'quantity' => '5%',
            'unitPriceHt' => '11%',
            'totalHt' => '11%',
            'vatAmount' => '11%',
            'vatRate' => '12%',
            'totalTtc' => '12%',
        ];

        $styles = [];
        if (isset($widthMap[$key])) {
            $styles[] = 'width:' . $widthMap[$key];
        }

        if ($key === 'designation') {
            $styles[] = 'text-align:left';
        } elseif ($key === 'lineNumber') {
            $styles[] = 'text-align:center';
            $styles[] = 'padding-left:1px';
            $styles[] = 'padding-right:1px';
        } else {
            $styles[] = 'text-align:right';
        }

        if ($key === 'vatRate') {
            $styles[] = 'font-size:' . ($header ? '7pt' : '8pt');
            $styles[] = 'line-height:1.15';
        }

        if (!$header && $key === 'totalTtc') {
            $styles[] = 'font-weight:bold';
        }

        return implode(';', $styles);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildDocumentHtml(array $payload, string $fontFamily): string
    {
        $seller = is_array($payload['seller'] ?? null) ? $payload['seller'] : [];
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        $metaLines = is_array($payload['metaLines'] ?? null) ? $payload['metaLines'] : [];
        $logoDataUri = trim((string) ($payload['logoDataUri'] ?? ''));

        $sellerLines = array_values(array_filter((array) ($seller['lines'] ?? []), static fn ($value): bool => is_string($value) && trim($value) !== ''));
        $customerLines = array_values(array_filter((array) ($customer['lines'] ?? []), static fn ($value): bool => is_string($value) && trim($value) !== ''));

        $sectionsHtml = '';
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $title = trim((string) ($section['title'] ?? 'Contenu'));
            $lines = array_values(array_filter((array) ($section['lines'] ?? []), static fn ($value): bool => is_string($value) && trim($value) !== ''));
            if ($title === '' && $lines === []) {
                continue;
            }

            $sectionsHtml .= '
              <div style="border:1px solid #d9deea; background:#fff; margin-top:12px;">
                <div style="padding:10px 14px; background:#f7f9fc; border-bottom:1px solid #d9deea; font-weight:bold;">' . $this->escape($title !== '' ? $title : 'Contenu') . '</div>
                <div style="padding:14px;">' . $this->renderParagraphLines($lines, '') . '</div>
              </div>';
        }

        return '
<style>
  * { font-family: ' . $this->escape($fontFamily) . '; color: #18212f; box-sizing: border-box; }
  .page { font-size: 10pt; line-height: 1.45; padding-bottom: 22mm; }
  .topbar { height: 4px; background: #4b56d2; margin-bottom: 14px; }
  .header-table { width: 100%; border-collapse: collapse; }
  .brand-block { width: 58%; vertical-align: top; }
  .meta-block { width: 42%; vertical-align: top; text-align: right; }
  .brand-row { width: 100%; border-collapse: collapse; }
  .logo-cell { width: 128px; vertical-align: top; }
  .copy-cell { vertical-align: top; }
  .logo { max-width: 116px; max-height: 78px; object-fit: contain; object-position: left top; }
  .brand-name { font-size: 24pt; font-weight: bold; line-height: 1.05; margin: 0; }
  .document-title { color: #627086; font-size: 11pt; margin-top: 6px; }
  .meta-title { color: #4b56d2; font-size: 12pt; font-weight: bold; margin: 0 0 4px; }
  .meta-line { color: #627086; font-size: 9pt; margin: 0 0 2px; }
  .status-chip { display: inline-block; margin-top: 8px; padding: 4px 8px; background: #eef2ff; color: #4b56d2; font-size: 8pt; font-weight: bold; }
  .party-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 12px; }
  .party-card { width: 48%; vertical-align: top; border: 1px solid #d9deea; background: #f7f9fc; padding: 12px 14px; }
  .party-gap { width: 4%; }
</style>
<div class="page">
  <div class="topbar"></div>
  <table class="header-table" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td class="brand-block">
        <table class="brand-row" border="0" cellspacing="0" cellpadding="0">
          <tr>
            ' . ($logoDataUri !== '' ? '<td class="logo-cell"><img class="logo" src="' . $this->escapeAttribute($logoDataUri) . '" alt="Logo" /></td>' : '') . '
            <td class="copy-cell">
              <div class="brand-name">' . $this->escape((string) ($seller['title'] ?? 'Document')) . '</div>
              <div class="document-title">' . $this->escape((string) ($payload['documentTitle'] ?? 'Document')) . '</div>
            </td>
          </tr>
        </table>
      </td>
      <td class="meta-block">
        <div class="meta-title">' . $this->escape((string) ($payload['documentNumber'] ?? 'DOC-TEST-001')) . '</div>
        <div class="meta-line">' . $this->escape((string) ($payload['issuedAt'] ?? date('d/m/Y'))) . '</div>
        ' . (!empty($payload['statusLabel']) ? '<div class="status-chip">' . $this->escape((string) $payload['statusLabel']) . '</div>' : '') . '
      </td>
    </tr>
  </table>

  <table class="party-table" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td class="party-card">
        <div style="font-size:8pt; text-transform:uppercase; letter-spacing:0.08em; color:#627086; font-weight:bold; margin-bottom:8px;">' . $this->escape((string) ($seller['title'] ?? 'Émetteur')) . '</div>
        ' . $this->renderParagraphLines($sellerLines, '') . '
      </td>
      <td class="party-gap"></td>
      <td class="party-card">
        <div style="font-size:8pt; text-transform:uppercase; letter-spacing:0.08em; color:#627086; font-weight:bold; margin-bottom:8px;">' . $this->escape((string) ($customer['title'] ?? 'Client')) . '</div>
        ' . $this->renderParagraphLines($customerLines, '') . '
      </td>
    </tr>
  </table>

  ' . (!empty($metaLines) ? '<div style="border:1px solid #d9deea; background:#f7f9fc; margin-top:12px; padding:12px 14px;">' . $this->renderParagraphLines(array_map(static fn ($line): string => (string) $line, $metaLines), '') . '</div>' : '') . '
  ' . $sectionsHtml . '
</div>';
    }

    /**
     * @param string[] $lines
     */
    private function renderParagraphLines(array $lines, string $className): string
    {
        $html = '';
        foreach ($lines as $line) {
            $class = trim($className);
            $html .= '<div' . ($class !== '' ? ' class="' . $this->escapeAttribute($class) . '"' : '') . '>' . $this->escape($line) . '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderFixedFooter(Tcpdf $pdf, array $payload, string $fontFamily): void
    {
        $footer = trim((string) ($payload['footer'] ?? ''));
        $documentNumber = trim((string) ($payload['documentNumber'] ?? ''));
        $labels = is_array($payload['labels'] ?? null) ? $payload['labels'] : [];
        $pageLabel = trim((string) ($labels['page'] ?? '')) ?: 'Page';
        $pages = $pdf->page->getPages();
        $pageCount = count($pages);
        if ($pageCount <= 0) {
            return;
        }

        $normalFont = $pdf->font->insert($pdf->pon, $fontFamily, '', 8);
        $boldFont = $pdf->font->insert($pdf->pon, $fontFamily, 'B', 8);
        $footerCopy = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $footer)) ?: '';

        foreach ($pages as $pid => $page) {
            $pageWidth = (float) ($page['width'] ?? 210.0);
            $pageHeight = (float) ($page['height'] ?? 297.0);
            $margin = 10.0;
            $usableWidth = $pageWidth - ($margin * 2);
            $leftColumnWidth = $usableWidth * 0.3;
            $centerColumnWidth = $usableWidth * 0.4;
            $rightColumnWidth = $usableWidth - $leftColumnWidth - $centerColumnWidth;
            $centerColumnX = $margin + $leftColumnWidth;
            $lineY = $pageHeight - 15.0;
            $documentY = $lineY + 1.2;
            $pageY = $lineY + 4.9;
            $footerY = $lineY + 2.0;

            $lineStyle = [
                'lineWidth' => 0.2,
                'lineCap' => 'butt',
                'lineJoin' => 'miter',
                'dashArray' => [],
                'dashPhase' => 0,
                'lineColor' => '#d9deea',
            ];

            $out = $pdf->graph->getStartTransform();
            $out .= $normalFont['out'];
            $out .= $pdf->graph->getLine($margin, $lineY, $pageWidth - $margin, $lineY, $lineStyle);

            if ($documentNumber !== '') {
                $out .= $boldFont['out'];
                $out .= $pdf->color->getPdfColor('#18212f');
                $out .= $pdf->getTextCell(
                    txt: $documentNumber,
                    posx: $margin,
                    posy: $documentY,
                    width: $leftColumnWidth,
                    height: 3.6,
                    offset: 0,
                    linespace: 0,
                    valign: 'T',
                    halign: 'L',
                );
                $out .= $normalFont['out'];
            }

            $out .= $pdf->color->getPdfColor('#627086');
            $out .= $pdf->getTextCell(
                txt: $pageLabel . ' ' . ((int) $pid + 1) . '/' . $pageCount,
                posx: $margin,
                posy: $pageY,
                width: $leftColumnWidth,
                height: 3.6,
                offset: 0,
                linespace: 0,
                valign: 'T',
                halign: 'L',
            );

            if ($footerCopy !== '') {
                $out .= $pdf->getTextCell(
                    txt: $footerCopy,
                    posx: $centerColumnX,
                    posy: $footerY,
                    width: $centerColumnWidth,
                    height: 6.0,
                    offset: 0,
                    linespace: 0,
                    valign: 'T',
                    halign: 'C',
                );
            }

            $out .= $pdf->graph->getStopTransform();
            $pdf->page->addContent($out, (int) $pid);
        }
    }

    private function money(mixed $value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        return number_format($number, 2, ',', ' ') . ' EUR';
    }

    private function escape(string $value): string
    {
        $normalized = trim($value);
        $escaped = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8', false);
        return str_replace(["\r\n", "\r", "\n"], '<br/>', $escaped);
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8', false);
    }
}
