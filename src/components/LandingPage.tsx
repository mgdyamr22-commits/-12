/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { 
  Car, 
  Eye, 
  Target, 
  Award, 
  Phone, 
  Mail, 
  MapPin, 
  Globe, 
  Twitter, 
  Facebook, 
  Instagram, 
  Linkedin, 
  LogIn, 
  Sparkles,
  ArrowLeftRight,
  Sun,
  Moon
} from 'lucide-react';
import { SystemSettings } from '../types';

interface LandingPageProps {
  settings: SystemSettings | null;
  darkMode?: boolean;
  toggleDarkMode?: () => void;
  onSelectPortal: (portal: 'rep_login' | 'manager_login') => void;
  onContact?: () => void;
}

export default function LandingPage({ settings, darkMode = true, toggleDarkMode, onSelectPortal, onContact }: LandingPageProps) {
  // Use settings or default values in case settings are not loaded yet
  const companyName = settings?.companyName || 'مؤسسة المخزون لإدارة واستيراد السيارات';
  const logo = settings?.logo || '';
  const description = settings?.companyDescription || 'المنصة الرائدة في إدارة وحجز مخازن السيارات الفاخرة والحديثة، وتسهيل كافة العمليات الجمركية واللوجستية بأعلى درجات الأمان والسرعة.';
  const vision = settings?.vision || 'أن نكون الخيار والواجهة اللوجستية الأولى لحوكمة وحوسبة مخازن السيارات في السوق الخليجي.';
  const mission = settings?.mission || 'تقديم حلول تقنية ذكية لربط المعارض بالمستودعات، وتمكين مناديب المبيعات من الحجز المباشر والفوري.';
  const goals = settings?.goals || 'أتمتة العمليات اليومية بنسبة 100%، تقليص فترات تجميد المخزون، وتحقيق الرقابة الكاملة والشفافية التامة على أصول الشركة.';
  const address = settings?.address || 'المملكة العربية السعودية، الرياض، حي الياسمين';
  const phone = settings?.phone || '+966 50 123 4567';
  const email = settings?.email || 'info@al-makhzoun.com';
  const website = settings?.website || 'www.al-makhzoun.com';

  const socialTwitter = settings?.socialTwitter || 'https://twitter.com';
  const socialFacebook = settings?.socialFacebook || 'https://facebook.com';
  const socialInstagram = settings?.socialInstagram || 'https://instagram.com';
  const socialLinkedin = settings?.socialLinkedin || 'https://linkedin.com';

  const bannerBgImage = settings?.bannerBgImage || 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&q=80&w=1200';
  const bannerBgHeight = settings?.bannerBgHeight || '520px';
  const bannerBgWidth = settings?.bannerBgWidth || '100%';
  const bannerTitleColor = settings?.bannerTitleColor || '#ffffff';
  const bannerSubtitleColor = settings?.bannerSubtitleColor || '#e2e8f0';
  const bannerTextBgEnable = settings?.bannerTextBgEnable !== undefined ? settings.bannerTextBgEnable : true;
  const bannerTextBgOpacity = settings?.bannerTextBgOpacity !== undefined ? settings.bannerTextBgOpacity : 65;

  const accentColor = settings?.themeAccent || '#4f46e5';
  const opacityVal = settings?.themeOpacity !== undefined ? settings?.themeOpacity : 85;
  
  const hexToRgb = (hex: string) => {
    if (!hex || hex === 'transparent') return '0, 0, 0';
    let c = hex.replace('#', '');
    if (c.length === 3) {
      c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
    }
    const num = parseInt(c, 16);
    return `${(num >> 16) & 255}, ${(num >> 8) & 255}, ${num & 255}`;
  };

  const rgbAccent = hexToRgb(accentColor);

  React.useEffect(() => {
    // Dynamic SEO
    const seoSettings = settings?.seo?.landing || {
      title: 'الرئيسية | ' + companyName,
      description: description
    };
    
    document.title = seoSettings.title;
    
    // Update Meta Description
    let metaDesc = document.querySelector('meta[name="description"]');
    if (!metaDesc) {
      metaDesc = document.createElement('meta');
      metaDesc.setAttribute('name', 'description');
      document.head.appendChild(metaDesc);
    }
    metaDesc.setAttribute('content', seoSettings.description);
    
    // Auto-log visitor entry client-side for high-fidelity analytics!
    fetch('/api/analytics/log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        path: '/',
        referrer: document.referrer || '',
        action: 'زيارة الصفحة الرئيسية للزوار'
      })
    }).catch(err => console.error('Analytics log error:', err));
  }, [settings, companyName, description]);

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col font-sans relative overflow-x-hidden transition-colors duration-300" dir="rtl">
      
      {/* Dynamic Style Override for Theme Accent and Opacity */}
      <style>{`
        :root {
          --theme-color: ${accentColor === 'transparent' ? 'transparent' : accentColor};
          --theme-color-rgb: ${rgbAccent};
          --theme-opacity: ${accentColor === 'transparent' ? 0 : opacityVal / 100};
        }

        .text-indigo-400, .text-indigo-500 {
          color: ${accentColor === 'transparent' ? 'inherit' : accentColor} !important;
        }
        
        .bg-indigo-600 {
          background-color: ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, ${opacityVal / 100})`} !important;
        }
        
        .bg-indigo-600\\/10 {
          background-color: ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.1)`} !important;
        }

        .hover\\:bg-indigo-700:hover {
          background-color: ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.95)`} !important;
        }

        .border-indigo-500 {
          border-color: ${accentColor === 'transparent' ? 'transparent' : accentColor} !important;
        }

        .border-indigo-500\\/20 {
          border-color: ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.2)`} !important;
        }

        .shadow-indigo-650\\/10 {
          box-shadow: 0 4px 6px -1px ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.1)`}, 
                      0 2px 4px -2px ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.1)`} !important;
        }

        .bg-indigo-600\\/5 {
          background-color: ${accentColor === 'transparent' ? 'transparent' : `rgba(${rgbAccent}, 0.05)`} !important;
        }
      `}</style>
      
      {/* Decorative Blur Orbs */}
      <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-3xl -z-10"></div>
      <div className="absolute bottom-1/4 left-1/4 w-[400px] h-[400px] bg-emerald-600/5 rounded-full blur-3xl -z-10"></div>

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
                <Car className="w-5 h-5" />
              </div>
            )}
            <div>
              <h1 className="font-extrabold text-sm text-slate-900 dark:text-white tracking-tight leading-none truncate max-w-[200px] sm:max-w-xs">
                {companyName}
              </h1>
              <span className="text-[9px] text-indigo-600 dark:text-indigo-400 font-mono tracking-widest block mt-1">ALMAKHZOUN PRO</span>
            </div>
          </div>

          {/* Quick Nav Login Portals & Light Toggle */}
          <div className="flex items-center gap-2 sm:gap-3">
            {toggleDarkMode && (
              <button 
                onClick={toggleDarkMode}
                className="p-2 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer flex justify-center items-center"
                title="تبديل الإضاءة"
              >
                {darkMode ? <Sun className="w-4 h-4 text-amber-400" /> : <Moon className="w-4 h-4 text-indigo-600" />}
              </button>
            )}
            {onContact && (
              <button 
                onClick={onContact}
                className="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-extrabold text-slate-700 dark:text-slate-350 bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-lg transition flex items-center gap-1.5 cursor-pointer"
              >
                <Mail className="w-3.5 h-3.5 text-indigo-500" />
                <span>اتصل بنا</span>
              </button>
            )}
            <button 
              onClick={() => onSelectPortal('rep_login')}
              className="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-extrabold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-600/10 hover:bg-indigo-100 dark:hover:bg-indigo-600/25 border border-indigo-200 dark:border-indigo-500/20 hover:border-indigo-300 dark:hover:border-indigo-500/40 rounded-lg transition flex items-center gap-1.5 cursor-pointer"
            >
              <LogIn className="w-3.5 h-3.5" />
              <span>دخول المناديب</span>
            </button>
            <button 
              onClick={() => onSelectPortal('manager_login')}
              className="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/30 transition flex items-center gap-1.5 cursor-pointer"
            >
              <LogIn className="w-3.5 h-3.5" />
              <span>دخول المديرين</span>
            </button>
          </div>

        </div>
      </header>

      {/* Title & Subtitle Container (Moved from Banner to below the Header) */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 text-center flex flex-col items-center">
        <div 
          className={`p-6 md:p-8 rounded-2xl w-full max-w-4xl transition-all duration-300 text-center flex flex-col items-center ${
            bannerTextBgEnable 
              ? 'backdrop-blur-md border border-slate-200/80 dark:border-slate-800/80 shadow-lg dark:shadow-2xl' 
              : 'bg-transparent border-none shadow-none'
          }`}
          style={bannerTextBgEnable ? {
            backgroundColor: darkMode 
              ? `rgba(15, 23, 42, ${bannerTextBgOpacity / 100})`
              : `rgba(255, 255, 255, ${Math.min(95, bannerTextBgOpacity + 15) / 100})`
          } : undefined}
        >
          <h2 
            className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black tracking-tight leading-tight mb-4"
            style={{ color: darkMode ? bannerTitleColor : '#0f172a' }}
          >
            منصة <span className="text-transparent bg-clip-text bg-gradient-to-l from-indigo-600 to-indigo-800 dark:from-indigo-300 dark:to-indigo-500">المخزون برو</span> لإدارة واستيراد السيارات
          </h2>

          <p 
            className="text-xs sm:text-sm md:text-base leading-relaxed max-w-2xl mx-auto font-semibold"
            style={{ color: darkMode ? bannerSubtitleColor : '#334155' }}
          >
            {description}
          </p>
        </div>
      </div>

      {/* ENLARGED PROMINENT BADGE */}
      <div className="text-center pt-10 pb-2">
        <div className="inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-full bg-white dark:bg-slate-900/90 border border-indigo-200 dark:border-indigo-500/35 text-indigo-600 dark:text-indigo-300 text-xs sm:text-sm md:text-base lg:text-lg font-black tracking-wide shadow-md dark:shadow-xl shadow-indigo-600/5 dark:shadow-indigo-950/40 hover:scale-105 transition-transform duration-300 select-none cursor-pointer">
          <Sparkles className="w-5 h-5 text-amber-500 dark:text-amber-400 animate-pulse shrink-0" />
          <span className="bg-gradient-to-r from-indigo-700 via-indigo-900 to-indigo-700 dark:from-indigo-200 dark:via-white dark:to-indigo-200 bg-clip-text text-transparent">✨ تصفح واطلب سيارتك المفضلة الآن</span>
          <Sparkles className="w-5 h-5 text-amber-500 dark:text-amber-400 animate-pulse shrink-0" />
        </div>
      </div>

      {/* HERO SECTION */}
      <section className="relative pt-6 pb-16 md:pb-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center flex flex-col items-center">
        <div className="w-full max-w-5xl mx-auto space-y-8 flex flex-col items-center">
          
          {/* Banner container with manager's custom width & height */}
          <div 
            className="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col justify-end items-center"
            style={{
              height: bannerBgHeight,
              width: bannerBgWidth,
              maxWidth: '100%'
            }}
          >
            {/* Background Image of the welcoming banner */}
            <img 
              src={bannerBgImage} 
              alt="Welcoming Banner" 
              className="absolute inset-0 w-full h-full object-cover select-none pointer-events-none"
              referrerPolicy="no-referrer"
            />
            
            {/* Overlay gradient to keep high text contrast */}
            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/95 via-slate-950/30 to-transparent"></div>

            {/* Elegant luxury minimalist tag */}
            <div className="relative z-10 px-6 py-3 mb-6 rounded-lg border border-white/10 bg-slate-950/40 backdrop-blur-sm shadow-lg text-center flex items-center gap-2">
              <Car className="w-4 h-4 text-indigo-400" />
              <span className="text-[10px] sm:text-xs font-bold tracking-widest text-slate-200">الوكيل المعتمد لسيارات النخبة والواردات الجمركية</span>
            </div>
          </div>

          {/* Interactive Portal Selector Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg mx-auto pt-6 text-right w-full">
            
            {/* Rep Portal Card */}
            <div 
              onClick={() => onSelectPortal('rep_login')}
              className="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/30 shadow-md dark:shadow-xl transition-all cursor-pointer hover:-translate-y-1 group flex flex-col justify-between"
            >
              <div className="flex items-center justify-between mb-4">
                <div className="w-10 h-10 rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-500/20 shadow-sm group-hover:bg-indigo-600 group-hover:text-white transition-all">
                  <LogIn className="w-5 h-5" />
                </div>
                <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-600/10 px-2 py-0.5 rounded border border-indigo-100 dark:border-indigo-500/10">مبيعات وحجوزات</span>
              </div>
              <div>
                <h4 className="font-extrabold text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">بوابة دخول المناديب</h4>
                <p className="text-[10px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                  استعرض مخزن ومعرض السيارات فوراً وحجزها للعملاء الكرام بمستنداتها الرسمية والبطاقات الجمركية.
                </p>
              </div>
            </div>

            {/* Manager Portal Card */}
            <div 
              onClick={() => onSelectPortal('manager_login')}
              className="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500/30 shadow-md dark:shadow-xl transition-all cursor-pointer hover:-translate-y-1 group flex flex-col justify-between"
            >
              <div className="flex items-center justify-between mb-4">
                <div className="w-10 h-10 rounded bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-500/20 shadow-sm group-hover:bg-indigo-600 group-hover:text-white transition-all">
                  <ArrowLeftRight className="w-5 h-5" />
                </div>
                <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-600/10 px-2 py-0.5 rounded border border-emerald-100 dark:border-emerald-500/10">صلاحية كاملة</span>
              </div>
              <div>
                <h4 className="font-extrabold text-sm text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">بوابة دخول المديرين</h4>
                <p className="text-[10px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                  تحكم لوجستي متكامل بالفروع، إعدادات النظام، حوكمة الصلاحيات، إصدار التقارير ومراقبة مؤشرات الأداء الحية.
                </p>
              </div>
            </div>

          </div>
        </div>
      </section>

      {/* STRATEGIC Bento-Grid (VISION, MISSION, GOALS) */}
      <section className="bg-slate-100/50 dark:bg-slate-950/60 border-t border-b border-slate-200 dark:border-slate-900/60 py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          
          <div className="text-center mb-12">
            <span className="text-[10px] text-indigo-600 dark:text-indigo-400 font-extrabold tracking-widest uppercase block mb-1">الهوية الاستراتيجية للشركة</span>
            <h3 className="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white">رؤيتنا ورسالتنا وأهدافنا الأساسية</h3>
            <p className="text-[11px] text-slate-500 mt-1">نسعى للريادة والتميز عبر استراتيجية مستدامة ونظام ذكي لإدارة المحركات</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            {/* Vision Card */}
            <div className="bg-white dark:bg-slate-900/40 p-6 rounded-xl border border-slate-200 dark:border-slate-800/60 shadow-md dark:shadow-lg hover:border-slate-300 dark:hover:border-slate-800 transition-all text-right space-y-4">
              <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <Eye className="w-5 h-5" />
              </div>
              <h4 className="font-extrabold text-sm text-slate-900 dark:text-white">رؤية الشركة</h4>
              <p className="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                {vision}
              </p>
            </div>

            {/* Mission Card */}
            <div className="bg-white dark:bg-slate-900/40 p-6 rounded-xl border border-slate-200 dark:border-slate-800/60 shadow-md dark:shadow-lg hover:border-slate-300 dark:hover:border-slate-800 transition-all text-right space-y-4">
              <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <Target className="w-5 h-5" />
              </div>
              <h4 className="font-extrabold text-sm text-slate-900 dark:text-white">رسالة الشركة</h4>
              <p className="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                {mission}
              </p>
            </div>

            {/* Goals Card */}
            <div className="bg-white dark:bg-slate-900/40 p-6 rounded-xl border border-slate-200 dark:border-slate-800/60 shadow-md dark:shadow-lg hover:border-slate-300 dark:hover:border-slate-800 transition-all text-right space-y-4">
              <div className="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-600/10 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <Award className="w-5 h-5" />
              </div>
              <h4 className="font-extrabold text-sm text-slate-900 dark:text-white">أهداف الشركة</h4>
              <p className="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed font-medium">
                {goals}
              </p>
            </div>

          </div>

        </div>
      </section>

      {/* CONTACT INFO & SOCIAL MEDIA */}
      <footer className="mt-auto border-t border-slate-200 dark:border-slate-900 bg-white dark:bg-slate-950 pt-12 pb-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-8 text-right pb-8 border-b border-slate-200 dark:border-slate-900">
            
            {/* About brief */}
            <div className="md:col-span-5 space-y-3">
              <div className="flex items-center gap-2.5">
                {logo ? (
                  <div className="w-7 h-7 rounded bg-slate-100 dark:bg-slate-900 flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-800 shrink-0">
                    <img src={logo} alt="Logo" className="w-full h-full object-contain" />
                  </div>
                ) : (
                  <div className="w-7 h-7 rounded bg-indigo-600 flex items-center justify-center text-white shrink-0">
                    <Car className="w-4 h-4" />
                  </div>
                )}
                <span className="font-extrabold text-xs text-slate-900 dark:text-white">{companyName}</span>
              </div>
              <p className="text-[10px] text-slate-600 dark:text-slate-400 leading-relaxed max-w-sm">
                نظام المخزون هو بوابتك للتحكم اللوجستي بالمستودعات الجمركية وربط الفروع المعتمدة ومراقبة وتحفيز مناديب البيع في شتى بقاع المملكة العربية السعودية.
              </p>
              
              {/* Social links */}
              <div className="flex items-center gap-3 pt-2">
                <a href={socialTwitter} target="_blank" rel="noreferrer" className="w-7 h-7 rounded-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white flex items-center justify-center transition hover:scale-105">
                  <Twitter className="w-3.5 h-3.5" />
                </a>
                <a href={socialFacebook} target="_blank" rel="noreferrer" className="w-7 h-7 rounded-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white flex items-center justify-center transition hover:scale-105">
                  <Facebook className="w-3.5 h-3.5" />
                </a>
                <a href={socialInstagram} target="_blank" rel="noreferrer" className="w-7 h-7 rounded-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white flex items-center justify-center transition hover:scale-105">
                  <Instagram className="w-3.5 h-3.5" />
                </a>
                <a href={socialLinkedin} target="_blank" rel="noreferrer" className="w-7 h-7 rounded-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-white flex items-center justify-center transition hover:scale-105">
                  <Linkedin className="w-3.5 h-3.5" />
                </a>
              </div>
            </div>

            {/* Direct contact info */}
            <div className="md:col-span-4 space-y-3">
              <h4 className="font-extrabold text-xs text-indigo-600 dark:text-indigo-400">معلومات التواصل المباشر</h4>
              <ul className="space-y-2 text-[10px] text-slate-600 dark:text-slate-400">
                <li className="flex items-center gap-2">
                  <MapPin className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                  <span>{address}</span>
                </li>
                <li className="flex items-center gap-2">
                  <Phone className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                  <span className="font-sans" dir="ltr">{phone}</span>
                </li>
                <li className="flex items-center gap-2">
                  <Mail className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                  <span className="font-sans">{email}</span>
                </li>
              </ul>
            </div>

            {/* Platform utilities */}
            <div className="md:col-span-3 space-y-3">
              <h4 className="font-extrabold text-xs text-indigo-600 dark:text-indigo-400">منصات ومواقع مكملة</h4>
              <ul className="space-y-2 text-[10px] text-slate-600 dark:text-slate-400">
                <li className="flex items-center gap-2">
                  <Globe className="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                  <span className="font-sans">{website}</span>
                </li>
                <li className="text-[9px] text-slate-400 dark:text-slate-500">
                  ساعات الدوام الرسمي: الأحد - الخميس من 8:00 ص إلى 6:00 م
                </li>
              </ul>
            </div>

          </div>

          <div className="pt-6 flex flex-col sm:flex-row justify-between items-center text-[10px] text-slate-400 dark:text-slate-500 font-medium border-t border-slate-100 dark:border-slate-900/50">
            <span className="font-mono">ALMAKHZOUN PRO CLOUD ENGINE v2.1.0 • © 2026</span>
            <span className="mt-2 sm:mt-0">مطوّر بالكامل ومهيأ للاستضافة والتنزيل على cPanel & Apache</span>
          </div>
        </div>
      </footer>

    </div>
  );
}
