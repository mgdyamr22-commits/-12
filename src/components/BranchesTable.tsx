/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { MapPin, Plus, Trash2, AlertCircle, CheckCircle } from 'lucide-react';
import { Branch } from '../types';

interface BranchesTableProps {
  branches: Branch[];
  onAddBranch: (branchData: any) => void;
  onDeleteBranch: (branchId: string) => void;
}

export default function BranchesTable({ branches, onAddBranch, onDeleteBranch }: BranchesTableProps) {
  const [name, setName] = useState('');
  const [location, setLocation] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    if (!name || !location) {
      setErrorMsg('يرجى كتابة اسم المعرض وموقعه الجغرافي بالكامل.');
      return;
    }

    onAddBranch({ name, location });
    setSuccessMsg('تم إضافة وتسجيل معرض السيارات الجديد بنجاح!');
    setName('');
    setLocation('');
    setShowAddForm(false);
  };

  return (
    <div className="space-y-4 text-right">
      
      {/* Header Panel */}
      <div className="flex justify-between items-center bg-slate-900 p-3.5 rounded-lg border border-slate-800">
        <div>
          <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
            <MapPin className="w-4 h-4 text-indigo-400" />
            <span>تنظيم المعارض والفروع النشطة</span>
          </h3>
          <p className="text-[10px] text-slate-500 mt-0.5">تحديد الفروع الجغرافية المسؤولة عن تسليم وتخزين وإيجار المركبات المودعة</p>
        </div>
        <button
          onClick={() => setShowAddForm(!showAddForm)}
          className="px-3.5 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition flex items-center gap-1 cursor-pointer shadow shadow-indigo-600/10"
        >
          <Plus className="w-4 h-4" />
          <span>إضافة فرع / معرض</span>
        </button>
      </div>

      {successMsg && (
        <div className="p-2.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded flex items-center gap-2">
          <CheckCircle className="w-4 h-4 shrink-0" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Add Branch form */}
      {showAddForm && (
        <div className="bg-slate-900 p-4 rounded border border-slate-800 space-y-3">
          <h4 className="font-bold text-xs text-slate-300">تسجيل بيانات المعرض المعتمد الجديد</h4>
          
          {errorMsg && (
            <div className="p-2.5 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-3.5">
            
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">اسم المعرض الفرعي *</label>
              <input
                type="text"
                required
                placeholder="مثال: معرض الرياض - حي الشفا"
                value={name}
                onChange={e => setName(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="md:col-span-2">
              <label className="block text-xs font-bold text-slate-400 mb-1">الموقع والعنوان الجغرافي التفصيلي *</label>
              <input
                type="text"
                required
                placeholder="مثال: الرياض، حي الشفا، طريق المعارض العام"
                value={location}
                onChange={e => setLocation(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div className="md:col-span-3 flex justify-end gap-2 mt-1">
              <button
                type="button"
                onClick={() => setShowAddForm(false)}
                className="px-3.5 py-1.5 text-xs font-bold text-slate-400 bg-slate-950 hover:bg-slate-850 border border-slate-800 rounded transition cursor-pointer"
              >
                إلغاء
              </button>
              <button
                type="submit"
                className="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer shadow shadow-indigo-600/10"
              >
                حفظ وإيداع المعرض
              </button>
            </div>

          </form>
        </div>
      )}

      {/* Branches List Table */}
      <div className="bg-slate-900 rounded border border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right text-xs">
            <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
              <tr>
                <th className="p-3">كود المعرض</th>
                <th className="p-3">اسم المعرض / الصالة المعنية</th>
                <th className="p-3">العنوان والموقع التفصيلي</th>
                <th className="p-3 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-350">
              {branches.map((b) => (
                <tr key={b.id} className="hover:bg-slate-800/10 transition-colors">
                  <td className="p-3 font-mono text-slate-500 font-bold">#{b.id.toUpperCase().slice(0, 5)}</td>
                  <td className="p-3 font-bold text-white">{b.name}</td>
                  <td className="p-3 font-medium text-slate-300">{b.location}</td>
                  <td className="p-3 text-center">
                    {branches.length <= 1 ? (
                      <span className="text-[10px] text-slate-500 font-medium">فرع أساسي محمي</span>
                    ) : (
                      <button
                        onClick={() => onDeleteBranch(b.id)}
                        className="p-1.5 rounded text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
                        title="حذف الفرع"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}
