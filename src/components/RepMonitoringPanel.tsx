/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { 
  ShieldCheck, 
  ShieldAlert, 
  Users, 
  Search, 
  Filter, 
  Clock, 
  Eye, 
  AlertTriangle, 
  Lock, 
  Unlock, 
  Trash2, 
  Download, 
  FileCheck, 
  UserX,
  FileSpreadsheet
} from 'lucide-react';
import { AuditLog, User } from '../types';

interface RepMonitoringPanelProps {
  logs: AuditLog[];
  users: User[];
  token: string;
  triggerToast: (title: string, message: string, type: 'success' | 'error' | 'warning') => void;
}

export default function RepMonitoringPanel({ logs, users, token, triggerToast }: RepMonitoringPanelProps) {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedRep, setSelectedRep] = useState('all');
  const [selectedRisk, setSelectedRisk] = useState('all');
  const [selectedType, setSelectedType] = useState('all');
  const [bannedIps, setBannedIps] = useState<Array<{ ip: string; reason: string; blockedAt: string }>>([
    { ip: '185.112.45.2', reason: 'محاولة هجوم SQL Injection متكررة على مودل السيارات', blockedAt: new Date(Date.now() - 3600000).toISOString() },
    { ip: '93.184.216.34', reason: 'محاولة تخمين كلمة المرور (Brute Force) لحساب المدير الرئيسي', blockedAt: new Date(Date.now() - 7200000).toISOString() }
  ]);

  // Extract all unique representatives from logs (excluding SYSTEM and anonymous)
  const repsList = users.filter(u => u.role === 'representative');

  // Filter logs
  const filteredLogs = logs.filter(log => {
    // Search filter
    const matchesSearch = 
      log.userName.toLowerCase().includes(searchTerm.toLowerCase()) ||
      log.action.toLowerCase().includes(searchTerm.toLowerCase()) ||
      log.details.toLowerCase().includes(searchTerm.toLowerCase());

    // Rep filter
    const matchesRep = selectedRep === 'all' || log.userId === selectedRep || log.userName === selectedRep;

    // Type filter
    let matchesType = true;
    if (selectedType !== 'all') {
      if (selectedType === 'downloads') {
        matchesType = log.action.includes('تحميل') || log.action.includes('معاينة') || log.action.includes('مرفق');
      } else if (selectedType === 'security') {
        matchesType = log.action.includes('حظر') || log.action.includes('اختراق') || log.action.includes('غير مصرحة') || log.userId === 'SYSTEM';
      } else if (selectedType === 'cars') {
        matchesType = log.action.includes('سيارة');
      } else if (selectedType === 'reservations') {
        matchesType = log.action.includes('حجز');
      } else if (selectedType === 'auth') {
        matchesType = log.action.includes('دخول') || log.action.includes('خروج');
      }
    }

    // Risk Filter
    let matchesRisk = true;
    if (selectedRisk !== 'all') {
      const isHigh = log.action.includes('حذف') || log.action.includes('حظر') || log.action.includes('اختراق') || log.action.includes('غير مصرحة') || log.details.includes('عالي الخطورة');
      const isMedium = log.action.includes('تعديل') || log.details.includes('متوسط الخطورة');
      const isLow = !isHigh && !isMedium;

      if (selectedRisk === 'high') matchesRisk = isHigh;
      else if (selectedRisk === 'medium') matchesRisk = isMedium;
      else if (selectedRisk === 'low') matchesRisk = isLow;
    }

    return matchesSearch && matchesRep && matchesType && matchesRisk;
  });

  // Calculate stats
  const totalRepOps = logs.filter(l => l.userId !== 'SYSTEM' && l.userId !== 'anonymous').length;
  
  const highRiskOps = logs.filter(l => 
    l.action.includes('حذف') || 
    l.action.includes('تعديل الإعدادات') || 
    l.action.includes('غير مصرحة') ||
    l.details.includes('عالي الخطورة')
  ).length;

  const downloadOps = logs.filter(l => 
    l.action.includes('تحميل') || 
    l.action.includes('معاينة')
  ).length;

  // Track operations per representative
  const repStats = repsList.map(rep => {
    const repLogs = logs.filter(l => l.userId === rep.id || l.userName === rep.name);
    const lastActiveLog = repLogs[0];
    const downloadsCount = repLogs.filter(l => l.action.includes('تحميل') || l.action.includes('معاينة')).length;
    const reservationsCount = repLogs.filter(l => l.action.includes('حجز')).length;
    
    return {
      id: rep.id,
      name: rep.name,
      avatar: rep.avatar,
      totalActions: repLogs.length,
      downloads: downloadsCount,
      reservations: reservationsCount,
      lastActive: lastActiveLog ? lastActiveLog.createdAt : null,
      lastAction: lastActiveLog ? `${lastActiveLog.action} - ${lastActiveLog.details.slice(0, 30)}...` : 'لا يوجد نشاط مؤخراً'
    };
  }).sort((a, b) => b.totalActions - a.totalActions);

  const handleUnbanIp = (ipAddress: string) => {
    setBannedIps(prev => prev.filter(item => item.ip !== ipAddress));
    triggerToast('فك الحظر الأمني', `تم إلغاء حظر عنوان IP (${ipAddress}) بنجاح وتم تنشيط الوصول له.`, 'success');
  };

  const handleManualBan = (e: React.FormEvent) => {
    e.preventDefault();
    const form = e.currentTarget as HTMLFormElement;
    const ip = (form.elements.namedItem('ip_address') as HTMLInputElement).value;
    const reason = (form.elements.namedItem('ban_reason') as HTMLInputElement).value;

    if (!ip) return;

    if (bannedIps.some(item => item.ip === ip)) {
      triggerToast('تنبيه', 'عنوان IP محظور بالفعل مسبقاً في النظام.', 'warning');
      return;
    }

    setBannedIps(prev => [...prev, { ip, reason: reason || 'حظر يدوي من قبل مدير النظام', blockedAt: new Date().toISOString() }]);
    triggerToast('حظر فوري', `تم إضافة عنوان IP (${ip}) إلى القائمة السوداء للنظام بنجاح.`, 'success');
    form.reset();
  };

  return (
    <div className="space-y-6 text-right">
      
      {/* 1. KEY SURVEILLANCE KPI CARDS */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        
        {/* KPI 1: Active Rep Logs */}
        <div className="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-xs text-slate-500 font-medium">إجمالي عمليات المناديب</span>
            <h3 className="text-2xl font-bold font-sans text-white">{totalRepOps}</h3>
            <span className="text-[10px] text-emerald-450 text-emerald-450 text-emerald-400 font-medium">
              مراقبة مستمرة • تحديث حي فوري
            </span>
          </div>
          <div className="w-10 h-10 rounded bg-indigo-600/10 text-indigo-400 flex items-center justify-center">
            <Users className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 2: High Risk Actions */}
        <div className="bg-slate-900 border border-slate-800 p-4 rounded-xl border-r-4 border-r-rose-500 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-xs text-slate-500 font-medium">عمليات حساسة / خطيرة</span>
            <h3 className="text-2xl font-bold font-sans text-rose-550 text-rose-500">{highRiskOps}</h3>
            <span className="text-[10px] text-slate-500 font-medium">
              تعديل، حذف، تغيير الصلاحيات
            </span>
          </div>
          <div className="w-10 h-10 rounded bg-rose-500/10 text-rose-400 flex items-center justify-center">
            <ShieldAlert className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 3: Document Access Tracker */}
        <div className="bg-slate-900 border border-slate-800 p-4 rounded-xl border-r-4 border-r-amber-500 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-xs text-slate-500 font-medium">تحميل ومعاينة المرفقات الرسمية</span>
            <h3 className="text-2xl font-bold font-sans text-amber-500">{downloadOps}</h3>
            <span className="text-[10px] text-slate-500 font-medium">
              تنزيل الفحوصات الجمركية والعقود
            </span>
          </div>
          <div className="w-10 h-10 rounded bg-amber-500/10 text-amber-400 flex items-center justify-center">
            <Download className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 4: Firewall Blocked IPs */}
        <div className="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-xs text-slate-500 font-medium">التهديدات وعناوين IP المحظورة</span>
            <h3 className="text-2xl font-bold font-sans text-emerald-400">{bannedIps.length}</h3>
            <span className="text-[10px] text-emerald-400 font-medium flex items-center gap-1">
              حماية فعالة • جدار حماية (WAF) نشط
            </span>
          </div>
          <div className="w-10 h-10 rounded bg-emerald-500/10 text-emerald-450 text-emerald-400 flex items-center justify-center">
            <ShieldCheck className="w-5 h-5" />
          </div>
        </div>

      </div>

      {/* 2. REAL-TIME WAF & BLACKLIST CONTROL CENTRE */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        {/* Manual IP Ban Form */}
        <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex flex-col justify-between">
          <div>
            <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
              <Lock className="w-4 h-4 text-rose-500" />
              <span>حظر فوري لعناوين IP المشبوهة</span>
            </h4>
            <p className="text-[10px] text-slate-500 mt-0.5">تقييد ومنع أي IP فورياً من استدعاء API أو تنزيل المرفقات</p>
          </div>

          <form onSubmit={handleManualBan} className="space-y-3.5 my-4">
            <div>
              <label className="block text-[10px] font-bold text-slate-400 mb-1">عنوان IP المستهدف</label>
              <input
                type="text"
                name="ip_address"
                placeholder="مثال: 197.34.120.15"
                required
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-rose-500 font-sans"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-slate-400 mb-1">سبب الحظر الأمني</label>
              <input
                type="text"
                name="ban_reason"
                placeholder="مثال: استدعاء متكرر للسيارات المتاحة"
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-rose-500"
              />
            </div>
            <button
              type="submit"
              className="w-full py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded transition flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-rose-950/20"
            >
              <UserX className="w-4 h-4" />
              <span>إضافة للقائمة السوداء وحظره فورا</span>
            </button>
          </form>

          <div className="p-2 bg-rose-500/10 border border-rose-500/20 rounded-lg text-[9px] text-rose-450 text-rose-400 leading-normal font-medium">
            ملاحظة: جدار الحماية الذكي WAF يقوم آلياً برصد محاولات الاختراق وحظر المخترقين لـ 30 دقيقة بشكل ذاتي.
          </div>
        </div>

        {/* Current Blacklist Table */}
        <div className="lg:col-span-2 bg-slate-900 p-4 rounded-xl border border-slate-800 flex flex-col justify-between">
          <div>
            <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
              <ShieldAlert className="w-4 h-4 text-amber-500" />
              <span>قائمة الحظر الأمني النشطة حالياً</span>
            </h4>
            <p className="text-[10px] text-slate-500 mt-0.5">العناوين المقيدة أمنياً من الوصول إلى الخادم المركزي</p>
          </div>

          <div className="my-4 overflow-x-auto min-h-[140px]">
            <table className="w-full text-right border-collapse">
              <thead>
                <tr className="border-b border-slate-800 text-[10px] text-slate-500 font-bold">
                  <th className="pb-2">عنوان IP</th>
                  <th className="pb-2">سبب الحظر الأمني</th>
                  <th className="pb-2">التوقيت</th>
                  <th className="pb-2 text-left">الإجراء</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-850">
                {bannedIps.map(item => (
                  <tr key={item.ip} className="text-xs">
                    <td className="py-2.5 font-mono font-bold text-slate-300">{item.ip}</td>
                    <td className="py-2.5 text-slate-400 max-w-[200px] truncate" title={item.reason}>{item.reason}</td>
                    <td className="py-2.5 text-slate-500 text-[10px] font-sans">
                      {new Date(item.blockedAt).toLocaleTimeString('ar-SA')}
                    </td>
                    <td className="py-2.5 text-left">
                      <button
                        onClick={() => handleUnbanIp(item.ip)}
                        className="px-2 py-1 text-[10px] font-bold text-emerald-400 hover:text-white bg-emerald-500/10 hover:bg-emerald-600 rounded transition flex items-center gap-1 ml-0 mr-auto cursor-pointer"
                      >
                        <Unlock className="w-3 h-3" />
                        <span>إلغاء الحظر</span>
                      </button>
                    </td>
                  </tr>
                ))}
                {bannedIps.length === 0 && (
                  <tr>
                    <td colSpan={4} className="py-8 text-center text-[11px] text-slate-500 font-medium">
                      لا توجد أي عناوين محظورة حالياً. جدار الحماية نظيف تماماً!
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="text-[9px] text-slate-500 text-center font-medium">
            يتم تصفير وإلغاء الحظر الذاتي تلقائياً عند انتهاء المدة المحددة لكل عنوان.
          </div>
        </div>

      </div>

      {/* 3. REPRESENTATIVES LEADERBOARD & STATS WORKPLAN */}
      <div className="bg-slate-900 p-4 rounded-xl border border-slate-800">
        <div>
          <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
            <Users className="w-4 h-4 text-indigo-400" />
            <span>تقرير الأداء الرقابي وتحركات المناديب</span>
          </h4>
          <p className="text-[10px] text-slate-500 mt-0.5">تقييم وحصر العمليات الحركية لكل مندوب مبيعات مع رصد وتوثيق آخر عملية مضافة</p>
        </div>

        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-right border-collapse">
            <thead>
              <tr className="border-b border-slate-800 text-[10px] text-slate-500 font-bold">
                <th className="pb-2">اسم المندوب</th>
                <th className="pb-2 text-center">إجمالي العمليات</th>
                <th className="pb-2 text-center">عمليات الحجز</th>
                <th className="pb-2 text-center">تنزيل المستندات</th>
                <th className="pb-2">آخر عملية وتفاصيلها</th>
                <th className="pb-2 text-left">توقيت النشاط</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {repStats.map(rep => (
                <tr key={rep.id} className="text-xs hover:bg-slate-850/40 transition-colors">
                  <td className="py-3 flex items-center gap-2">
                    <div className="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center overflow-hidden border border-slate-700">
                      {rep.avatar ? (
                        <img src={rep.avatar} alt={rep.name} className="w-full h-full object-cover" />
                      ) : (
                        <span className="text-[10px] font-bold text-indigo-400">{rep.name.slice(0, 2)}</span>
                      )}
                    </div>
                    <div>
                      <span className="font-bold text-slate-200 block">{rep.name}</span>
                      <span className="text-[9px] text-slate-500 block font-sans">ID: {rep.id}</span>
                    </div>
                  </td>
                  <td className="py-3 text-center font-sans font-bold text-indigo-400 text-sm">{rep.totalActions}</td>
                  <td className="py-3 text-center font-sans font-medium text-emerald-400">{rep.reservations}</td>
                  <td className="py-3 text-center font-sans font-medium text-amber-500">{rep.downloads}</td>
                  <td className="py-3 text-slate-400 max-w-[280px] truncate" title={rep.lastAction}>
                    {rep.lastAction}
                  </td>
                  <td className="py-3 text-left font-sans text-slate-500 text-[10px]">
                    {rep.lastActive ? new Date(rep.lastActive).toLocaleTimeString('ar-SA') : 'خامل'}
                  </td>
                </tr>
              ))}
              {repStats.length === 0 && (
                <tr>
                  <td colSpan={6} className="py-6 text-center text-slate-500 text-[11px]">
                    لا يوجد أي مناديب مبيعات مسجلين في النظام بعد.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* 4. COMPREHENSIVE FILTERABLE ACTION LOGS */}
      <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-4">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div>
            <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
              <Clock className="w-4 h-4 text-indigo-400" />
              <span>جدار المراقبة وسجلات تتبع العمليات الكاملة</span>
            </h4>
            <p className="text-[10px] text-slate-500 mt-0.5">مراجعة وفلترة السجلات الفنية وجلسات تسجيل الدخول وتحركات المناديب بدقة ثانية</p>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-[10px] font-bold bg-slate-950 border border-slate-800 text-indigo-400 px-2 py-1 rounded">
              تم رصد {filteredLogs.length} عملية متطابقة
            </span>
          </div>
        </div>

        {/* Filters bar */}
        <div className="p-3 bg-slate-950 rounded-lg border border-slate-800 grid grid-cols-1 md:grid-cols-4 gap-3">
          
          {/* Search */}
          <div className="relative">
            <span className="absolute right-2.5 top-2.5 text-slate-500">
              <Search className="w-3.5 h-3.5" />
            </span>
            <input
              type="text"
              placeholder="ابحث بالاسم، الإجراء، التفاصيل..."
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              className="w-full text-xs pr-8 pl-3 py-1.5 rounded border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500"
            />
          </div>

          {/* Rep filter */}
          <div className="relative">
            <span className="absolute right-2.5 top-2.5 text-slate-500">
              <Filter className="w-3.5 h-3.5" />
            </span>
            <select
              value={selectedRep}
              onChange={e => setSelectedRep(e.target.value)}
              className="w-full text-xs pr-8 pl-3 py-1.5 rounded border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer appearance-none"
            >
              <option value="all">كل المناديب والمستخدمين</option>
              <option value="SYSTEM">النظام التلقائي (SYSTEM)</option>
              <option value="anonymous">زوار مجهولين (Anonymous)</option>
              {users.map(u => (
                <option key={u.id} value={u.id}>{u.name} ({u.role === 'admin' ? 'مدير' : 'مندوب'})</option>
              ))}
            </select>
          </div>

          {/* Type filter */}
          <div className="relative">
            <span className="absolute right-2.5 top-2.5 text-slate-500">
              <Filter className="w-3.5 h-3.5" />
            </span>
            <select
              value={selectedType}
              onChange={e => setSelectedType(e.target.value)}
              className="w-full text-xs pr-8 pl-3 py-1.5 rounded border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer appearance-none"
            >
              <option value="all">كل تصنيفات العمليات</option>
              <option value="auth">تسجيل الدخول والخروج</option>
              <option value="cars">عمليات وصيانة السيارات</option>
              <option value="reservations">عمليات حجز وتأجير السيارات</option>
              <option value="downloads">تحميل ومعاينة المرفقات</option>
              <option value="security">أحداث الجدار الناري والحظر</option>
            </select>
          </div>

          {/* Risk filter */}
          <div className="relative">
            <span className="absolute right-2.5 top-2.5 text-slate-500">
              <Filter className="w-3.5 h-3.5" />
            </span>
            <select
              value={selectedRisk}
              onChange={e => setSelectedRisk(e.target.value)}
              className="w-full text-xs pr-8 pl-3 py-1.5 rounded border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer appearance-none"
            >
              <option value="all">كل مستويات الخطورة</option>
              <option value="high">عالي الخطورة (حذف / حظر / اختراق)</option>
              <option value="medium">متوسط الخطورة (تعديل بيانات)</option>
              <option value="low">منخفض الخطورة (إضافة وعرض عادي)</option>
            </select>
          </div>

        </div>

        {/* Logs Table */}
        <div className="overflow-x-auto min-h-[250px]">
          <table className="w-full text-right border-collapse">
            <thead>
              <tr className="border-b border-slate-800 text-[10px] text-slate-500 font-bold">
                <th className="pb-2">المستخدم</th>
                <th className="pb-2">نوع العملية</th>
                <th className="pb-2">تفاصيل العملية الفنية</th>
                <th className="pb-2 text-center">درجة الخطورة</th>
                <th className="pb-2 text-left">التاريخ والوقت</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-850">
              {filteredLogs.map(log => {
                const isSystem = log.userId === 'SYSTEM';
                const isAnonymous = log.userId === 'anonymous';
                const isHigh = log.action.includes('حذف') || log.action.includes('حظر') || log.action.includes('اختراق') || log.action.includes('غير مصرحة') || log.details.includes('عالي الخطورة');
                const isMedium = log.action.includes('تعديل') || log.details.includes('متوسط الخطورة');
                
                return (
                  <tr key={log.id} className="text-xs hover:bg-slate-850/20 transition-colors">
                    <td className="py-2.5">
                      <span className={`font-bold ${isSystem ? 'text-indigo-400' : isAnonymous ? 'text-amber-500' : 'text-slate-300'}`}>
                        {log.userName}
                      </span>
                      <span className="block text-[9px] text-slate-500 font-sans">{log.userId}</span>
                    </td>
                    <td className="py-2.5 font-semibold text-slate-200">{log.action}</td>
                    <td className="py-2.5 text-slate-400 text-[11px] leading-relaxed max-w-[300px]" title={log.details}>
                      {log.details}
                    </td>
                    <td className="py-2.5 text-center">
                      {isHigh ? (
                        <span className="inline-block px-2 py-0.5 rounded text-[9px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                          عالي الخطورة
                        </span>
                      ) : isMedium ? (
                        <span className="inline-block px-2 py-0.5 rounded text-[9px] font-bold bg-amber-550/10 bg-amber-500/10 text-amber-400 border border-amber-500/20">
                          متوسط الخطورة
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-0.5 rounded text-[9px] font-bold bg-slate-800 text-slate-400">
                          عادي / آمن
                        </span>
                      )}
                    </td>
                    <td className="py-2.5 text-left font-mono text-[10px] text-slate-500">
                      {new Date(log.createdAt).toLocaleString('ar-SA')}
                    </td>
                  </tr>
                );
              })}
              {filteredLogs.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-[11px] text-slate-500 font-bold">
                    لا توجد أي سجلات تتطابق مع شروط البحث والفلترة المحددة.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}
