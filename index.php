<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>سیستم تولید راپور</title>
  <style>
    :root{--green:#174a35;--gold:#d7b65d;--bg:#f4f6f4;--border:#dce4dd}
    *{box-sizing:border-box}
    body{margin:0;font-family:Tahoma,Arial,sans-serif;background:var(--bg);color:#202923;line-height:1.7}
    header{background:var(--green);color:#fff;border-bottom:4px solid var(--gold);padding:22px 24px}
    .inner,main{width:min(100% - 32px,900px);margin:auto}
    .inner{display:flex;justify-content:space-between;align-items:center;gap:16px}
    h1{font-size:clamp(1.25rem,3vw,1.8rem);margin:0}
    header p{margin:4px 0 0;color:#e2ebe5}
    .step{color:#f2dfa2;font-size:.9rem}
    main{padding:34px 0 54px}
    .intro{margin-bottom:22px}.intro h2{margin:0 0 8px;font-size:1.4rem}.intro p{margin:0;color:#66736a}
    .panel{background:#fff;border:1px solid var(--border);border-radius:14px;padding:clamp(20px,4vw,32px);box-shadow:0 8px 24px #14321e0d}
    .upload{border:2px dashed #b8c9bc;border-radius:12px;padding:30px 18px;text-align:center;background:#fbfdfb}
    .upload h3{margin:0 0 6px}.upload p{margin:0 0 18px;color:#66736a;font-size:.92rem}
    input[type=file]{display:block;width:100%;max-width:420px;margin:auto;padding:10px;border:1px solid var(--border);border-radius:8px;background:white;font-family:inherit}
    .info{margin-top:20px;padding:15px;border:1px solid var(--border);border-radius:10px;background:#f5f8f5}
    .info strong{display:block;margin-bottom:3px}.info p{margin:0;color:#66736a;font-size:.9rem}
    button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:8px;background:var(--green);color:white;font-family:inherit;font-size:1rem;font-weight:bold;cursor:pointer}
    button:hover{background:#103526}button:focus-visible,input:focus-visible{outline:3px solid var(--gold);outline-offset:3px}
    footer{text-align:center;padding:18px;color:#66736a;font-size:.85rem}
    @media(max-width:520px){.inner{align-items:flex-start;flex-direction:column}main{padding-top:24px}}
  </style>
</head>
<body>
  <header><div class="inner"><div><h1>سیستم تولید راپور</h1><p>تبدیل فهرست اکسل به راپور قابل چاپ PDF</p></div><div class="step">مرحله اول: بارگذاری فایل</div></div></header>
  <main>
    <section class="intro"><h2>ساخت راپور از فهرست اکسل</h2><p>فایل اکسل شاگردان را انتخاب کنید تا راپور PDF ساخته شود.</p></section>
    <section class="panel">
      <form action="generate.php" method="post" enctype="multipart/form-data">
        <div class="upload">
          <h3>فایل اکسل خود را انتخاب کنید</h3><p>فورمت‌های قابل قبول: .xlsx و .xls</p>
          <input type="file" id="excel-file" name="excel_file" accept=".xlsx,.xls,.csv" required>
        </div>
        <div class="info"><strong>قالب راپور</strong><p>فایل اکسل خوانده می‌شود و معلومات آن در یک جدول قابل چاپ قرار می‌گیرد. قالب رسمی نمونه در مرحله بعدی دقیق‌تر تنظیم می‌شود.</p></div>
        <button type="submit">تولید راپور PDF</button>
      </form>
    </section>
  </main>
  <footer>سیستم تولید راپور اکسل به PDF</footer>
</body>
</html>
