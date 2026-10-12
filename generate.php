<?php
declare(strict_types=1);

/**
 * Excel-to-PDF report generator.
 * Dependencies: phpoffice/phpspreadsheet and dompdf/dompdf (Composer).
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excel_file'])) {
    http_response_code(400);
    exit('لطفاً از صفحه اصلی یک فایل اکسل انتخاب کنید.');
}

if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('بارگذاری فایل موفق نشد. لطفاً دوباره تلاش کنید.');
}

$originalName = $_FILES['excel_file']['name'] ?? 'file.xlsx';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    http_response_code(400);
    exit('فقط فایل‌های XLSX، XLS یا CSV قابل قبول است.');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('کتابخانه‌ها نصب نشده‌اند. در پوشه پروژه دستور composer install را اجرا کنید.');
}
require $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    // Remove fully empty rows while preserving the first non-empty row as the header.
    $rows = array_values(array_filter($rows, static function ($row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }
        return false;
    }));

    if (count($rows) < 2) {
        throw new RuntimeException('فایل باید حداقل یک ردیف عنوان و یک ردیف معلومات داشته باشد.');
    }

    $headers = array_map(static function ($value, $index): string {
        $value = trim((string) $value);
        return $value !== '' ? $value : 'ستون ' . ($index + 1);
    }, $rows[0], array_keys($rows[0]));

    $dataRows = array_slice($rows, 1);
    $columnCount = count($headers);
    $bodyRows = '';

    foreach ($dataRows as $row) {
        $bodyRows .= '<tr>';
        for ($i = 0; $i < $columnCount; $i++) {
            $value = isset($row[$i]) ? (string) $row[$i] : '';
            $bodyRows .= '<td>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
        }
        $bodyRows .= '</tr>';
    }

    $headerCells = '';
    foreach ($headers as $header) {
        $headerCells .= '<th>' . htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
    }

    $reportTitle = 'راپور معلومات';
    $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
    <style>
      @page { margin: 24px 22px; }
      body { font-family: DejaVu Sans, sans-serif; direction: rtl; color: #17231b; font-size: 10px; }
      h1 { text-align: center; font-size: 18px; margin: 0 0 5px; color: #174a35; }
      .subtitle { text-align: center; color: #526258; margin-bottom: 18px; font-size: 10px; }
      .meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
      .meta td { border: 1px solid #9eaaa1; padding: 6px; }
      table.data { width: 100%; border-collapse: collapse; table-layout: auto; }
      .data th { background: #174a35; color: #fff; font-weight: bold; }
      .data th, .data td { border: 1px solid #77857a; padding: 5px 4px; text-align: right; vertical-align: top; overflow-wrap: break-word; }
      .data tr:nth-child(even) td { background: #f1f5f1; }
      .footer { margin-top: 28px; display: table; width: 100%; }
      .signature { display: table-cell; width: 33%; text-align: center; padding-top: 22px; }
    </style></head><body>
    <h1>' . $reportTitle . '</h1>
    <div class="subtitle">تهیه‌شده از فهرست اکسل</div>
    <table class="meta"><tr><td>نام فایل: ' . htmlspecialchars($originalName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td>تعداد ردیف‌ها: ' . count($dataRows) . '</td><td>تاریخ: ' . date('Y-m-d') . '</td></tr></table>
    <table class="data"><thead><tr>' . $headerCells . '</tr></thead><tbody>' . $bodyRows . '</tbody></table>
    <div class="footer"><div class="signature">امضای مسئول</div><div class="signature">امضای مدیر</div><div class="signature">مهر اداره</div></div>
    </body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', $columnCount > 6 ? 'landscape' : 'portrait');
    $dompdf->render();

    $downloadName = 'excel-report-' . date('Ymd-His') . '.pdf';
    $dompdf->stream($downloadName, ['Attachment' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(422);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="UTF-8"><body style="font-family:Tahoma,Arial;padding:30px">';
    echo '<h2>ساخت راپور موفق نشد</h2><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    echo '<p><a href="index.php">بازگشت به صفحه اصلی</a></p></body></html>';
}
