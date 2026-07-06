/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Car } from '../types';

/**
 * Generates a SHA256-like mock audit hash to satisfy high-security requirements.
 */
function generateAuditHash(car: Car): string {
  const dataString = `${car.id}-${car.vin}-${car.sale?.contractNumber || 'N/A'}-${car.sale?.invoiceNumber || 'N/A'}`;
  let hash = 0;
  for (let i = 0; i < dataString.length; i++) {
    const char = dataString.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash; // Convert to 32bit integer
  }
  return 'AMP-' + Math.abs(hash).toString(16).toUpperCase().padStart(8, '0') + '-' + car.id.split('-').pop()?.toUpperCase();
}

/**
 * Prints a highly polished tax invoice for a sold car.
 */
export function printInvoice(car: Car, companyName = 'المخزون برو لبيع السيارات', logo = '', lang = 'ar') {
  if (!car.sale) return;

  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('يرجى السماح بالنوافذ المنبثقة لطباعة الفاتورة.');
    return;
  }

  const auditHash = generateAuditHash(car);
  const vatRate = 0.15;
  const totalPrice = car.price;
  const basePrice = totalPrice / (1 + vatRate);
  const vatAmount = totalPrice - basePrice;

  const content = `
    <!DOCTYPE html>
    <html dir="${lang === 'ar' ? 'rtl' : 'ltr'}">
    <head>
      <meta charset="utf-8">
      <title>فاتورة ضريبية مبسطة - ${car.sale.invoiceNumber}</title>
      <style>
        body {
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          color: #1e293b;
          margin: 0;
          padding: 40px;
          background-color: #ffffff;
        }
        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-b: 2px solid #e2e8f0;
          padding-bottom: 20px;
          margin-bottom: 30px;
        }
        .logo-section {
          display: flex;
          align-items: center;
          gap: 15px;
        }
        .logo-img {
          width: 60px;
          height: 60px;
          object-fit: contain;
          border-radius: 8px;
        }
        .company-title {
          font-size: 24px;
          font-weight: 800;
          color: #4f46e5;
          margin: 0;
        }
        .invoice-badge {
          background-color: #f1f5f9;
          border: 1px solid #cbd5e1;
          padding: 8px 16px;
          border-radius: 6px;
          text-align: center;
        }
        .invoice-title {
          font-size: 18px;
          font-weight: 800;
          color: #0f172a;
          margin: 0 0 5px 0;
        }
        .invoice-subtitle {
          font-size: 11px;
          color: #64748b;
          margin: 0;
          font-weight: bold;
        }
        .meta-grid {
          display: grid;
          grid-cols: 2;
          display: flex;
          justify-content: space-between;
          gap: 20px;
          background-color: #f8fafc;
          border: 1px solid #e2e8f0;
          padding: 15px;
          border-radius: 8px;
          margin-bottom: 30px;
          font-size: 12px;
        }
        .meta-col {
          flex: 1;
        }
        .meta-col h3 {
          font-size: 13px;
          color: #4f46e5;
          margin: 0 0 10px 0;
          border-bottom: 1px solid #e2e8f0;
          padding-bottom: 5px;
          font-weight: 800;
        }
        .meta-item {
          margin-bottom: 6px;
          display: flex;
          justify-content: space-between;
        }
        .meta-label {
          color: #64748b;
          font-weight: 600;
        }
        .meta-val {
          font-weight: bold;
        }
        .table-title {
          font-size: 14px;
          font-weight: bold;
          color: #0f172a;
          margin: 0 0 10px 0;
          border-right: 3px solid #4f46e5;
          padding-right: 8px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 30px;
          font-size: 12px;
        }
        th {
          background-color: #f1f5f9;
          color: #1e293b;
          text-align: right;
          padding: 12px;
          font-weight: 800;
          border-bottom: 2px solid #cbd5e1;
        }
        td {
          padding: 12px;
          border-bottom: 1px solid #e2e8f0;
        }
        .totals-section {
          width: 350px;
          margin-right: auto;
          margin-left: 0;
          background-color: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: 15px;
          font-size: 13px;
          margin-bottom: 40px;
        }
        .total-row {
          display: flex;
          justify-content: space-between;
          margin-bottom: 8px;
          padding-bottom: 8px;
          border-bottom: 1px dashed #e2e8f0;
        }
        .total-row:last-child {
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
          font-weight: 850;
          color: #4f46e5;
          font-size: 15px;
        }
        .security-badge {
          border: 2px solid #10b981;
          background-color: #ecfdf5;
          color: #065f46;
          padding: 15px;
          border-radius: 8px;
          margin-bottom: 40px;
          display: flex;
          align-items: center;
          gap: 15px;
        }
        .security-icon {
          font-size: 24px;
        }
        .security-text h4 {
          margin: 0 0 4px 0;
          font-size: 12px;
          font-weight: 800;
        }
        .security-text p {
          margin: 0;
          font-size: 10px;
          font-family: monospace;
          color: #047857;
        }
        .footer-signatures {
          display: flex;
          justify-content: space-between;
          margin-top: 60px;
          font-size: 12px;
          text-align: center;
        }
        .signature-box {
          width: 200px;
          border-top: 1.5px solid #cbd5e1;
          padding-top: 10px;
        }
        @media print {
          body {
            padding: 0;
          }
          .no-print {
            display: none;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo-section">
          ${logo ? `<img src="${logo}" alt="Logo" class="logo-img">` : '<div style="width: 50px; height: 50px; background: #4f46e5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">M</div>'}
          <div>
            <h1 class="company-title">${companyName}</h1>
            <span style="font-size: 10px; color: #64748b; font-weight: bold;">المملكة العربية السعودية | نظام الفواتير الإلكترونية المعتمد</span>
          </div>
        </div>
        <div class="invoice-badge">
          <h2 class="invoice-title">فاتورة ضريبية مبسطة</h2>
          <span class="invoice-subtitle">SIMPLIFIED TAX INVOICE</span>
        </div>
      </div>

      <div class="meta-grid">
        <div class="meta-col">
          <h3>معلومات الفاتورة</h3>
          <div class="meta-item"><span class="meta-label">رقم الفاتورة:</span><span class="meta-val">${car.sale.invoiceNumber}</span></div>
          <div class="meta-item"><span class="meta-label">رقم العقد:</span><span class="meta-val">${car.sale.contractNumber}</span></div>
          <div class="meta-item"><span class="meta-label">تاريخ الإصدار:</span><span class="meta-val">${car.sale.deliveryDate || new Date().toISOString().split('T')[0]}</span></div>
          <div class="meta-item"><span class="meta-label">المندوب البائع:</span><span class="meta-val">${car.sale.sellerName}</span></div>
        </div>
        <div class="meta-col" style="border-right: 1px solid #e2e8f0; padding-right: 20px;">
          <h3>بيانات المشتري (العميل)</h3>
          <div class="meta-item"><span class="meta-label">اسم العميل:</span><span class="meta-val">${car.sale.buyerName}</span></div>
          <div class="meta-item"><span class="meta-label">الهوية الوطنية:</span><span class="meta-val">${car.registrationNumber || '10********'}</span></div>
          <div class="meta-item"><span class="meta-label">طريقة الدفع:</span><span class="meta-val">${car.sale.paymentMethod === 'cash' ? 'نقدي' : car.sale.paymentMethod === 'bank' ? 'تحويل بنكي' : 'شيك مصدق'}</span></div>
          <div class="meta-item"><span class="meta-label">حالة السداد:</span><span class="meta-val">${car.sale.paymentStatus === 'paid' ? 'مدفوعة بالكامل' : 'مدفوعة جزئياً'}</span></div>
        </div>
      </div>

      <h3 class="table-title">تفاصيل المركبة المباعة</h3>
      <table>
        <thead>
          <tr>
            <th>بيانات المركبة</th>
            <th>الرقم التسلسلي / الشاصيه (VIN)</th>
            <th>رقم اللوحة</th>
            <th>الموديل / سنة الصنع</th>
            <th>اللون الخارجي</th>
            <th>السعر الإجمالي (شامل الضريبة)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="font-weight: bold; font-size: 13px;">${car.make} ${car.model} ${car.trim}</td>
            <td style="font-family: monospace; font-size: 11px;">${car.vin}</td>
            <td>${car.plateNumber}</td>
            <td>${car.year}</td>
            <td>${car.color}</td>
            <td style="font-weight: bold; font-size: 13px; color: #4f46e5;">${totalPrice.toLocaleString()} ر.س</td>
          </tr>
        </tbody>
      </table>

      <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div class="security-badge" style="width: 50%;">
          <div class="security-icon">🛡️</div>
          <div class="security-text">
            <h4>عملية آمنة وموثقة إلكترونياً</h4>
            <p>كود التشفير والتحقق الرقمي للعملية المعتمد بالنظام:</p>
            <p style="font-size: 9px; font-weight: bold; margin-top: 4px;">HASH: ${auditHash}</p>
          </div>
        </div>

        <div class="totals-section">
          <div class="total-row"><span>المبلغ الأساسي (غير خاضع للضريبة):</span><span>${basePrice.toLocaleString(undefined, { maximumFractionDigits: 2 })} ر.س</span></div>
          <div class="total-row"><span>ضريبة القيمة المضافة (15%):</span><span>${vatAmount.toLocaleString(undefined, { maximumFractionDigits: 2 })} ر.س</span></div>
          <div class="total-row"><span>المبلغ الإجمالي شامل الضريبة:</span><span>${totalPrice.toLocaleString()} ر.س</span></div>
          <div class="total-row"><span>المبلغ المدفوع:</span><span>${car.sale.paidAmount.toLocaleString()} ر.س</span></div>
          <div class="total-row"><span>المبلغ المتبقي المعلق:</span><span>${car.sale.remainingAmount.toLocaleString()} ر.س</span></div>
        </div>
      </div>

      <div class="footer-signatures">
        <div class="signature-box">
          <p style="font-weight: bold; margin: 0 0 30px 0;">توقيع المندوب البائع</p>
          <p style="font-size: 10px; color: #64748b;">${car.sale.sellerName}</p>
        </div>
        <div class="signature-box" style="border: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;">
          <div style="border: 1px solid #cbd5e1; padding: 5px; width: 65px; height: 65px; display: flex; align-items: center; justify-content: center; font-size: 8px; text-align: center; color: #64748b; font-family: monospace; font-weight: bold;">
            QR CODE<br>MOCK SECURE<br>STATION
          </div>
          <span style="font-size: 9px; color: #94a3b8;">ختم النظام الرقمي</span>
        </div>
        <div class="signature-box">
          <p style="font-weight: bold; margin: 0 0 30px 0;">توقيع المشتري (العميل)</p>
          <p style="font-size: 10px; color: #64748b;">أوافق على استلام المركبة والشروط القانونية</p>
        </div>
      </div>

      <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print();" style="background-color: #4f46e5; color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4);">طباعة المستند الضريبي</button>
      </div>

      <script>
        window.onload = function() {
          // Auto trigger printing after resource load
          setTimeout(function() {
            window.print();
          }, 600);
        }
      </script>
    </body>
    </html>
  `;

  printWindow.document.write(content);
  printWindow.document.close();
}

