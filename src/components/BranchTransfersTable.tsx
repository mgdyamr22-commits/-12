/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { 
  ArrowLeftRight, 
  FileText, 
  Calendar, 
  User, 
  Plus, 
  Search, 
  Building, 
  CheckCircle2, 
  Printer, 
  Download, 
  X,
  AlertCircle
} from 'lucide-react';
import { Car, Branch, BranchTransfer } from '../types';
import { Language } from '../i18n/translations';

interface BranchTransfersTableProps {
  cars: Car[];
  branches: Branch[];
  token: string;
  lang: Language;
  triggerToast: (title: string, desc: string, type: 'success' | 'info' | 'warn') => void;
  fetchGlobalState: () => void;
}

export default function BranchTransfersTable({
  cars,
  branches,
  token,
  lang,
  triggerToast,
  fetchGlobalState
}: BranchTransfersTableProps) {
  const [transfers, setTransfers] = useState<BranchTransfer[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [showManualModal, setShowManualModal] = useState(false);
  const [selectedTransfer, setSelectedTransfer] = useState<BranchTransfer | null>(null);

  // Form states for manual transfer
  const [formCarId, setFormCarId] = useState('');
  const [formToBranchId, setFormToBranchId] = useState('');
  const [formNotes, setFormNotes] = useState('');

  const fetchTransfers = async () => {
    try {
      setLoading(true);
      const res = await fetch('/api/transfers', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        const data = await res.json();
        setTransfers(data);
      }
    } catch (err) {
      console.error(err);
      triggerToast('خطأ في تحميل البيانات', 'فشل تحميل سجل تحويلات الفروع.', 'warn');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTransfers();
  }, []);

  const handleManualTransferSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const carObj = cars.find(c => c.id === formCarId);
    if (!carObj) {
      triggerToast('خطأ في التحويل', 'يرجى اختيار سيارة صالحة للتحويل.', 'warn');
      return;
    }
    if (carObj.branchId === formToBranchId) {
      triggerToast('خطأ في التحويل', 'لا يمكن تحويل السيارة لنفس فرعها الحالي.', 'warn');
      return;
    }

    try {
      const res = await fetch('/api/transfers', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          carId: formCarId,
          fromBranchId: carObj.branchId,
          toBranchId: formToBranchId,
          notes: formNotes
        })
      });

      if (res.ok) {
        triggerToast('تم التحويل يدوياً', 'تم نقل السيارة المسجلة وإنشاء خطاب التحويل بنجاح.', 'success');
        setShowManualModal(false);
        setFormCarId('');
        setFormToBranchId('');
        setFormNotes('');
        fetchTransfers();
        fetchGlobalState();
      } else {
        const errData = await res.json();
        triggerToast('فشل التحويل', errData.error || 'حدث خطأ غير متوقع.', 'warn');
      }
    } catch (err) {
      console.error(err);
      triggerToast('خطأ في النظام', 'فشل إتمام عملية التحويل يدوياً.', 'warn');
    }
  };

  // Filter transfers based on search
  const filteredTransfers = transfers.filter(t => {
    const carObj = cars.find(c => c.id === t.carId);
    const fromBranch = branches.find(b => b.id === t.fromBranchId);
    const toBranch = branches.find(b => b.id === t.toBranchId);
    
    const carMatch = carObj ? `${carObj.make} ${carObj.model} ${carObj.plateNumber}`.toLowerCase() : '';
    const fromMatch = fromBranch ? fromBranch.name.toLowerCase() : '';
    const toMatch = toBranch ? toBranch.name.toLowerCase() : '';
    const noteMatch = t.notes ? t.notes.toLowerCase() : '';
    const letterMatch = t.letterNumber ? t.letterNumber.toLowerCase() : '';
    
    const query = searchQuery.toLowerCase();
    return carMatch.includes(query) || 
           fromMatch.includes(query) || 
           toMatch.includes(query) || 
           noteMatch.includes(query) ||
           letterMatch.includes(query);
  });

  // Print Transfer Letter function
  const handlePrintTransfer = (t: BranchTransfer) => {
    const carObj = cars.find(c => c.id === t.carId);
    const fromBranch = branches.find(b => b.id === t.fromBranchId);
    const toBranch = branches.find(b => b.id === t.toBranchId);

    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
      <html lang="ar" dir="rtl">
        <head>
          <meta charset="utf-8">
          <title>خطاب تحويل مركبة - ${t.letterNumber}</title>
          <style>
            body {
              font-family: 'system-ui', -apple-system, sans-serif;
              padding: 40px;
              color: #1e293b;
            }
            .header {
              text-align: center;
              border-bottom: 2px solid #e2e8f0;
              padding-bottom: 20px;
              margin-bottom: 30px;
            }
            .title {
              font-size: 24px;
              font-weight: bold;
              color: #4f46e5;
              margin-top: 10px;
            }
            .info-grid {
              display: grid;
              grid-template-columns: 1fr 1fr;
              gap: 20px;
              margin-bottom: 30px;
            }
            .info-box {
              border: 1px solid #e2e8f0;
              padding: 15px;
              border-radius: 8px;
              background: #f8fafc;
            }
            .info-title {
              font-weight: bold;
              font-size: 14px;
              color: #64748b;
              margin-bottom: 10px;
              border-bottom: 1px dashed #cbd5e1;
              padding-bottom: 5px;
            }
            .info-row {
              display: flex;
              justify-content: space-between;
              margin-bottom: 8px;
              font-size: 13px;
            }
            .notes {
              margin-top: 30px;
              border: 1px solid #e2e8f0;
              padding: 15px;
              border-radius: 8px;
              font-size: 13px;
              line-height: 1.6;
            }
            .signatures {
              margin-top: 60px;
              display: grid;
              grid-template-columns: 1fr 1fr 1fr;
              text-align: center;
              font-size: 13px;
              font-weight: bold;
            }
            .signature-box {
              padding-top: 40px;
              border-top: 1px dashed #cbd5e1;
              margin: 0 10px;
            }
            @media print {
              body { padding: 0; }
              .no-print { display: none; }
            }
          </style>
        </head>
        <body onload="window.print()">
          <div class="header">
            <h1 class="title">خطاب تحويل مركبة رسمي</h1>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">نظام المخزون برو المتكامل لإدارة المعارض والسيارات</p>
          </div>

          <div style="display: flex; justify-content: space-between; margin-bottom: 25px; font-size: 13px;">
            <div><strong>رقم الخطاب:</strong> ${t.letterNumber}</div>
            <div><strong>تاريخ التحويل:</strong> ${t.transferDate}</div>
          </div>

          <div class="info-grid">
            <div class="info-box">
              <div class="info-title">تفاصيل المركبة</div>
              <div class="info-row">
                <span>النوع والموديل:</span>
                <strong>${carObj ? `${carObj.make} ${carObj.model} (${carObj.year})` : 'ممسوحة'}</strong>
              </div>
              <div class="info-row">
                <span>رقم اللوحة:</span>
                <strong>${carObj?.plateNumber || '-'}</strong>
              </div>
              <div class="info-row">
                <span>رقم الهيكل (VIN):</span>
                <strong>${carObj?.vin || '-'}</strong>
              </div>
              <div class="info-row">
                <span>اللون الخارجي:</span>
                <strong>${carObj?.color || '-'}</strong>
              </div>
            </div>

            <div class="info-box">
              <div class="info-title">تفاصيل مسار النقل</div>
              <div class="info-row">
                <span>من معرض / فرع:</span>
                <strong style="color: #ef4444;">${fromBranch?.name || '-'}</strong>
              </div>
              <div class="info-row">
                <span>إلى معرض / فرع:</span>
                <strong style="color: #10b981;">${toBranch?.name || '-'}</strong>
              </div>
              <div class="info-row">
                <span>المنسق المسؤول:</span>
                <strong>${t.createdByUserName}</strong>
              </div>
            </div>
          </div>

          <div class="notes">
            <strong>ملاحظات وتوجيهات عملية النقل:</strong>
            <p style="margin-top: 8px;">${t.notes || 'لا يوجد ملاحظات إضافية مسجلة.'}</p>
          </div>

          <div class="signatures">
            <div class="signature-box">توقيع المستودع / المصدّر</div>
            <div class="signature-box">سائق وسيلة النقل</div>
            <div class="signature-box">توقيع فرع الاستلام</div>
          </div>
        </body>
      </html>
    `);
    printWindow.document.close();
  };

  // Available cars for transfer (only non-sold and non-reserved)
  const availableCarsToTransfer = cars.filter(c => c.status !== 'sold');

  return (
    <div className="space-y-6">
      
      {/* Overview Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 flex items-center justify-between shadow-lg">
          <div>
            <span className="text-[10px] text-slate-500 font-bold block">إجمالي عمليات النقل</span>
            <span className="text-xl font-sans font-black text-indigo-400 mt-1 block">{transfers.length}</span>
          </div>
          <div className="w-10 h-10 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center border border-indigo-500/10">
            <ArrowLeftRight className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 flex items-center justify-between shadow-lg">
          <div>
            <span className="text-[10px] text-slate-500 font-bold block">فروع نشطة مشمولة</span>
            <span className="text-xl font-sans font-black text-emerald-400 mt-1 block">{branches.length}</span>
          </div>
          <div className="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/10">
            <Building className="w-5 h-5" />
          </div>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-4 flex items-center justify-between shadow-lg">
          <div>
            <span className="text-[10px] text-slate-500 font-bold block">التحويلات هذا الشهر</span>
            <span className="text-xl font-sans font-black text-amber-400 mt-1 block">
              {transfers.filter(t => new Date(t.transferDate).getMonth() === new Date().getMonth()).length}
            </span>
          </div>
          <div className="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/10">
            <Calendar className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Main Content Card */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl shadow-xl overflow-hidden">
        
        {/* Card Header & Search */}
        <div className="p-4 bg-slate-950 border-b border-slate-800 flex flex-col sm:flex-row justify-between items-center gap-3">
          <div className="flex items-center gap-2">
            <div className="p-1.5 bg-indigo-600/10 border border-indigo-500/10 text-indigo-400 rounded-lg">
              <ArrowLeftRight className="w-4 h-4" />
            </div>
            <div>
              <h3 className="font-bold text-xs text-white">التحويلات بين المعارض والفروع</h3>
              <p className="text-[10px] text-slate-500 mt-0.5">سجل كامل بخطابات التحويل التلقائية واليدوية</p>
            </div>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            <div className="relative w-full sm:w-60">
              <input
                type="text"
                placeholder="بحث في التحويلات..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full text-[10px] text-white bg-slate-900 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-1.5 px-3 pl-8 text-right font-sans"
              />
              <Search className="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2" />
            </div>

            <button
              onClick={() => setShowManualModal(true)}
              className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold py-1.5 px-3 rounded shrink-0 transition cursor-pointer"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>تحويل يدوي</span>
            </button>
          </div>
        </div>

        {/* Transfers Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-right text-xs">
            <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
              <tr>
                <th className="p-3">رقم الخطاب</th>
                <th className="p-3">المركبة</th>
                <th className="p-3">رقم اللوحة</th>
                <th className="p-3">من فرع</th>
                <th className="p-3">إلى فرع</th>
                <th className="p-3">تاريخ التحويل</th>
                <th className="p-3">المسؤول</th>
                <th className="p-3 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-300">
              {filteredTransfers.map((t) => {
                const carObj = cars.find(c => c.id === t.carId);
                const fromBranch = branches.find(b => b.id === t.fromBranchId);
                const toBranch = branches.find(b => b.id === t.toBranchId);

                return (
                  <tr key={t.id} className="hover:bg-slate-850/30 transition-colors">
                    <td className="p-3 font-mono text-indigo-400 font-bold text-[10px]">{t.letterNumber || '-'}</td>
                    <td className="p-3">
                      <span className="font-bold text-white block">
                        {carObj ? `${carObj.make} ${carObj.model}` : 'مركبة ممسوحة'}
                      </span>
                      <span className="text-[9px] text-slate-500 font-mono block">
                        {carObj ? carObj.vin : '-'}
                      </span>
                    </td>
                    <td className="p-3">
                      <span className="font-mono bg-slate-950 py-0.5 px-1.5 border border-slate-800 rounded text-[10px] tracking-wider">
                        {carObj?.plateNumber || '-'}
                      </span>
                    </td>
                    <td className="p-3 text-rose-400 font-medium">{fromBranch?.name || 'مجهول'}</td>
                    <td className="p-3 text-emerald-400 font-medium">{toBranch?.name || 'مجهول'}</td>
                    <td className="p-3 font-mono text-slate-400">{t.transferDate}</td>
                    <td className="p-3 font-medium text-slate-200">{t.createdByUserName || 'النظام'}</td>
                    <td className="p-3 text-center">
                      <div className="flex justify-center gap-1.5">
                        <button
                          onClick={() => setSelectedTransfer(t)}
                          className="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-[9px] transition cursor-pointer"
                        >
                          عرض تفاصيل
                        </button>
                        <button
                          onClick={() => handlePrintTransfer(t)}
                          className="px-2 py-1 rounded bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white font-bold text-[9px] flex items-center gap-1 transition cursor-pointer"
                        >
                          <Printer className="w-3 h-3" />
                          <span>طباعة الخطاب</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}

              {filteredTransfers.length === 0 && (
                <tr>
                  <td colSpan={8} className="text-center py-12 text-slate-500 font-bold">
                    {loading ? 'جاري تحميل سجل عمليات التحويل...' : 'لا توجد عمليات تحويل مطابقة للبحث.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Manual Transfer Modal */}
      {showManualModal && (
        <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-50 flex items-center justify-center p-4 text-right">
          <div className="bg-slate-900 rounded-xl w-full max-w-md shadow-2xl border border-slate-800 overflow-hidden">
            <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
              <div>
                <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
                  <ArrowLeftRight className="w-4 h-4 text-indigo-400" />
                  <span>تحويل مركبة يدوياً بين الفروع</span>
                </h3>
                <p className="text-[10px] text-slate-500 mt-0.5">نقل فورى لسيارة مسجلة بالنظام من معرضها لآخر</p>
              </div>
              <button 
                onClick={() => setShowManualModal(false)} 
                className="p-1.5 rounded bg-slate-850 hover:bg-slate-800 text-slate-400 hover:text-white transition cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleManualTransferSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">السيارة المراد نقلها</label>
                <select
                  required
                  value={formCarId}
                  onChange={(e) => setFormCarId(e.target.value)}
                  className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 text-right"
                >
                  <option value="">-- اختر السيارة --</option>
                  {availableCarsToTransfer.map(c => {
                    const currentBranchName = branches.find(b => b.id === c.branchId)?.name || 'مجهول';
                    return (
                      <option key={c.id} value={c.id}>
                        {c.make} {c.model} ({c.year}) - لوحة: {c.plateNumber || '-'} [الموقع الحالي: {currentBranchName}]
                      </option>
                    );
                  })}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">المعرض المحول إليه (الوجهة)</label>
                <select
                  required
                  value={formToBranchId}
                  onChange={(e) => setFormToBranchId(e.target.value)}
                  className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 text-right"
                >
                  <option value="">-- اختر معرض الاستلام --</option>
                  {branches.map(b => (
                    <option key={b.id} value={b.id}>{b.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">ملاحظات وسبب التحويل</label>
                <textarea
                  value={formNotes}
                  onChange={(e) => setFormNotes(e.target.value)}
                  rows={3}
                  placeholder="مثال: نقل لمعرض جدة بطلب من المدير العام لتلبية رغبة عميل..."
                  className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 text-right"
                />
              </div>

              <div className="pt-2 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowManualModal(false)}
                  className="px-4 py-1.5 text-xs font-bold text-slate-400 bg-slate-850 hover:bg-slate-800 hover:text-white rounded transition cursor-pointer"
                >
                  إلغاء
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer"
                >
                  تأكيد النقل وإنشاء خطاب
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Transfer Detail Modal */}
      {selectedTransfer && (() => {
        const carObj = cars.find(c => c.id === selectedTransfer.carId);
        const fromBranch = branches.find(b => b.id === selectedTransfer.fromBranchId);
        const toBranch = branches.find(b => b.id === selectedTransfer.toBranchId);

        return (
          <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-50 flex items-center justify-center p-4 text-right">
            <div className="bg-slate-900 rounded-xl w-full max-w-md shadow-2xl border border-slate-800 overflow-hidden">
              <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                <div>
                  <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
                    <FileText className="w-4 h-4 text-indigo-400" />
                    <span>تفاصيل خطاب التحويل</span>
                  </h3>
                  <p className="text-[10px] text-slate-500 mt-0.5">{selectedTransfer.letterNumber}</p>
                </div>
                <button 
                  onClick={() => setSelectedTransfer(null)} 
                  className="p-1.5 rounded bg-slate-850 hover:bg-slate-800 text-slate-400 hover:text-white transition cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="p-5 space-y-4 text-xs">
                <div className="bg-slate-950/55 p-3 rounded-lg border border-slate-800 space-y-2">
                  <div className="flex justify-between">
                    <span className="text-slate-500 font-bold">المركبة المحولة:</span>
                    <span className="text-white font-bold">
                      {carObj ? `${carObj.make} ${carObj.model} (${carObj.year})` : 'ممسوحة'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500 font-bold">رقم اللوحة:</span>
                    <span className="text-white font-mono">{carObj?.plateNumber || '-'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500 font-bold">رقم الهيكل VIN:</span>
                    <span className="text-white font-mono">{carObj?.vin || '-'}</span>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="bg-slate-950/55 p-3 rounded-lg border border-slate-800">
                    <span className="text-slate-500 text-[10px] block font-bold">من معرض / فرع</span>
                    <span className="text-rose-400 text-xs font-bold block mt-1">{fromBranch?.name || '-'}</span>
                  </div>
                  <div className="bg-slate-950/55 p-3 rounded-lg border border-slate-800">
                    <span className="text-slate-500 text-[10px] block font-bold">إلى معرض / فرع</span>
                    <span className="text-emerald-400 text-xs font-bold block mt-1">{toBranch?.name || '-'}</span>
                  </div>
                </div>

                <div className="bg-slate-950/55 p-3 rounded-lg border border-slate-800 space-y-2">
                  <div className="flex justify-between">
                    <span className="text-slate-500 font-bold">تاريخ النقل:</span>
                    <span className="text-white font-mono">{selectedTransfer.transferDate}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500 font-bold">المسؤول عن النقل:</span>
                    <span className="text-white">{selectedTransfer.createdByUserName}</span>
                  </div>
                </div>

                <div className="bg-slate-950/55 p-3 rounded-lg border border-slate-800">
                  <span className="text-slate-500 text-[10px] block font-bold mb-1">ملاحظات التحويل والمستندات</span>
                  <p className="text-slate-300 leading-normal">{selectedTransfer.notes || 'لا يوجد ملاحظات مدونة لهذه العملية.'}</p>
                </div>

                <div className="pt-3 flex justify-end gap-2 border-t border-slate-800">
                  <button
                    onClick={() => handlePrintTransfer(selectedTransfer)}
                    className="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded flex items-center gap-1 transition cursor-pointer"
                  >
                    <Printer className="w-3.5 h-3.5" />
                    <span>طباعة خطاب التحويل الرسمي</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        );
      })()}

    </div>
  );
}
