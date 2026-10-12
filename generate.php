<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excel_file'])) {
    http_response_code(400);
    exit('لطفاً از صفحه اصلی یک فایل اکسل انتخاب کنید.');
}
if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('بارگذاری فایل موفق نشد. لطفاً فایل را دوباره انتخاب کنید.');
}
$originalName = basename((string)($_FILES['excel_file']['name'] ?? 'students.xlsx'));
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    http_response_code(400);
    exit('فقط فایل‌های XLSX، XLS یا CSV قابل قبول است.');
}
if (!is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
    http_response_code(400);
    exit('فایل بارگذاری‌شده معتبر نیست.');
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

function esc(mixed $value): string {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $allRows = $sheet->toArray(null, true, true, false);
    $allRows = array_values(array_filter($allRows, static function ($row): bool {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') return true;
        }
        return false;
    }));

    if (count($allRows) < 2) {
        throw new RuntimeException('فایل اکسل باید شامل ردیف عنوان و حداقل یک شاگرد باشد.');
    }

    $sourceHeaders = array_map(static fn($v) => strtolower(trim((string)$v)), $allRows[0]);
    $findColumn = static function (array $headers, array $aliases): ?int {
        foreach ($headers as $i => $header) {
            $normalized = preg_replace('/[\\s_\\-]+/', '', (string)$header);
            foreach ($aliases as $alias) {
                if ($normalized === preg_replace('/[\\s_\\-]+/', '', strtolower($alias))) return $i;
            }
        }
        return null;
    };

    $noCol = $findColumn($sourceHeaders, ['no', 'number', 'studentno', 'شماره', 'نمبر']);
    $nameCol = $findColumn($sourceHeaders, ['name', 'studentname', 'نام', 'نامشاگرد']);
    $fatherCol = $findColumn($sourceHeaders, ['fathername', 'father', 'نامپدر', 'نامپدرشاگرد']);
    if ($nameCol === null || $fatherCol === null) {
        throw new RuntimeException('ستون‌های name و fathername پیدا نشدند. لطفاً عنوان ستون‌ها را مطابق فایل نمونه نگه دارید.');
    }

    // Exclude template/numbering rows that have no student name or father's name.
    $records = [];
    foreach (array_slice($allRows, 1) as $row) {
        $name = trim((string)($row[$nameCol] ?? ''));
        $father = trim((string)($row[$fatherCol] ?? ''));
        if ($name === '' && $father === '') continue;
        $number = $noCol !== null ? trim((string)($row[$noCol] ?? '')) : '';
        $records[] = [
            'no' => $number !== '' ? $number : (string)(count($records) + 1),
            'name' => $name,
            'father' => $father,
        ];
    }
    if (!$records) throw new RuntimeException('هیچ شاگردی با نام یا نام پدر در فایل پیدا نشد.');

    // Keep the supplied student number; do not add a duplicate serial column.
    $split = (int)ceil(count($records) / 2);
    $leftRecords = array_slice($records, 0, $split);
    $rightRecords = array_slice($records, $split);

    $makeTable = static function (array $items, int $startIndex): string {
        $html = '<table class="students"><thead><tr><th class="number">شماره</th><th>نام شاگرد</th><th>نام پدر</th></tr></thead><tbody>';
        foreach ($items as $record) {
            $html .= '<tr><td class="number">' . esc($record['no']) . '</td><td>' . esc($record['name']) . '</td><td>' . esc($record['father']) . '</td></tr>';
        }
        // Add blank ruled rows for a register-style print layout without counting them as students.
        $targetRows = max(count($items), 12);
        for ($i = count($items); $i < $targetRows; $i++) {
            $html .= '<tr><td class="number">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }
        return $html . '</tbody></table>';
    };

    $school = esc($_POST['school_name'] ?? '');
    $className = esc($_POST['class_name'] ?? '');
    $teacher = esc($_POST['teacher_name'] ?? '');
    $subject = esc($_POST['subject_name'] ?? '');
    $academicYear = esc($_POST['academic_year'] ?? '');
    $reportDate = esc($_POST['report_date'] ?? date('Y-m-d'));

    $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><style>
      @page { size: A4 landscape; margin: 10mm 9mm; }
      body { font-family: DejaVu Sans, sans-serif; direction: rtl; color: #111; font-size: 9px; }
      .top { width:100%; border-collapse:collapse; margin-bottom:5px; }
      .top td { border:0; vertical-align:middle; text-align:center; }
      .emblem { width:18%; font-size:8px; }
      h1 { font-size:15px; margin:0 0 3px; }
      h2 { font-size:11px; margin:0; }
      .subhead { font-size:9px; margin-top:3px; }
      .details { width:100%; border-collapse:collapse; margin:7px 0 8px; }
      .details td { border:1px solid #333; padding:4px 5px; width:20%; }
      .columns { width:100%; border-collapse:collapse; table-layout:fixed; }
      .columns > tbody > tr > td { width:50%; vertical-align:top; padding:0 3px; }
      table.students { width:100%; border-collapse:collapse; table-layout:fixed; }
      .students th,.students td { border:1px solid #222; padding:3px 2px; height:17px; text-align:center; vertical-align:middle; overflow-wrap:break-word; }
      .students th { font-weight:bold; background:#ececec; }
      .students .number { width:35px; }
      .signatures { width:100%; border-collapse:collapse; margin-top:12px; }
      .signatures td { border:0; text-align:center; padding-top:16px; width:33.3%; }
      .signline { display:block; border-top:1px solid #333; margin:0 14px 4px; }
      .note { margin-top:7px; font-size:8px; }
    </style></head><body>
    <table class="top"><tr>
      <td class="emblem">نشان رسمی<br>وزارت معارف</td>
      <td><h1>وزارت معارف</h1><h2>فورم ثبت و راپور شاگردان</h2><div class="subhead">فهرست شاگردان</div></td>
      <td class="emblem">مکتب / اداره<br>فورم رسمی</td>
    </tr></table>
    <table class="details"><tr>
      <td>نام مکتب: ' . ($school !== '' ? $school : '&nbsp;') . '</td>
      <td>صنف: ' . ($className !== '' ? $className : '&nbsp;') . '</td>
      <td>نام آموزگار: ' . ($teacher !== '' ? $teacher : '&nbsp;') . '</td>
      <td>مضمون: ' . ($subject !== '' ? $subject : '&nbsp;') . '</td>
      <td>سال تعلیمی: ' . ($academicYear !== '' ? $academicYear : '&nbsp;') . '</td>
    </tr></table>
    <table class="columns"><tr><td>' . $makeTable($leftRecords, 0) . '</td><td>' . $makeTable($rightRecords, $split) . '</td></tr></table>
    <div class="note">تعداد مجموعی شاگردان: ' . count($records) . ' &nbsp;&nbsp; تاریخ: ' . $reportDate . '</div>
    <table class="signatures"><tr>
      <td><span class="signline"></span>امضای آموزگار</td>
      <td><span class="signline"></span>امضای مدیر مکتب</td>
      <td><span class="signline"></span>مهر مکتب</td>
    </tr></table>
    </body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('student-report-' . date('Ymd-His') . '.pdf', ['Attachment' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(422);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="UTF-8"><body style="font-family:Tahoma,Arial;padding:30px">';
    echo '<h2>ساخت راپور موفق نشد</h2><p>' . esc($e->getMessage()) . '</p><p><a href="index.php">بازگشت به صفحه اصلی</a></p></body></html>';
}
