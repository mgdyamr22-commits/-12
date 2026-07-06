/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import {
  Terminal,
  Activity,
  AlertTriangle,
  CheckCircle,
  Database,
  FolderLock,
  RefreshCw,
  Trash2,
  UserCheck,
  Wrench,
  ShieldAlert,
  Search,
  Eye
} from 'lucide-react';

interface TroubleshootPanelProps {
  token: string;
  lang: 'ar' | 'en';
}

interface DiagnosticItem {
  key: string;
  title: string;
  status: 'ok' | 'warning' | 'error' | 'loading';
  message: string;
  details?: string;
}

export default function TroubleshootPanel({ token, lang }: TroubleshootPanelProps) {
  const [diagnostics, setDiagnostics] = useState<DiagnosticItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [repairing, setRepairing] = useState<string | null>(null);
  const [actionStatus, setActionStatus] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
  
  // Log viewer state
  const [selectedLogType, setSelectedLogType] = useState<'waf' | 'fatal' | 'warnings'>('waf');
  const [logContent, setLogContent] = useState<string>('');
  const [loadingLog, setLoadingLog] = useState(false);

  useEffect(() => {
    runDiagnostics();
  }, []);

  const runDiagnostics = async () => {
    setLoading(true);
    setActionStatus(null);
    try {
      const response = await fetch('/api/troubleshoot/diagnose', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (response.ok) {
        const data = await response.json();
        setDiagnostics(data.checklist);
      } else {
        throw new Error('Failed to run diagnostics');
      }
    } catch (err: any) {
      setDiagnostics([
        {
          key: 'db_connection',
          title: lang === 'ar' ? 'الاتصال بقاعدة البيانات' : 'Database Connection',
          status: 'error',
          message: lang === 'ar' ? 'فشل الاتصال بمركز البيانات المحلي.' : 'Failed to connect to local database coordinator.'
        }
      ]);
    } finally {
      setLoading(false);
    }
  };

  const triggerRepair = async (action: string) => {
    setRepairing(action);
    setActionStatus(null);
    try {
      const response = await fetch('/api/troubleshoot/repair', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ action })
      });
      
      const result = await response.json();
      if (response.ok) {
        setActionStatus({
          type: 'success',
          message: result.message || (lang === 'ar' ? 'تمت العملية بنجاح!' : 'Repair completed successfully!')
        });
        runDiagnostics(); // Refresh states
      } else {
        setActionStatus({
          type: 'error',
          message: result.error || (lang === 'ar' ? 'حدث خطأ أثناء محاولة الإصلاح.' : 'An error occurred during repair.')
        });
      }
    } catch (err: any) {
      setActionStatus({
        type: 'error',
        message: lang === 'ar' ? 'خطأ في الاتصال بالخادم.' : 'Server connection error.'
      });
    } finally {
      setRepairing(null);
    }
  };

  const fetchLogs = async (type: 'waf' | 'fatal' | 'warnings') => {
    setLoadingLog(true);
    setLogContent('');
    try {
      const response = await fetch(`/api/troubleshoot/logs?type=${type}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (response.ok) {
        const data = await response.json();
        setLogContent(data.content || (lang === 'ar' ? 'سجل فارغ أو لا يوجد أحداث مسجلة.' : 'Log is empty or no events registered.'));
      } else {
        setLogContent(lang === 'ar' ? 'تعذر قراءة ملف السجلات من السيرفر.' : 'Unable to read log file from server.');
      }
    } catch (err) {
      setLogContent(lang === 'ar' ? 'خطأ في الاتصال بالسيرفر لقراءة السجلات.' : 'Connection error reading logs.');
    } finally {
      setLoadingLog(false);
    }
  };

  const clearLogFile = async (type: 'waf' | 'fatal' | 'warnings') => {
    if (!window.confirm(lang === 'ar' ? 'هل أنت متأكد من مسح وتصفير ملف السجلات هذا؟ لا يمكن التراجع.' : 'Are you sure you want to clear this log file? This cannot be undone.')) return;
    setLoadingLog(true);
    try {
      const response = await fetch(`/api/troubleshoot/logs/clear`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ type })
      });
      if (response.ok) {
        setLogContent(lang === 'ar' ? 'تم تصفير ومسح السجل بنجاح!' : 'Log cleared successfully!');
      } else {
        alert(lang === 'ar' ? 'فشل مسح السجل.' : 'Failed to clear log.');
      }
    } catch (err) {
      alert('Error clearing log');
    } finally {
      setLoadingLog(false);
    }
  };

  useEffect(() => {
    fetchLogs(selectedLogType);
  }, [selectedLogType]);

  return (
    <div className="space-y-6 text-right font-sans">
      
      {/* 1. Header block */}
      <div className="bg-slate-900 p-5 rounded-xl border border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h3 className="font-extrabold text-sm text-white flex items-center gap-2">
            <Wrench className="w-5 h-5 text-indigo-400 animate-spin-slow" />
            <span>نظام التشخيص وإصلاح الأعطال الذاتي للموقع ⚙️</span>
          </h3>
          <p className="text-[11px] text-slate-400 mt-1">
            يقوم النظام بتحليل عميق لقاعدة البيانات، ملفات السجلات، ومستويات الأمان للـ WAF على السيرفر لتوليد حلول ذكية بنقرة واحدة.
          </p>
        </div>
        <button
          onClick={runDiagnostics}
          disabled={loading}
          className="px-3.5 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 rounded transition flex items-center gap-1.5 cursor-pointer shrink-0"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          <span>{loading ? 'جاري الفحص والتحليل...' : 'إعادة فحص وتحديث النظام'}</span>
        </button>
      </div>

      {actionStatus && (
        <div className={`p-4 rounded-xl border flex items-center gap-2.5 text-xs font-bold ${
          actionStatus.type === 'success' 
            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' 
            : 'bg-rose-500/10 border-rose-500/20 text-rose-400'
        }`}>
          {actionStatus.type === 'success' ? <CheckCircle className="w-5 h-5" /> : <AlertTriangle className="w-5 h-5" />}
          <span>{actionStatus.message}</span>
        </div>
      )}

      {/* 2. Bento Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Diagnostics Checklist */}
        <div className="lg:col-span-2 bg-slate-900 p-5 rounded-xl border border-slate-800 space-y-4">
          <h4 className="font-bold text-xs text-white flex items-center gap-1.5 border-b border-slate-800 pb-2.5">
            <Activity className="w-4 h-4 text-indigo-400" />
            <span>تقرير الفحص والتحليل الشامل (System Health Diagnostic)</span>
          </h4>

          {loading ? (
            <div className="py-20 text-center text-slate-500 text-xs">
              <RefreshCw className="w-8 h-8 mx-auto text-indigo-500 animate-spin mb-3" />
              <span>جاري مسح بيئة التشغيل وهيكل قاعدة البيانات ومستويات الأمان...</span>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {diagnostics.map((item) => (
                <div 
                  key={item.key} 
                  className={`p-3.5 rounded-lg border flex flex-col justify-between space-y-2 text-right transition hover:border-slate-700 ${
                    item.status === 'ok' 
                      ? 'bg-slate-950/40 border-slate-850/80' 
                      : item.status === 'warning'
                      ? 'bg-amber-500/5 border-amber-500/10'
                      : 'bg-rose-500/5 border-rose-500/10'
                  }`}
                >
                  <div className="flex justify-between items-center">
                    <span className={`text-[9px] font-bold px-2 py-0.5 rounded ${
                      item.status === 'ok' 
                        ? 'bg-emerald-500/10 text-emerald-400' 
                        : item.status === 'warning'
                        ? 'bg-amber-500/10 text-amber-400'
                        : 'bg-rose-500/10 text-rose-400'
                    }`}>
                      {item.status === 'ok' ? 'سليم 🟢' : item.status === 'warning' ? 'تنبيه 🟡' : 'فشل خطير 🔴'}
                    </span>
                    <span className="font-extrabold text-xs text-slate-200">{item.title}</span>
                  </div>
                  
                  <p className="text-[11px] text-slate-400 leading-relaxed">{item.message}</p>
                  
                  {item.details && (
                    <div className="p-1.5 bg-slate-950 rounded text-[9px] text-slate-500 font-mono text-left block max-h-12 overflow-y-auto">
                      {item.details}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Healing & Troubleshooting Actions Panel */}
        <div className="bg-slate-900 p-5 rounded-xl border border-slate-800 space-y-4">
          <h4 className="font-bold text-xs text-white flex items-center gap-1.5 border-b border-slate-800 pb-2.5">
            <Wrench className="w-4 h-4 text-indigo-400" />
            <span>لوحة الإصلاح السريع الذاتي بنقرة واحدة (One-Click Self Healing)</span>
          </h4>
          <p className="text-[10px] text-slate-500 leading-relaxed">
            استخدم أدوات المعالجة والإنعاش الذاتي أدناه لحل المشاكل الأكثر شيوعاً على السيرفر وقاعدة البيانات فوراً.
          </p>

          <div className="space-y-3 pt-2">
            
            {/* Action 1: Repair Reservation Discrepancies */}
            <div className="p-3 bg-slate-950 rounded-lg border border-indigo-950/30 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-[10px] text-slate-500 font-mono">السيارات والحجوزات</span>
                <span className="text-xs font-bold text-slate-300">إصلاح وتزامن حجوزات السيارات</span>
              </div>
              <p className="text-[9px] text-slate-400 leading-relaxed">
                يقوم بمسح قاعدة البيانات وفك الحجوزات المنتهية تلقائياً وإعادة تصنيف السيارات العالقة كمتاحة.
              </p>
              <button
                onClick={() => triggerRepair('fix_reservations')}
                disabled={!!repairing}
                className="w-full py-1.5 text-[10px] font-bold rounded cursor-pointer transition flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >
                <Database className="w-3.5 h-3.5" />
                <span>{repairing === 'fix_reservations' ? 'جاري المعالجة الفورية...' : 'تشغيل محاذاة الحجوزات والسيارات 🔄'}</span>
              </button>
            </div>

            {/* Action 2: Align Database Columns */}
            <div className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-[10px] text-slate-500 font-mono">الجداول والأعمدة</span>
                <span className="text-xs font-bold text-slate-300">تحديث هيكل ومخطط الجداول</span>
              </div>
              <p className="text-[9px] text-slate-400 leading-relaxed">
                يتحقق من كافة الجداول وإضافة الأعمدة المفقودة تلقائياً (مثل role) وترقيتها لبيئة VARCHAR الشاملة.
              </p>
              <button
                onClick={() => triggerRepair('recreate_schema')}
                disabled={!!repairing}
                className="w-full py-1.5 text-[10px] font-bold rounded cursor-pointer transition flex items-center justify-center gap-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-slate-200 disabled:opacity-50"
              >
                <Wrench className="w-3.5 h-3.5 text-indigo-400" />
                <span>{repairing === 'recreate_schema' ? 'جاري محاذاة الجداول...' : 'إصلاح وتحديث مخطط الجداول'}</span>
              </button>
            </div>

            {/* Action 3: Repair Directory security & HTACCESS */}
            <div className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-[10px] text-slate-500 font-mono">حماية الملفات</span>
                <span className="text-xs font-bold text-slate-300">إصلاح وحماية المجلدات والملفات</span>
              </div>
              <p className="text-[9px] text-slate-400 leading-relaxed">
                ينشئ المجلدات المفقودة ويقوم بحقن ملفات حماية .htaccess لمنع هجمات رفع الملفات التنفيذية الضارة.
              </p>
              <button
                onClick={() => triggerRepair('fix_permissions')}
                disabled={!!repairing}
                className="w-full py-1.5 text-[10px] font-bold rounded cursor-pointer transition flex items-center justify-center gap-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-slate-200 disabled:opacity-50"
              >
                <FolderLock className="w-3.5 h-3.5 text-indigo-400" />
                <span>{repairing === 'fix_permissions' ? 'جاري تأمين المجلدات...' : 'إصلاح وحماية المجلدات الأمنية'}</span>
              </button>
            </div>

            {/* Action 4: Reset General Admin Password */}
            <div className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-[10px] text-slate-500 font-mono">المدراء والأذونات</span>
                <span className="text-xs font-bold text-slate-300">إنشاء/إعادة تعيين حساب المدير الافتراضي</span>
              </div>
              <p className="text-[9px] text-slate-400 leading-relaxed">
                يضمن وجود حساب المدير العام الافتراضي ببيانات الدخول (admin / admin123) لحل مشكلة قفل الحساب.
              </p>
              <button
                onClick={() => triggerRepair('reset_admin')}
                disabled={!!repairing}
                className="w-full py-1.5 text-[10px] font-bold rounded cursor-pointer transition flex items-center justify-center gap-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-slate-200 disabled:opacity-50"
              >
                <UserCheck className="w-3.5 h-3.5 text-indigo-400" />
                <span>{repairing === 'reset_admin' ? 'جاري إعادة حساب المدير...' : 'إنعاش حساب المدير الافتراضي'}</span>
              </button>
            </div>

            {/* Action 5: Clean Logs */}
            <div className="p-3 bg-slate-950 rounded-lg border border-slate-800/80 space-y-2">
              <div className="flex justify-between items-center">
                <span className="text-[10px] text-slate-500 font-mono">تدوير البيانات</span>
                <span className="text-xs font-bold text-slate-300">تطهير وتدوير السجلات المكتظة</span>
              </div>
              <p className="text-[9px] text-slate-400 leading-relaxed">
                يحتفظ بآخر 100 سجل أحداث فقط ويمسح باقي السجلات المكتنزة لتوفير سعة الخادم وسرعة الاستجابة.
              </p>
              <button
                onClick={() => triggerRepair('clear_system_logs')}
                disabled={!!repairing}
                className="w-full py-1.5 text-[10px] font-bold rounded cursor-pointer transition flex items-center justify-center gap-1.5 bg-rose-950/15 hover:bg-rose-950/35 border border-rose-900/30 text-rose-400 disabled:opacity-50"
              >
                <Trash2 className="w-3.5 h-3.5 text-rose-400" />
                <span>{repairing === 'clear_system_logs' ? 'جاري تنظيف وتدوير السجلات...' : 'تطهير وتدوير السجلات المكتظة'}</span>
              </button>
            </div>

          </div>
        </div>

      </div>

      {/* 3. Server Live Log Viewer Console */}
      <div className="bg-slate-900 p-5 rounded-xl border border-slate-800 space-y-4">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-800 pb-2.5">
          <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
            <Terminal className="w-4 h-4 text-emerald-400" />
            <span>مستعرض سجلات خادم الأمان والأعطال الحية (Live Log Viewer Console)</span>
          </h4>
          
          <div className="flex items-center gap-2 w-full sm:w-auto">
            <select
              value={selectedLogType}
              onChange={(e) => setSelectedLogType(e.target.value as any)}
              className="text-[10px] font-bold px-3 py-1.5 bg-slate-950 border border-slate-800 text-slate-300 rounded focus:outline-none focus:border-indigo-500"
            >
              <option value="waf">🛡️ جدار حماية الأمان (waf_security.log)</option>
              <option value="fatal">❌ الأخطاء الفادحة (fatal_exceptions.log)</option>
              <option value="warnings">⚠️ التحذيرات والتنبيهات (php_warnings.log)</option>
            </select>
            
            <button
              onClick={() => clearLogFile(selectedLogType)}
              className="p-1.5 rounded text-rose-400 hover:bg-rose-500/10 border border-rose-500/20 cursor-pointer"
              title="تفريغ هذا الملف"
            >
              <Trash2 className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <p className="text-[10px] text-slate-500">
          يعرض هذا المستعرض الملفات النصية المسجلة للأمان والتحذيرات على السيرفر مباشرة للمساعدة في تتبع المشاكل والتعافي منها بسهولة.
        </p>

        {loadingLog ? (
          <div className="py-20 text-center text-slate-500 text-xs">
            <RefreshCw className="w-6 h-6 mx-auto text-emerald-400 animate-spin mb-2" />
            <span>جاري سحب قراءة ملف السجل المباشر من السيرفر...</span>
          </div>
        ) : (
          <div className="relative">
            <pre className="p-4 bg-black/90 border border-slate-850 rounded-xl text-[10px] text-emerald-400 font-mono text-left block max-h-72 overflow-y-auto whitespace-pre-wrap leading-relaxed select-all">
              {logContent}
            </pre>
            <div className="absolute top-2 right-2 flex gap-1">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span className="text-[8px] font-bold text-slate-500 uppercase tracking-widest font-mono">Live</span>
            </div>
          </div>
        )}
      </div>

    </div>
  );
}
