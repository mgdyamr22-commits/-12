/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { 
  Phone, 
  Mail, 
  MapPin, 
  Send, 
  ArrowRight, 
  CheckCircle, 
  AlertTriangle,
  Loader2,
  Clock,
  Globe
} from 'lucide-react';
import { SystemSettings } from '../types';

interface ContactUsPageProps {
  settings: SystemSettings | null;
  onBack: () => void;
  darkMode?: boolean;
}

export default function ContactUsPage({ settings, onBack, darkMode = true }: ContactUsPageProps) {
  const companyName = settings?.companyName || 'مؤسسة المخزون لإدارة واستيراد السيارات';
  const logo = settings?.logo || '';
  const address = settings?.address || 'المملكة العربية السعودية، الرياض، حي الياسمين';
  const phone = settings?.phone || '+966 50 123 4567';
  const email = settings?.email || 'info@al-makhzoun.com';
  const website = settings?.website || 'www.al-makhzoun.com';

  const [formData, setFormData] = React.useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: ''
  });

  const [isLoading, setIsLoading] = React.useState(false);
  const [successMsg, setSuccessMsg] = React.useState('');
  const [errorMsg, setErrorMsg] = React.useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name || !formData.phone || !formData.message) {
      setErrorMsg('يرجى ملء الاسم ورقم الجوال والرسالة.');
      return;
    }

    setIsLoading(true);
    setErrorMsg('');
    setSuccessMsg('');

    try {
      const response = await fetch('/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });

      const resData = await response.json();
      if (response.ok) {
        setSuccessMsg(resData.message || 'تم إرسال رسالتك بنجاح! شكراً لتواصلك معنا.');
        setFormData({
          name: '',
          email: '',
          phone: '',
          subject: '',
          message: ''
        });
      } else {
        setErrorMsg(resData.error || 'حدث خطأ أثناء إرسال الرسالة، يرجى المحاولة لاحقاً.');
      }
    } catch (err) {
      setErrorMsg('فشل الاتصال بالخادم، يرجى التحقق من اتصال الإنترنت.');
    } finally {
      setIsLoading(false);
    }
  };

  const accentColor = settings?.themeAccent || '#4f46e5';

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-sans transition-colors duration-300" dir="rtl">
      
      {/* HEADER / NAVBAR */}
      <header className="border-b border-slate-200 dark:border-slate-900 bg-white/80 dark:bg-slate-950/80 backdrop-blur-md sticky top-0 z-50 transition-all">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
          {/* Logo & Brand */}
          <div className="flex items-center gap-3">
            {logo ? (
              <div className="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-900 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-800 shrink-0">
                <img src={logo} alt="Logo" className="w-full h-full object-contain" />
              </div>
            ) : (
              <div className="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center text-white border border-indigo-500 shadow-md shrink-0">
                <span>🚗</span>
              </div>
            )}
            <div>
              <h1 className="font-extrabold text-sm text-slate-900 dark:text-white tracking-tight leading-none truncate max-w-[200px] sm:max-w-xs">
                {companyName}
              </h1>
              <span className="text-[9px] text-indigo-600 dark:text-indigo-400 font-mono tracking-widest block mt-1">CONTACT PORTAL</span>
            </div>
          </div>

          <button 
            onClick={onBack}
            className="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-extrabold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-lg transition flex items-center gap-1.5 cursor-pointer"
          >
            <ArrowRight className="w-4 h-4 ml-1" />
            <span>العودة للرئيسية</span>
          </button>
        </div>
      </header>

      {/* MAIN LAYOUT */}
      <main className="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <div className="text-center mb-12">
          <span className="text-xs text-indigo-600 dark:text-indigo-400 font-extrabold tracking-widest uppercase block mb-1">أتصل بنا</span>
          <h2 className="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">يسعدنا تواصلك معنا دائماً</h2>
          <p className="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto mt-2">
            إذا كان لديك أي استفسار حول سياراتنا المعروضة، حجز المعارض أو أي خدمات لوجستية، فلا تتردد في مراسلتنا فوراً.
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          {/* Left Block - Contact Info Details (5 cols) */}
          <div className="lg:col-span-5 space-y-6">
            <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800/80 shadow-md dark:shadow-2xl space-y-6">
              <h3 className="font-extrabold text-lg text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800">
                معلومات التواصل
              </h3>

              <div className="space-y-5">
                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <MapPin className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-slate-400">العنوان الرسمي</h4>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 leading-relaxed">
                      {address}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <Phone className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-slate-400">رقم الهاتف والجوال</h4>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 font-sans" dir="ltr">
                      {phone}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <Mail className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-slate-400">البريد الإلكتروني</h4>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 font-sans">
                      {email}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <Globe className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-slate-400">الموقع الإلكتروني الرسمي</h4>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 font-sans">
                      {website}
                    </p>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <Clock className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-bold text-xs text-slate-400">أوقات العمل واستلام الاستفسارات</h4>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1">
                      الأحد - الخميس: من الساعة 8:00 صباحاً وحتى 6:00 مساءً
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Quick Helper Tips */}
            <div className="bg-amber-500/5 border border-amber-500/10 rounded-2xl p-5 text-right space-y-2">
              <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                <Clock className="w-4 h-4 shrink-0" />
                <span className="font-extrabold text-xs">ملاحظة هامة</span>
              </div>
              <p className="text-[10px] text-slate-500 leading-relaxed font-medium">
                تتم مراجعة كافة الطلبات والرسائل الواردة من خلال لوحة التحكم اللوجستية للمديرين والمناديب ويتم الرد والتواصل معك عبر الهاتف أو البريد الإلكتروني المدخل خلال 24 ساعة عمل.
              </p>
            </div>
          </div>

          {/* Right Block - Interactive Form (7 cols) */}
          <div className="lg:col-span-7">
            <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 md:p-8 border border-slate-200 dark:border-slate-800/80 shadow-md dark:shadow-2xl">
              <h3 className="font-extrabold text-lg text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-800 mb-6">
                نموذج التواصل السريع
              </h3>

              {successMsg && (
                <div className="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-start gap-3">
                  <CheckCircle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="text-xs font-bold leading-relaxed">{successMsg}</div>
                </div>
              )}

              {errorMsg && (
                <div className="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 flex items-start gap-3">
                  <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />
                  <div className="text-xs font-bold leading-relaxed">{errorMsg}</div>
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {/* Name field */}
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-bold text-slate-400">الاسم الكامل <span className="text-rose-500">*</span></label>
                    <input 
                      type="text" 
                      required
                      placeholder="أدخل اسمك الكامل"
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      className="w-full px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-medium transition"
                    />
                  </div>

                  {/* Phone field */}
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-bold text-slate-400">رقم الجوال <span className="text-rose-500">*</span></label>
                    <input 
                      type="tel" 
                      required
                      placeholder="مثال: 05XXXXXXXX"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                      className="w-full px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-medium font-sans text-right transition"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {/* Email field */}
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-bold text-slate-400">البريد الإلكتروني</label>
                    <input 
                      type="email" 
                      placeholder="name@example.com"
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                      className="w-full px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-medium font-sans text-right transition"
                    />
                  </div>

                  {/* Subject field */}
                  <div className="space-y-1.5">
                    <label className="block text-[11px] font-bold text-slate-400">موضوع الرسالة</label>
                    <input 
                      type="text" 
                      placeholder="مثال: استفسار عن سعر سيارة، طلب وكالة..."
                      value={formData.subject}
                      onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
                      className="w-full px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-medium transition"
                    />
                  </div>
                </div>

                {/* Message field */}
                <div className="space-y-1.5">
                  <label className="block text-[11px] font-bold text-slate-400">نص الرسالة أو الاستفسار <span className="text-rose-500">*</span></label>
                  <textarea 
                    rows={5}
                    required
                    placeholder="اكتب تفاصيل استفسارك أو رسالتك هنا..."
                    value={formData.message}
                    onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-medium leading-relaxed transition"
                  />
                </div>

                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full py-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-indigo-600/10 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      <span>جاري إرسال الرسالة...</span>
                    </>
                  ) : (
                    <>
                      <Send className="w-4 h-4 ml-1" />
                      <span>إرسال الرسالة الآن</span>
                    </>
                  )}
                </button>
              </form>
            </div>
          </div>

        </div>
      </main>

      {/* FOOTER */}
      <footer className="border-t border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-950 py-6 text-center">
        <div className="max-w-7xl mx-auto px-4 text-[10px] text-slate-400 dark:text-slate-500 font-mono">
          ALMAKHZOUN PRO • © 2026 • جميع الحقوق محفوظة
        </div>
      </footer>

    </div>
  );
}
