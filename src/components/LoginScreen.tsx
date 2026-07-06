/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { KeyRound, User, Lock, AlertCircle, ShieldCheck, Car } from 'lucide-react';

interface LoginScreenProps {
  portal: 'representative' | 'manager';
  onLoginSuccess: (token: string, user: any) => void;
  onBackToLanding: () => void;
}

export default function LoginScreen({ portal, onLoginSuccess, onBackToLanding }: LoginScreenProps) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [companySettings, setCompanySettings] = useState<any>(null);

  useEffect(() => {
    fetch('/api/settings')
      .then(res => res.json())
      .then(data => setCompanySettings(data))
      .catch(err => console.error('Failed to load settings in LoginScreen', err));
  }, []);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username || !password) {
      setErrorMsg('يرجى كتابة اسم المستخدم وكلمة المرور للدخول.');
      return;
    }

    setLoading(true);
    setErrorMsg('');

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });

      if (!response.ok) {
        const result = await response.json();
        throw new Error(result.error || 'فشل تسجيل الدخول.');
      }

      const result = await response.json();
      
      // Separate login portals role validation
      if (portal === 'representative' && result.user.role !== 'representative') {
        throw new Error('عذراً، هذه البوابة مخصصة لمناديب المبيعات فقط. يرجى استخدام بوابة دخول المديرين.');
      }
      if (portal === 'manager' && result.user.role === 'representative') {
        throw new Error('عذراً، هذه البوابة مخصصة لمديري النظام فقط. يرجى استخدام بوابة دخول المناديب.');
      }

      onLoginSuccess(result.token, result.user);
    } catch (err: any) {
      setErrorMsg(err.message || 'خطأ في اسم المستخدم أو كلمة المرور.');
    } finally {
      setLoading(false);
    }
  };

  const handleQuickLogin = (user: string) => {
    setUsername(user);
    setPassword(`${user}123`);
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-4 relative overflow-hidden font-sans text-slate-800 dark:text-slate-200 transition-colors duration-300">
      
      {/* Visual Ambient Background Orbs */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/5 rounded-full blur-3xl -z-10"></div>
      <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-slate-500/5 rounded-full blur-3xl -z-10"></div>

      <div className="w-full max-w-4xl grid grid-cols-1 md:grid-cols-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-lg dark:shadow-2xl relative">
        
        {/* LEFT COLUMN: Visual Brand Cover */}
        <div className="md:col-span-5 bg-gradient-to-br from-slate-50 via-slate-100 to-slate-50 dark:from-slate-900 dark:via-slate-950 dark:to-slate-900 p-6 flex flex-col justify-between border-b md:border-b-0 md:border-l border-slate-200 dark:border-slate-800">
          
          <div className="flex items-center gap-3">
            {companySettings?.logo ? (
              <div className="w-8 h-8 rounded bg-slate-100 dark:bg-slate-950 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-800 shrink-0">
                <img src={companySettings.logo} alt="Logo" className="w-full h-full object-contain" />
              </div>
            ) : (
              <div className="w-8 h-8 rounded bg-indigo-600 flex items-center justify-center text-white font-extrabold text-lg shrink-0 border border-indigo-500">
                <Car className="w-4 h-4" />
              </div>
            )}
            <div>
              <h2 className="font-bold text-xs text-slate-900 dark:text-white tracking-tight leading-none truncate max-w-[150px]" title={companySettings?.companyName || 'نظام إدارة مخزون السيارات'}>
                {companySettings?.companyName || 'نظام إدارة مخزون السيارات'}
              </h2>
              <span className="text-[9px] text-indigo-600 dark:text-indigo-400 font-mono">ALMAKHZOUN PRO</span>
            </div>
          </div>

          <div className="space-y-3.5 my-8 md:my-0">
            <div className="w-10 h-10 rounded bg-indigo-50 dark:bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-500/20 shadow-sm dark:shadow-lg">
              <Car className="w-5 h-5" />
            </div>
            <h3 className="text-lg font-bold text-slate-900 dark:text-white leading-snug">
              بوابة مبيعات وإدارة مستودعات ومعارض السيارات الموحدة
            </h3>
            <p className="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
              برنامج متكامل وذكي لمتابعة المخازن، حجز المركبات، وتوزيع السيارات على الفروع وإخراج بطاقات المواصفات فورياً لعملاء المعارض الكرام.
            </p>
          </div>

          <div className="text-[9px] text-slate-400 dark:text-slate-500 font-mono">
            SECURE CLOUD PORTAL v2.1.0 • © 2026
          </div>
        </div>

        {/* RIGHT COLUMN: Interactive Login Form */}
        <div className="md:col-span-7 p-6 md:p-8 flex flex-col justify-center space-y-4 bg-white dark:bg-slate-900">
          <div>
            <span className="text-[9px] text-indigo-600 dark:text-indigo-400 font-bold tracking-wider uppercase block mb-1">
              {portal === 'representative' ? 'بوابة دخول المناديب' : 'بوابة دخول المديرين'}
            </span>
            <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
              {portal === 'representative' ? 'تسجيل دخول مندوبي المبيعات' : 'بوابة الإدارة والتحكم بالشركة'}
            </h1>
            <p className="text-[11px] text-slate-600 dark:text-slate-400 mt-1">
              {portal === 'representative' 
                ? 'يرجى تسجيل الدخول بكود المبيعات لمتابعة وحجز السيارات في المعرض.' 
                : 'بوابة مؤمنة بموجب بروتوكول الرقابة والأمن لاستعراض المؤشرات وإدارة الصلاحيات والتقارير.'}
            </p>
          </div>

          {errorMsg && (
            <div className="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-rose-600 dark:text-rose-400 text-xs font-bold flex items-center gap-2.5">
              <AlertCircle className="w-4.5 h-4.5 shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          <form onSubmit={handleLogin} className="space-y-3.5">
            
            {/* Username Input */}
            <div className="space-y-1">
              <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400">اسم المستخدم للوصول</label>
              <div className="relative">
                <input
                  type="text"
                  required
                  placeholder={portal === 'representative' ? 'مثال: agent' : 'مثال: admin'}
                  value={username}
                  onChange={e => setUsername(e.target.value)}
                  className="w-full text-xs pr-9 pl-3.5 py-2 rounded border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-indigo-500/75 focus:ring-1 focus:ring-indigo-500/20 font-sans"
                />
                <User className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 absolute top-2.5 right-3" />
              </div>
            </div>

            {/* Password Input */}
            <div className="space-y-1">
              <label className="block text-[10px] font-bold text-slate-500 dark:text-slate-400">كلمة المرور السرية</label>
              <div className="relative">
                <input
                  type="password"
                  required
                  placeholder="••••••••"
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  className="w-full text-xs pr-9 pl-3.5 py-2 rounded border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-indigo-500/75 focus:ring-1 focus:ring-indigo-500/20 font-sans"
                />
                <Lock className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 absolute top-2.5 right-3" />
              </div>
            </div>

            {/* Submit Action */}
            <button
              type="submit"
              disabled={loading}
              className="w-full py-2.5 rounded bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 font-extrabold text-xs text-white transition duration-150 flex items-center justify-center gap-1.5 shadow-lg shadow-indigo-600/10 cursor-pointer"
            >
              {loading ? (
                <>
                  <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <span>جاري التحقق من التشفير...</span>
                </>
              ) : (
                <>
                  <ShieldCheck className="w-4 h-4" />
                  <span>{portal === 'representative' ? 'تسجيل الدخول لبوابة المناديب' : 'تسجيل الدخول للوحة الإدارة'}</span>
                </>
              )}
            </button>
          </form>

          {/* Quick seeded logins cards */}
          <div className="pt-4 border-t border-slate-200 dark:border-slate-800">
            <span className="block text-[9px] font-bold text-slate-500 mb-2">
              حساب الدخول التجريبي السريع المخصص لهذه البوابة:
            </span>
            <div className="grid grid-cols-1 gap-2.5">
              
              {portal === 'manager' ? (
                <button
                  type="button"
                  onClick={() => handleQuickLogin('admin')}
                  className="p-2 text-right rounded bg-slate-50 hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-950/70 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/30 transition group flex flex-col justify-between cursor-pointer"
                >
                  <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">حساب مدير النظام المعتمد (صلاحية كاملة)</span>
                  <span className="text-[9px] text-slate-600 dark:text-slate-400 font-sans mt-0.5">اسم المستخدم: admin</span>
                  <span className="text-[9px] text-slate-600 dark:text-slate-400 font-sans">كلمة المرور: admin123</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => handleQuickLogin('agent')}
                  className="p-2 text-right rounded bg-slate-50 hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-950/70 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/30 transition group flex flex-col justify-between cursor-pointer"
                >
                  <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">حساب مندوب مبيعات معتمد (حجوزات وسيارت)</span>
                  <span className="text-[9px] text-slate-600 dark:text-slate-400 font-sans mt-0.5">اسم المستخدم: agent</span>
                  <span className="text-[9px] text-slate-600 dark:text-slate-400 font-sans">كلمة المرور: agent123</span>
                </button>
              )}

            </div>
          </div>

          {/* Return to Landing Page Action */}
          <button
            type="button"
            onClick={onBackToLanding}
            className="w-full py-2 text-[10px] font-bold rounded bg-slate-50 hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition flex items-center justify-center gap-1 cursor-pointer"
          >
            <span>← العودة إلى الصفحة التعريفية للشركة</span>
          </button>

        </div>

      </div>

    </div>
  );
}
