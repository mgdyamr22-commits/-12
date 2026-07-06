/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { X, User, Phone, Clock, FileText, DollarSign, Calendar } from 'lucide-react';
import { Reservation } from '../types';

interface EditReservationModalProps {
  reservation: Reservation;
  onClose: () => void;
  onSave: (resId: string, data: any) => void;
}

export function EditReservationModal({ reservation, onClose, onSave }: EditReservationModalProps) {
  const [customerName, setCustomerName] = useState(reservation.customerName);
  const [customerPhone, setCustomerPhone] = useState(reservation.customerPhone);
  const [duration, setDuration] = useState(reservation.duration);
  const [reason, setReason] = useState(reservation.reason || '');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSave(reservation.id, {
      customerName,
      customerPhone,
      duration: parseInt(String(duration)),
      reason
    });
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-50 flex items-center justify-center p-4 text-right" id="edit-reservation-modal">
      <div className="bg-slate-900 rounded-xl w-full max-w-md shadow-2xl border border-slate-800 overflow-hidden">
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
              <FileText className="w-4 h-4 text-amber-400" />
              <span>تعديل بيانات الحجز</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">تحديث معلومات العميل والتنسيق للمركبة</p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-850 hover:bg-slate-800 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">اسم العميل</label>
            <div className="relative">
              <input
                type="text"
                required
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 px-3 pl-8 text-right font-sans"
              />
              <User className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">رقم الهاتف</label>
            <div className="relative">
              <input
                type="text"
                required
                value={customerPhone}
                onChange={(e) => setCustomerPhone(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 px-3 pl-8 text-left font-mono"
                dir="ltr"
              />
              <Phone className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">مدة الحجز (أيام)</label>
            <div className="relative">
              <input
                type="number"
                min="1"
                required
                value={duration}
                onChange={(e) => setDuration(parseInt(e.target.value) || 1)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 px-3 pl-8 text-right font-sans"
              />
              <Clock className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">سبب الغرض والملاحظات</label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={3}
              className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded p-2 px-3 text-right"
            />
          </div>

          <div className="pt-2 flex justify-end gap-2 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-1.5 text-xs font-bold text-slate-400 bg-slate-850 hover:bg-slate-800 hover:text-white rounded transition cursor-pointer"
            >
              إلغاء
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded transition cursor-pointer"
            >
              حفظ التعديلات
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

interface SellReservationModalProps {
  reservation: Reservation;
  onClose: () => void;
  onConfirm: (resId: string, carId: string, saleData: any) => void;
}

export function SellReservationModal({ reservation, onClose, onConfirm }: SellReservationModalProps) {
  const [saleAmount, setSaleAmount] = useState('');
  const [customerName, setCustomerName] = useState(reservation.customerName);
  const [customerPhone, setCustomerPhone] = useState(reservation.customerPhone);
  const [exitDate, setExitDate] = useState(new Date().toISOString().split('T')[0]);
  const [exitNotes, setExitNotes] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onConfirm(reservation.id, reservation.carId, {
      saleAmount: parseFloat(saleAmount),
      customerName,
      customerPhone,
      exitDate,
      exitNotes
    });
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-50 flex items-center justify-center p-4 text-right" id="sell-reservation-modal">
      <div className="bg-slate-900 rounded-xl w-full max-w-md shadow-2xl border border-slate-800 overflow-hidden">
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
              <DollarSign className="w-4 h-4 text-emerald-400" />
              <span>تسجيل عملية البيع وتحرير المركبة</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">ترحيل السيارة المحجوزة تلقائياً لقسم المبيعات</p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-850 hover:bg-slate-800 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">مبلغ البيع الفعلي (SAR)</label>
            <div className="relative">
              <input
                type="number"
                required
                min="1"
                placeholder="0.00"
                value={saleAmount}
                onChange={(e) => setSaleAmount(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded p-2 px-3 pl-8 text-right font-sans"
              />
              <DollarSign className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">اسم المشتري النهائي</label>
            <div className="relative">
              <input
                type="text"
                required
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded p-2 px-3 pl-8 text-right font-sans"
              />
              <User className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">رقم الهاتف للعميل</label>
            <div className="relative">
              <input
                type="text"
                required
                value={customerPhone}
                onChange={(e) => setCustomerPhone(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded p-2 px-3 pl-8 text-left font-mono"
                dir="ltr"
              />
              <Phone className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">تاريخ المبيعة</label>
            <div className="relative">
              <input
                type="date"
                required
                value={exitDate}
                onChange={(e) => setExitDate(e.target.value)}
                className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded p-2 px-3 pl-8 text-right font-sans"
              />
              <Calendar className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-2.5" />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-bold text-slate-400 mb-1">تفاصيل وملاحظات الخروج</label>
            <textarea
              value={exitNotes}
              onChange={(e) => setExitNotes(e.target.value)}
              rows={3}
              placeholder="مثال: تم سداد العربون مسبقاً وتكملة الشراء نقدياً..."
              className="w-full text-xs text-white bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded p-2 px-3 text-right"
            />
          </div>

          <div className="pt-2 flex justify-end gap-2 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-1.5 text-xs font-bold text-slate-400 bg-slate-850 hover:bg-slate-800 hover:text-white rounded transition cursor-pointer"
            >
              إلغاء
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded transition cursor-pointer"
            >
              تأكيد البيع والترحيل
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
