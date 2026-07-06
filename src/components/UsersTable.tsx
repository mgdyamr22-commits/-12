/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { Users, Plus, Trash2, KeyRound, AlertCircle, CheckCircle, ShieldCheck } from 'lucide-react';
import { User, Branch } from '../types';

interface UsersTableProps {
  users: User[];
  branches: Branch[];
  onAddUser: (userData: any) => void;
  onDeleteUser: (userId: string) => void;
}

export default function UsersTable({ users, branches, onAddUser, onDeleteUser }: UsersTableProps) {
  const [username, setUsername] = useState('');
  const [name, setName] = useState('');
  const [role, setRole] = useState<'admin' | 'representative'>('representative');
  const [branchId, setBranchId] = useState(branches[0]?.id || '');
  const [password, setPassword] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    if (!username || !name || !role || !branchId || !password) {
      setErrorMsg('يرجى تعبئة جميع الحقول المطلوبة لإنشاء الحساب.');
      return;
    }

    onAddUser({
      username: username.toLowerCase().trim(),
      name,
      role,
      branchId,
      password,
      email: email.trim() || undefined,
      phone: phone.trim() || undefined
    });

    setSuccessMsg('تم تسجيل وحفظ حساب المستخدم الجديد بنجاح!');
    setUsername('');
    setName('');
    setPassword('');
    setEmail('');
    setPhone('');
    setShowAddForm(false);
  };

  return (
    <div className="space-y-4 text-right">
      
      {/* Header Panel */}
      <div className="flex justify-between items-center bg-slate-900 p-3.5 rounded-lg border border-slate-800">
        <div>
          <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
            <Users className="w-4 h-4 text-indigo-400" />
            <span>إدارة حسابات المستخدمين والمناديب</span>
          </h3>
          <p className="text-[10px] text-slate-500 mt-0.5">إنشاء الحسابات، ضبط الصلاحيات، وربط المناديب بالفروع والمعارض المعنية</p>
        </div>
        <button
          onClick={() => setShowAddForm(!showAddForm)}
          className="px-3.5 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition flex items-center gap-1 cursor-pointer shadow shadow-indigo-600/10"
        >
          <Plus className="w-4 h-4" />
          <span>إضافة مستخدم جديد</span>
        </button>
      </div>

      {successMsg && (
        <div className="p-2.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded flex items-center gap-2">
          <CheckCircle className="w-4 h-4 shrink-0" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Add User Form Drawer/Card */}
      {showAddForm && (
        <div className="bg-slate-900 p-4 rounded border border-slate-800 space-y-3">
          <h4 className="font-bold text-xs text-slate-300">نموذج تسجيل مستخدم / مندوب مبيعات جديد</h4>
          
          {errorMsg && (
            <div className="p-2.5 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-7 gap-3.5">
            
            {/* Name */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">الاسم الكامل *</label>
              <input
                type="text"
                required
                placeholder="مثال: يوسف الحربي"
                value={name}
                onChange={e => setName(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
              />
            </div>

            {/* Username */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">اسم المستخدم (Login) *</label>
              <input
                type="text"
                required
                placeholder="مثال: yasser"
                value={username}
                onChange={e => setUsername(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
              />
            </div>

            {/* Password */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">كلمة المرور المؤقتة *</label>
              <input
                type="password"
                required
                placeholder="حد أدنى 6 خانات"
                value={password}
                onChange={e => setPassword(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
              />
            </div>

            {/* Email */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">البريد الإلكتروني</label>
              <input
                type="email"
                placeholder="yousef@example.com"
                value={email}
                onChange={e => setEmail(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
              />
            </div>

            {/* Phone */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">رقم الهاتف</label>
              <input
                type="text"
                placeholder="05xxxxxxxx"
                value={phone}
                onChange={e => setPhone(e.target.value)}
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
              />
            </div>

            {/* Role Selector */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">الدور والصلاحية *</label>
              <select
                value={role}
                onChange={e => setRole(e.target.value as any)}
                className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="representative">مندوب مبيعات (Representative)</option>
                <option value="admin">مدير بنظام كامل (Administrator)</option>
              </select>
            </div>

            {/* Branch Selector */}
            <div>
              <label className="block text-xs font-bold text-slate-400 mb-1">المعرض / الفرع الرئيسي *</label>
              <select
                value={branchId}
                onChange={e => setBranchId(e.target.value)}
                className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                {branches.map(b => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>

            <div className="md:col-span-7 flex justify-end gap-2 mt-1">
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
                تأكيد وحفظ
              </button>
            </div>

          </form>
        </div>
      )}

      {/* Users Data List Table */}
      <div className="bg-slate-900 rounded border border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right text-xs">
            <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
              <tr>
                <th className="p-3">اسم الموظف</th>
                <th className="p-3">اسم المستخدم</th>
                <th className="p-3">الصلاحية الحالية</th>
                <th className="p-3">المعرض والفرع</th>
                <th className="p-3">تاريخ الانضمام</th>
                <th className="p-3 text-center">الإجراءات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-300">
              {users.map((u) => {
                const branch = branches.find(b => b.id === u.branchId)?.name || 'غير محدد';
                return (
                  <tr key={u.id} className="hover:bg-slate-800/10 transition-colors">
                    <td className="p-3">
                      <div className="font-bold text-white">{u.name}</div>
                      {(u.email || u.phone) && (
                        <div className="text-[10px] text-slate-500 flex gap-2 font-sans mt-0.5">
                          {u.email && <span>{u.email}</span>}
                          {u.phone && <span>{u.phone}</span>}
                        </div>
                      )}
                    </td>
                    <td className="p-3 font-mono font-medium text-slate-400">{u.username}</td>
                    <td className="p-3">
                      {u.role === 'admin' ? (
                        <span className="px-2 py-0.5 rounded text-[10px] bg-indigo-600/10 text-indigo-400 font-bold border border-indigo-500/20 flex items-center gap-1 w-fit">
                          <ShieldCheck className="w-3.5 h-3.5" />
                          <span>صلاحيات كاملة (مدير)</span>
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 rounded text-[10px] bg-slate-950 text-slate-400 font-bold border border-slate-800 flex items-center gap-1 w-fit">
                          <KeyRound className="w-3.5 h-3.5" />
                          <span>مندوب مبيعات</span>
                        </span>
                      )}
                    </td>
                    <td className="p-3 font-medium">{branch}</td>
                    <td className="p-3 font-mono text-[10px] text-slate-500">
                      {new Date(u.createdAt).toLocaleDateString('ar-SA')}
                    </td>
                    <td className="p-3 text-center">
                      {u.id === 'u1' ? (
                        <span className="text-[10px] text-slate-500 font-medium">حساب محمي</span>
                      ) : (
                        <button
                          onClick={() => onDeleteUser(u.id)}
                          className="p-1.5 rounded text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
                          title="حذف الموظف"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}
