<?php
// ============================================================
// Export Helper — KARN HIGH SCHOOL
// Provides:
//   pdfReportPage(string $title, string $subtitle, string $bodyHtml): never
//     → outputs a full print-ready HTML page then exits
//
//   excelExport(string $filename, array $headers, array $rows): never
//     → outputs an Excel-compatible XML file then exits
//
//   csvExport(string $filename, array $headers, array $rows): never
//     → outputs a plain CSV file then exits
// ============================================================

if (!function_exists('pdfReportPage')) {

/**
 * Output a print-ready HTML page with school header, watermark and footer.
 * Caller should echo the body content inside this wrapper.
 * This function exits after output.
 *
 * @param string $title    Main report title
 * @param string $subtitle e.g. "Academic Year 2026/2027"
 * @param string $bodyHtml The table/content HTML
 */
function pdfReportPage(string $title, string $subtitle, string $bodyHtml): never
{
    $school  = setting('school_name',    'KARN HIGH SCHOOL');
    $address = setting('school_address', 'Karnplay City, Gbehlay-Geh District, Nimba County, Republic of Liberia');
    $phone   = setting('school_phone',   '+231 886 417 711');
    $email   = setting('school_email',   'info@karnhighschool.edu.lr');
    $logo    = BASE_URL.'/assets/images/logo.png';
    $date    = date('d F Y');
    $time    = date('H:i');

    // Inline all styles — no external CSS needed for print
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>'.htmlspecialchars($title).' — '.htmlspecialchars($school).'</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:9pt;color:#111;background:#fff;padding:0}

/* ── Watermark ── */
.wm{
  position:fixed;top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:55%;opacity:.045;
  pointer-events:none;z-index:0
}

/* ── Header ── */
.rpt-header{
  display:flex;align-items:center;
  justify-content:space-between;
  padding:12px 16px 10px;
  border-bottom:3px solid #1a2744;
  margin-bottom:0;
  page-break-inside:avoid;
}
.rpt-header-left{display:flex;align-items:center;gap:12px}
.rpt-logo{width:58px;height:58px;object-fit:contain}
.rpt-school-name{font-size:16pt;font-weight:900;color:#1a2744;line-height:1.1}
.rpt-school-sub{font-size:8pt;color:#555;line-height:1.5;margin-top:2px}
.rpt-header-right{text-align:right;font-size:8pt;color:#666}

/* ── Title block ── */
.rpt-title-block{
  background:#1a2744;color:#fff;
  padding:8px 16px 6px;
  margin-bottom:10px;
}
.rpt-title-block h1{font-size:13pt;font-weight:900;letter-spacing:.04em}
.rpt-title-block p{font-size:8.5pt;opacity:.8;margin-top:2px}

/* ── Content ── */
.rpt-body{padding:0 16px 16px;position:relative;z-index:1}

/* ── Tables ── */
table{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:8.5pt}
thead tr{background:#1a2744;color:#fff}
thead th{padding:6px 7px;text-align:left;font-weight:700;font-size:8pt;white-space:nowrap}
thead th.num{text-align:right}
tbody tr:nth-child(even){background:#f5f7fa}
tbody tr:hover{background:#eef2ff}
td{padding:5px 7px;border-bottom:1px solid #e0e4ec;vertical-align:top}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tfoot tr{background:#e8eaf0;font-weight:700}
tfoot td{padding:5px 7px;border-top:2px solid #1a2744}

/* ── Section headings ── */
.rpt-section{
  font-size:10pt;font-weight:700;color:#1a2744;
  border-bottom:2px solid #1a2744;padding-bottom:3px;
  margin:14px 0 8px;text-transform:uppercase;letter-spacing:.05em
}

/* ── Footer ── */
.rpt-footer{
  position:fixed;bottom:0;left:0;right:0;
  border-top:2px solid #1a2744;
  padding:5px 16px;
  display:flex;justify-content:space-between;
  font-size:7.5pt;color:#666;
  background:#fff;
}
.rpt-footer strong{color:#1a2744}

/* ── Print ── */
@page{size:A4;margin:8mm 8mm 18mm 8mm}
@media print{
  body{background:#fff}
  .no-print{display:none!important}
  .rpt-footer{position:fixed;bottom:0}
  .page-break{page-break-before:always}
  .wm{position:fixed}
}
</style>
</head>
<body>

<!-- Watermark -->
<img class="wm" src="'.htmlspecialchars($logo).'" alt=""
     onerror="this.style.display=\'none\'"/>

<!-- Header -->
<div class="rpt-header">
  <div class="rpt-header-left">
    <img class="rpt-logo" src="'.htmlspecialchars($logo).'" alt="KHS"
         onerror="this.style.display=\'none\'"/>
    <div>
      <div class="rpt-school-name">'.htmlspecialchars($school).'</div>
      <div class="rpt-school-sub">
        '.htmlspecialchars($address).'<br>
        Tel: '.htmlspecialchars($phone).'
        '.($email ? ' &nbsp;|&nbsp; '.htmlspecialchars($email) : '').'
      </div>
    </div>
  </div>
  <div class="rpt-header-right">
    <div><strong>Date:</strong> '.htmlspecialchars($date).'</div>
    <div><strong>Time:</strong> '.htmlspecialchars($time).'</div>
    <div style="margin-top:4px;font-size:7pt;color:#aaa">OFFICIAL SCHOOL DOCUMENT</div>
  </div>
</div>

<!-- Title -->
<div class="rpt-title-block">
  <h1>'.htmlspecialchars($title).'</h1>
  '.($subtitle ? '<p>'.htmlspecialchars($subtitle).'</p>' : '').'
</div>

<!-- Body -->
<div class="rpt-body">
'.$bodyHtml.'
</div>

<!-- Footer (fixed at bottom on every print page) -->
<div class="rpt-footer">
  <span><strong>'.htmlspecialchars($school).'</strong> &nbsp;|&nbsp; '.htmlspecialchars($address).'</span>
  <span>Generated: '.htmlspecialchars($date.' at '.$time).' &nbsp;|&nbsp; <strong>'.htmlspecialchars($title).'</strong></span>
</div>

<script>
// Auto-trigger print dialog when opened directly
if (window.location.search.indexOf("autoprint=1") !== -1) {
  window.onload = function(){ window.print(); }
}
</script>
</body>
</html>';
    exit;
}

} // end pdfReportPage


// ── Excel XML export ──────────────────────────────────────────
if (!function_exists('excelExport')) {

/**
 * Stream an Excel-compatible SpreadsheetML (.xls) file.
 *
 * @param string   $filename  Filename without extension (will add .xls)
 * @param array    $headers   Array of column header strings
 * @param array    $rows      2D array of data rows (each row is an array of values)
 * @param string   $sheetName Worksheet name (default: 'Report')
 */
function excelExport(string $filename, array $headers, array $rows, string $sheetName = 'Report'): never
{
    $school = setting('school_name', 'KARN HIGH SCHOOL');
    $date   = date('d F Y H:i');

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename).'.xls"');
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');

    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>'.esc($school).'</Author>
  <Created>'.date('Y-m-d\TH:i:s\Z').'</Created>
  <Title>'.esc($filename).'</Title>
</DocumentProperties>
<Styles>
  <Style ss:ID="sTitle">
    <Alignment ss:Horizontal="Left"/>
    <Font ss:Bold="1" ss:Size="14" ss:Color="#1a2744"/>
  </Style>
  <Style ss:ID="sSub">
    <Font ss:Italic="1" ss:Size="9" ss:Color="#666666"/>
  </Style>
  <Style ss:ID="sHeader">
    <Alignment ss:Horizontal="Center" ss:WrapText="1"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1a2744"/>
    </Borders>
    <Font ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1a2744" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="sEven">
    <Interior ss:Color="#F5F7FA" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="sOdd">
    <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="sTotal">
    <Font ss:Bold="1"/>
    <Interior ss:Color="#E8EAF0" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#1a2744"/>
    </Borders>
  </Style>
</Styles>
<Worksheet ss:Name="'.esc(mb_substr($sheetName, 0, 31)).'">
<Table>';

    $colCount = max(count($headers), 1);

    // School name row
    echo '<Row>
  <Cell ss:MergeAcross="'.($colCount-1).'" ss:StyleID="sTitle">
    <Data ss:Type="String">'.esc($school).'</Data>
  </Cell>
</Row>';

    // Subtitle row
    echo '<Row>
  <Cell ss:MergeAcross="'.($colCount-1).'" ss:StyleID="sSub">
    <Data ss:Type="String">'.esc($filename.' — Generated: '.$date).'</Data>
  </Cell>
</Row>';

    // Blank row spacer
    echo '<Row><Cell><Data ss:Type="String"></Data></Cell></Row>';

    // Headers row
    echo '<Row>';
    foreach ($headers as $h) {
        echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">'.esc($h).'</Data></Cell>';
    }
    echo '</Row>';

    // Data rows
    foreach ($rows as $i => $row) {
        $style = ($i % 2 === 0) ? 'sEven' : 'sOdd';
        echo '<Row>';
        foreach ((array)$row as $cell) {
            $val  = (string)$cell;
            $type = is_numeric($val) && !preg_match('/^0\d/', $val) ? 'Number' : 'String';
            echo '<Cell ss:StyleID="'.$style.'"><Data ss:Type="'.$type.'">'.esc($val).'</Data></Cell>';
        }
        echo '</Row>';
    }

    // Total row if data present
    if (!empty($rows)) {
        echo '<Row><Cell ss:MergeAcross="'.($colCount-1).'" ss:StyleID="sTotal">
  <Data ss:Type="String">Total rows: '.count($rows).'  |  '.esc($school).'  |  Generated: '.esc($date).'</Data>
</Cell></Row>';
    }

    echo '</Table>
</Worksheet>
</Workbook>';
    exit;
}

} // end excelExport


// ── CSV export ────────────────────────────────────────────────
if (!function_exists('csvExport')) {

/**
 * Stream a UTF-8 CSV file.
 */
function csvExport(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename).'.csv"');
    header('Cache-Control: max-age=0');

    $fp = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);
    foreach ($rows as $row) {
        fputcsv($fp, array_values((array)$row));
    }
    fclose($fp);
    exit;
}

} // end csvExport


// ── Shared: build PDF body table ──────────────────────────────
if (!function_exists('buildReportTable')) {

/**
 * Build an HTML table suitable for pdfReportPage() body.
 *
 * @param string $sectionTitle Optional section heading
 * @param array  $headers      Column headers
 * @param array  $rows         Data rows (array of arrays)
 * @param array  $numCols      0-indexed column numbers that should be right-aligned
 */
function buildReportTable(
    string $sectionTitle,
    array  $headers,
    array  $rows,
    array  $numCols = []
): string {
    $html = '';
    if ($sectionTitle) {
        $html .= '<div class="rpt-section">'.htmlspecialchars($sectionTitle).'</div>';
    }
    $html .= '<table><thead><tr>';
    foreach ($headers as $i => $h) {
        $cls = in_array($i, $numCols) ? ' class="num"' : '';
        $html .= '<th'.$cls.'>'.htmlspecialchars($h).'</th>';
    }
    $html .= '</tr></thead><tbody>';
    if (empty($rows)) {
        $html .= '<tr><td colspan="'.count($headers).'" style="text-align:center;padding:16px;color:#888">No data found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_values((array)$row) as $i => $cell) {
                $cls = in_array($i, $numCols) ? ' class="num"' : '';
                $html .= '<td'.$cls.'>'.htmlspecialchars((string)$cell).'</td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</tbody>';
    // Totals row
    $html .= '<tfoot><tr><td colspan="'.count($headers).'">Total: '.count($rows).' record'.(count($rows)!==1?'s':'').'</td></tr></tfoot>';
    $html .= '</table>';
    return $html;
}

} // end buildReportTable