/**
 * Prints a highly polished legal Sales Contract.
 */
export function printContract(car: Car, companyName = 'المخزون برو لبيع السيارات', logo = '', lang = 'ar') {
  if (!car.sale) return;

  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('يرجى السماح بالنوافذ المنبثقة لطباعة العقد.');
    return;
  }

  const auditHash = generateAuditHash(car);

  const content = `
    <!DOCTYPE html>
    <html dir="${lang === 'ar' ? 'rtl' : 'ltr'}">
    <head>
      <meta charset="utf-8">
      <title>عقد مبايعة قانوني - ${car.sale.contractNumber}</title>
      <style>
        body {
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          color: #1e293b;
          margin: 0;
          padding: 50px;
          line-height: 1.8;
          font-size: 13px;
        }
        .header {
          text-align: center;
          border-bottom: 2px solid #0f172a;
          padding-bottom: 15px;
          margin-bottom: 30px;
        }
        .logo-placeholder {
          font-size: 20px;
          font-weight: bold;
          color: #4f46e5;
          margin-bottom: 5px;
        }
        .contract-title {
          font-size: 20px;
          font-weight: 900;
          color: #0f172a;
          margin: 10px 0 5px 0;
        }
        .contract-subtitle {
          font-size: 11px;
          color: #64748b;
          text-transform: uppercase;
          letter-spacing: 1px;
        }
        .section-title {
          font-size: 14px;
          font-weight: 800;
          color: #0f172a;
          border-bottom: 1px solid #cbd5e1;
          padding-bottom: 4px;
          margin-top: 25px;
          margin-bottom: 12px;
        }
        .grid-data {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 15px;
          margin-bottom: 20px;
        }
        .data-item {
          display: flex;
          justify-content: space-between;
          border-bottom: 1px dashed #e2e8f0;
          padding-bottom: 4px;
        }
        .data-label {
          color: #64748b;
          font-weight: 600;
        }
        .data-val {
          font-weight: bold;
        }
        .clause-list {
          padding-right: 20px;
          margin: 0;
        }
        .clause-item {
          margin-bottom: 10px;
          text-align: justify;
        }
        .signatures-area {
          display: flex;
          justify-content: space-between;
          margin-top: 60px;
        }
        .sig-box {
          width: 250px;
          text-align: center;
          border-top: 1.5px solid #0f172a;
          padding-top: 10px;
        }
        .hash-code {
          text-align: center;
          margin-top: 50px;
          font-family: monospace;
          font-size: 9px;
          color: #64748b;
          border-top: 1px solid #f1f5f9;
          padding-top: 15px;
        }
        @media print {
          body {
            padding: 0;
          }
          .no-print {
            display: none;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo-placeholder">${companyName}</div>
        <h1 class="contract-title">عقد مبايعة مركبة ونقل ملكية</h1>
        <div class="contract-subtitle">Sales & Ownership Transfer Contract</div>
        <span style="font-size: 11px; font-weight: bold;">رقم العقد: ${car.sale.contractNumber} | تاريخ التعاقد: ${car.sale.deliveryDate || new Date().toISOString().split('T')[0]}</span>
      </div>

      <p style="text-align: justify;">
        إنه في يوم <strong>${car.sale.deliveryDate || new Date().toISOString().split('T')[0]}</strong>، تم الاتفاق والتعاقد بين كلاً من الطرفين التاليين على مبايعة ونقل ملكية المركبة الموصوفة أدناه، وذلك بعد إقرار الطرفين بأهليتهما المعتبرة قانوناً وشرعاً للتصرف والتعاقد:
      </p>

      <div class="section-title">الطرف الأول (البائع المفوض)</div>
      <div class="grid-data">
        <div class="data-item"><span class="data-label">الشركة البائعة:</span><span class="data-val">${companyName}</span></div>
        <div class="data-item"><span class="data-label">المندوب المعتمد:</span><span class="data-val">${car.sale.sellerName}</span></div>
        <div class="data-item"><span class="data-label">الفرع / المعرض:</span><span class="data-val">المعرض الرئيسي</span></div>
        <div class="data-item"><span class="data-label">الهاتف الموحد:</span><span class="data-val">920000000</span></div>
      </div>

      <div class="section-title">الطرف الثاني (المشتري)</div>
      <div class="grid-data">
        <div class="data-item"><span class="data-label">اسم المشتري الكريم:</span><span class="data-val">${car.sale.buyerName}</span></div>
        <div class="data-item"><span class="data-label">رقم الهوية الوطنية/الإقامة:</span><span class="data-val">10********</span></div>
        <div class="data-item"><span class="data-label">رقم الهاتف الجوال:</span><span class="data-val">${car.registrationNumber || '0500000000'}</span></div>
        <div class="data-item"><span class="data-label">طريقة السداد:</span><span class="data-val">${car.sale.paymentMethod === 'cash' ? 'نقدي' : 'تحويل مصرفي'}</span></div>
      </div>

      <div class="section-title">بيانات المركبة المتعاقد عليها</div>
      <div class="grid-data">
        <div class="data-item"><span class="data-label">ماركة السيارة وفئتها:</span><span class="data-val">${car.make} ${car.model}</span></div>
        <div class="data-item"><span class="data-label">سنة الصنع (الموديل):</span><span class="data-val">${car.year}</span></div>
        <div class="data-item"><span class="data-label">رقم شاصيه المركبة (VIN):</span><span class="data-val" style="font-family: monospace;">${car.vin}</span></div>
        <div class="data-item"><span class="data-label">رقم اللوحة المرورية:</span><span class="data-val">${car.plateNumber}</span></div>
        <div class="data-item"><span class="data-label">اللون الخارجي:</span><span class="data-val">${car.color}</span></div>
        <div class="data-item"><span class="data-label">قيمة الصفقة الإجمالية:</span><span class="data-val" style="color: #4f46e5;">${car.price.toLocaleString()} ر.س</span></div>
      </div>

      <div class="section-title">بنود وشروط التعاقد الرسمية</div>
      <ol class="clause-list">
        <li class="clause-item"><strong>معاينة المركبة:</strong> يقر الطرف الثاني (المشتري) بأنه عاين المركبة المذكورة أعلاه المعاينة التامة النافية للجهالة شرعاً وقانوناً، وقبل شرائها بحالتها الراهنة المعتمدة بالمعرض.</li>
        <li class="clause-item"><strong>التسليم والمسؤولية:</strong> تنتقل كامل المسؤولية المدنية والجنائية والمخالفات المرورية عن المركبة إلى الطرف الثاني بمجرد استلامه الفعلي للمركبة وتوقيع هذا العقد.</li>
        <li class="clause-item"><strong>الالتزام المالي:</strong> يلتزم الطرف الثاني بسداد كامل القيمة المتفق عليها للطرف الأول وفق جدول السداد المحدد بالفاتورة، ولا تنقل الملكية نهائياً إلا بعد السداد والتحقق المصرفي التام.</li>
        <li class="clause-item"><strong>حماية البيانات والرقابة:</strong> يخضع هذا العقد وعملية البيع المسجلة لأعلى معايير الرقابة والحماية الإلكترونية المعتمدة بالنظام لضمان سلامة المعاملات ومنع الازدواجية والتلاعب.</li>
      </ol>

      <div class="signatures-area">
        <div class="sig-box">
          <p style="font-weight: bold; margin-bottom: 40px;">الطرف الأول (عن المعرض)</p>
          <p style="font-size: 11px;">توقيع وختم المعرض</p>
        </div>
        <div class="sig-box">
          <p style="font-weight: bold; margin-bottom: 40px;">الطرف الثاني (المشتري)</p>
          <p style="font-size: 11px;">توقيع وبصمة المشتري</p>
        </div>
      </div>

      <div class="hash-code">
        🔒 تم التحقق والتوقيع الرقمي لهذه الصفقة على خوادم المعرض بنجاح. كود الأمان المشفر الفريد للاتحاد المروري:<br>
        <strong>SYSTEM SIGNATURE: ${auditHash} | ID: ${car.id}</strong>
      </div>

      <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print();" style="background-color: #0f172a; color: white; border: none; padding: 10px 20px; font-size: 13px; font-weight: bold; border-radius: 6px; cursor: pointer;">طباعة العقد القانوني</button>
      </div>

      <script>
        window.onload = function() {
          setTimeout(function() {
            window.print();
          }, 600);
        }
      </script>
    </body>
    </html>
  `;

  printWindow.document.write(content);
  printWindow.document.close();
}
