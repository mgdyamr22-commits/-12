/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useRef } from 'react';
import { 
  Settings, 
  Save, 
  Database, 
  ShieldAlert, 
  CheckCircle, 
  AlertCircle, 
  Upload, 
  Terminal,
  Globe,
  Twitter,
  Facebook,
  Instagram,
  Linkedin,
  Eye,
  Target,
  Award,
  Sparkles,
  Image,
  Maximize2,
  Type
} from 'lucide-react';
import { SystemSettings } from '../types';
import SEOSettingsPanel from './SEOSettingsPanel';
import AnalyticsDashboard from './AnalyticsDashboard';
import TroubleshootPanel from './TroubleshootPanel';

interface SettingsPanelProps {
  settings: SystemSettings;
  token: string;
  onSaveSettings: (settings: SystemSettings) => void;
  onRestoreSuccess: () => void;
  onOpenInstallerSim?: () => void;
}

export default function SettingsPanel({ settings, token, onSaveSettings, onRestoreSuccess, onOpenInstallerSim }: SettingsPanelProps) {
  const [activeSubTab, setActiveSubTab] = useState<'general' | 'landing' | 'seo' | 'analytics' | 'troubleshoot'>('general');
  
  // General settings state
  const [companyName, setCompanyName] = useState(settings.companyName);
  const [phone, setPhone] = useState(settings.phone);
  const [email, setEmail] = useState(settings.email);
  const [currency, setCurrency] = useState(settings.currency);
  const [address, setAddress] = useState(settings.address);
  const [systemStatus, setSystemStatus] = useState(settings.systemStatus);
  const [logo, setLogo] = useState(settings.logo || '');

  // Theme settings state
  const [themeAccent, setThemeAccent] = useState(settings.themeAccent || '#4f46e5');
  const [themeOpacity, setThemeOpacity] = useState(settings.themeOpacity !== undefined ? settings.themeOpacity : 85);

  // Banner background custom states
  const [bannerBgImage, setBannerBgImage] = useState(settings.bannerBgImage || 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&q=80&w=1200');
  const [bannerBgHeight, setBannerBgHeight] = useState(settings.bannerBgHeight || '520px');
  const [bannerBgWidth, setBannerBgWidth] = useState(settings.bannerBgWidth || '100%');
  const [bannerTitleColor, setBannerTitleColor] = useState(settings.bannerTitleColor || '#ffffff');
  const [bannerSubtitleColor, setBannerSubtitleColor] = useState(settings.bannerSubtitleColor || '#e2e8f0');
  const [bannerTextBgEnable, setBannerTextBgEnable] = useState(settings.bannerTextBgEnable !== undefined ? settings.bannerTextBgEnable : true);
  const [bannerTextBgOpacity, setBannerTextBgOpacity] = useState(settings.bannerTextBgOpacity !== undefined ? settings.bannerTextBgOpacity : 65);

  // Landing page settings state
  const [companyDescription, setCompanyDescription] = useState(settings.companyDescription || '');
  const [vision, setVision] = useState(settings.vision || '');
  const [mission, setMission] = useState(settings.mission || '');
  const [goals, setGoals] = useState(settings.goals || '');
  const [website, setWebsite] = useState(settings.website || '');
  const [socialTwitter, setSocialTwitter] = useState(settings.socialTwitter || '');
  const [socialFacebook, setSocialFacebook] = useState(settings.socialFacebook || '');
  const [socialInstagram, setSocialInstagram] = useState(settings.socialInstagram || '');
  const [socialLinkedin, setSocialLinkedin] = useState(settings.socialLinkedin || '');

  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);
  const logoInputRef = useRef<HTMLInputElement>(null);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    onSaveSettings({
      companyName,
      phone,
      email,
      currency,
      address,
      systemStatus,
      logo,
      companyDescription,
      vision,
      mission,
      goals,
      website,
      socialTwitter,
      socialFacebook,
      socialInstagram,
      socialLinkedin,
      themeAccent,
      themeOpacity,
      bannerBgImage,
      bannerBgHeight,
      bannerBgWidth,
      bannerTitleColor,
      bannerSubtitleColor,
      bannerTextBgEnable,
      bannerTextBgOpacity,
      seo: settings.seo
    });

    setSuccessMsg('تم حفظ وتحديث الإعدادات العامة للشركة وبيانات الصفحة التعريفية بنجاح!');
  };

  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      setErrorMsg('حجم ملف الشعار يجب أن يكون أقل من 2 ميغابايت.');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      setLogo(reader.result as string);
    };
    reader.onerror = () => {
      setErrorMsg('حدث خطأ أثناء قراءة ملف الشعار.');
    };
    reader.readAsDataURL(file);
  };

  // Trigger Backup Download
  const handleBackup = async () => {
    try {
      const response = await fetch('/api/backup', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      if (!response.ok) throw new Error('فشل توليد النسخة الاحتياطية.');
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `elite_car_stock_backup_${Date.now()}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch (err: any) {
      setErrorMsg(err.message || 'حدث خطأ أثناء تحميل ملف النسخة الاحتياطية.');
    }
  };

  // Trigger Restore Upload
  const handleRestore = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setLoading(true);
    setErrorMsg('');
    setSuccessMsg('');

    try {
      const reader = new FileReader();
      const readPromise = new Promise<string>((resolve, reject) => {
        reader.onload = () => resolve(reader.result as string);
        reader.onerror = () => reject(new Error('خطأ في قراءة ملف الاستعادة.'));
        reader.readAsText(file);
      });

      const jsonString = await readPromise;

      // Send to server Restore endpoint
      const response = await fetch('/api/restore', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ backupData: jsonString })
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.error || 'فشل استيراد واستعادة ملف البيانات.');
      }

      setSuccessMsg('تم استيراد قاعدة البيانات بالكامل وتحديث النظام بنجاح!');
      onRestoreSuccess(); // Refresh global states immediately
    } catch (err: any) {
      setErrorMsg(err.message || 'فشل استيراد قاعدة البيانات. تأكد من أن الملف سليم وبصيغة JSON صحيحة.');
    } finally {
      setLoading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 text-right font-sans">
      
      {/* 1. GENERAL & LANDING SYSTEM SETTINGS */}
      <div className="lg:col-span-2 bg-slate-900 p-5 rounded-xl border border-slate-800 space-y-5">
        
        {/* Sub-Tab Header Switcher */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-800 pb-3">
          <div>
            <h3 className="font-extrabold text-sm text-white flex items-center gap-1.5">
              <Settings className="w-4 h-4 text-indigo-400" />
              <span>تهيئة وتعديل إعدادات المؤسسة</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">اضبط الهوية التجارية للنظام ومحتوى الصفحة الرئيسية للعملاء والزوار</p>
          </div>
          
          {/* Tabs buttons */}
          <div className="flex bg-slate-950 border border-slate-800 rounded p-1 shrink-0 w-full sm:w-auto">
            <button
              type="button"
              onClick={() => setActiveSubTab('general')}
              className={`flex-1 sm:flex-initial px-3 py-1.5 rounded text-[10px] font-bold transition cursor-pointer ${activeSubTab === 'general' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}
            >
              الهوية العامة
            </button>
            <button
              type="button"
              onClick={() => setActiveSubTab('landing')}
              className={`flex-1 sm:flex-initial px-3 py-1.5 rounded text-[10px] font-bold transition cursor-pointer ${activeSubTab === 'landing' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}
            >
              إعدادات الصفحة التعريفية
            </button>
            <button
              type="button"
              onClick={() => setActiveSubTab('seo')}
              className={`flex-1 sm:flex-initial px-3 py-1.5 rounded text-[10px] font-bold transition cursor-pointer ${activeSubTab === 'seo' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}
            >
              تهيئة السيو (SEO)
            </button>
            <button
              type="button"
              onClick={() => setActiveSubTab('analytics')}
              className={`flex-1 sm:flex-initial px-3 py-1.5 rounded text-[10px] font-bold transition cursor-pointer ${activeSubTab === 'analytics' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}
            >
              حركات الزوار والتحليلات
            </button>
            <button
              type="button"
              onClick={() => setActiveSubTab('troubleshoot')}
              className={`flex-1 sm:flex-initial px-3 py-1.5 rounded text-[10px] font-bold transition cursor-pointer ${activeSubTab === 'troubleshoot' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}
            >
              تحليل المشاكل والصيانة ⚙️
            </button>
          </div>
        </div>

        {successMsg && (
          <div className="p-2.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded flex items-center gap-2">
            <CheckCircle className="w-4 h-4 shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}

        {activeSubTab === 'seo' ? (
          <SEOSettingsPanel settings={settings} onSaveSettings={onSaveSettings} />
        ) : activeSubTab === 'analytics' ? (
          <AnalyticsDashboard token={token} />
        ) : activeSubTab === 'troubleshoot' ? (
          <TroubleshootPanel token={token} lang="ar" />
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            
            {activeSubTab === 'general' ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Logo Upload Section */}
              <div className="md:col-span-2 p-3 bg-slate-950 rounded-lg border border-slate-800 flex flex-col md:flex-row items-center gap-4">
                <div className="w-20 h-20 rounded border border-dashed border-slate-700 bg-slate-900 flex items-center justify-center overflow-hidden shrink-0">
                  {logo ? (
                    <img src={logo} alt="Company Logo" className="w-full h-full object-contain" />
                  ) : (
                    <div className="text-center p-1">
                      <Upload className="w-5 h-5 mx-auto text-slate-500 mb-1" />
                      <span className="text-[9px] text-slate-500 block">لا يوجد شعار</span>
                    </div>
                  )}
                </div>
                
                <div className="flex-1 space-y-1.5 text-right w-full">
                  <span className="text-xs font-bold text-slate-300 block">شعار المؤسسة الرسمي</span>
                  <p className="text-[10px] text-slate-500 leading-relaxed">
                    اختر صورة شعار واضحة وخلفية شفافة إن أمكن (بصيغة PNG أو JPG أو SVG) بحد أقصى 2 ميجابايت ليتم عرضها في فواتير الطباعة وتصدير التقارير وأعلى شريط النظام.
                  </p>
                  
                  <div className="flex gap-2">
                    <input
                      type="file"
                      ref={logoInputRef}
                      onChange={handleLogoUpload}
                      accept="image/*"
                      className="hidden"
                    />
                    <button
                      type="button"
                      onClick={() => logoInputRef.current?.click()}
                      className="px-3 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer"
                    >
                      تحميل شعار
                    </button>
                    {logo && (
                      <button
                        type="button"
                        onClick={() => setLogo('')}
                        className="px-3 py-1.5 text-[10px] font-bold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 rounded transition cursor-pointer"
                      >
                        إزالة الشعار
                      </button>
                    )}
                  </div>
                </div>
              </div>
              
              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1">اسم المؤسسة / الشركة المعتمد</label>
                <input
                  type="text"
                  required
                  value={companyName}
                  onChange={e => setCompanyName(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1">رقم تواصل خدمة العملاء</label>
                <input
                  type="text"
                  required
                  value={phone}
                  onChange={e => setPhone(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1">البريد الإلكتروني الرسمي للمراسلات</label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={e => setEmail(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1">رمز العملة الرسمية للمخزون</label>
                <input
                  type="text"
                  required
                  value={currency}
                  onChange={e => setCurrency(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-bold text-slate-400 mb-1">العنوان والمقر الرئيسي للمركز</label>
                <input
                  type="text"
                  required
                  value={address}
                  onChange={e => setAddress(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              {/* Theme Accent and Opacity */}
              <div className="md:col-span-2 border-t border-slate-850 pt-4 mt-2 space-y-3">
                <h4 className="text-xs font-bold text-indigo-400 flex items-center gap-1">
                  <Sparkles className="w-3.5 h-3.5" />
                  <span>السمة اللونية العامة للموقع (Theme Accent) والشفافية</span>
                </h4>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Theme Accent Color Selection */}
                  <div className="space-y-2">
                    <label className="block text-[11px] text-slate-450 font-bold">اختر اللون الأساسي</label>
                    <div className="flex flex-wrap gap-2">
                      {[
                        { hex: '#4f46e5', label: 'كحلي / نيلة', bg: 'bg-[#4f46e5]' },
                        { hex: '#10b981', label: 'أخضر زمردي', bg: 'bg-[#10b981]' },
                        { hex: '#0ea5e9', label: 'أزرق سماوي', bg: 'bg-[#0ea5e9]' },
                        { hex: '#f43f5e', label: 'وردي / أحمر', bg: 'bg-[#f43f5e]' },
                        { hex: '#8b5cf6', label: 'بنفسجي ملكي', bg: 'bg-[#8b5cf6]' },
                        { hex: '#f59e0b', label: 'برتقالي عسلي', bg: 'bg-[#f59e0b]' },
                        { hex: 'transparent', label: 'بدون لون / شفاف', bg: 'bg-slate-900 border border-slate-750' }
                      ].map(c => {
                        const isSelected = themeAccent === c.hex;
                        return (
                          <button
                            key={c.hex}
                            type="button"
                            onClick={() => setThemeAccent(c.hex)}
                            className={`px-2.5 py-1.5 rounded text-[10px] font-bold transition flex items-center gap-1.5 border cursor-pointer ${isSelected ? 'border-white text-white' : 'border-slate-800 text-slate-450 hover:text-white'}`}
                          >
                            <span className={`w-2.5 h-2.5 rounded-full shrink-0 ${c.bg}`}></span>
                            <span>{c.label}</span>
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  {/* Opacity level */}
                  <div className="space-y-2">
                    <div className="flex justify-between items-center">
                      <label className="block text-[11px] text-slate-450 font-bold">درجة الشفافية (Opacity Level)</label>
                      <span className="text-[10px] text-slate-300 font-mono">{themeAccent === 'transparent' ? '0%' : `${themeOpacity}%`}</span>
                    </div>
                    <input
                      type="range"
                      min="0"
                      max="100"
                      step="5"
                      disabled={themeAccent === 'transparent'}
                      value={themeAccent === 'transparent' ? 0 : themeOpacity}
                      onChange={e => setThemeOpacity(parseInt(e.target.value))}
                      className="w-full accent-indigo-500 h-1 bg-slate-950 rounded-lg cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                    />
                    <p className="text-[9px] text-slate-500 leading-normal">
                      تتحكم هذه القيمة في درجة تعتيم اللون الأساسي فوق خلفية صفحات المعرض المخصصة للجمهور. ضبط القيمة لنسبة منخفضة أو اختيار "بدون لون" يضمن بقاء صور الخلفية ساطعة وواضحة دون تأثر.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              
              <div className="md:col-span-2">
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Sparkles className="w-3.5 h-3.5 text-indigo-400" />
                  <span>وصف ونبذة تعريفية بالشركة</span>
                </label>
                <textarea
                  value={companyDescription}
                  onChange={e => setCompanyDescription(e.target.value)}
                  rows={3}
                  placeholder="اكتب نبذة مميزة عن الشركة تظهر في صدر الصفحة التعريفية للزوار والمناديب..."
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Eye className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رؤية الشركة</span>
                </label>
                <textarea
                  value={vision}
                  onChange={e => setVision(e.target.value)}
                  rows={2}
                  placeholder="أدخل رؤية الشركة الاستراتيجية للمستقبل..."
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Target className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رسالة الشركة</span>
                </label>
                <textarea
                  value={mission}
                  onChange={e => setMission(e.target.value)}
                  rows={2}
                  placeholder="أدخل رسالة الشركة اللوجستية والمهنية للجمهور..."
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Award className="w-3.5 h-3.5 text-indigo-400" />
                  <span>أهداف الشركة الأساسية</span>
                </label>
                <textarea
                  value={goals}
                  onChange={e => setGoals(e.target.value)}
                  rows={2}
                  placeholder="اكتب أهداف ومؤشرات نجاح الشركة (مفصولة بفاصلة أو فقرات)..."
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Globe className="w-3.5 h-3.5 text-indigo-400" />
                  <span>موقع الشركة الإلكتروني</span>
                </label>
                <input
                  type="text"
                  value={website}
                  onChange={e => setWebsite(e.target.value)}
                  placeholder="www.company.com"
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Twitter className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رابط منصة إكس / تويتر</span>
                </label>
                <input
                  type="text"
                  value={socialTwitter}
                  onChange={e => setSocialTwitter(e.target.value)}
                  placeholder="https://twitter.com/username"
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Facebook className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رابط فيسبوك</span>
                </label>
                <input
                  type="text"
                  value={socialFacebook}
                  onChange={e => setSocialFacebook(e.target.value)}
                  placeholder="https://facebook.com/page"
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Instagram className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رابط إنستغرام</span>
                </label>
                <input
                  type="text"
                  value={socialInstagram}
                  onChange={e => setSocialInstagram(e.target.value)}
                  placeholder="https://instagram.com/username"
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                  <Linkedin className="w-3.5 h-3.5 text-indigo-400" />
                  <span>رابط لينكد إن</span>
                </label>
                <input
                  type="text"
                  value={socialLinkedin}
                  onChange={e => setSocialLinkedin(e.target.value)}
                  placeholder="https://linkedin.com/company/name"
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              {/* Welcoming Banner Styling and Sizing Controls */}
              <div className="md:col-span-2 border-t border-slate-800/80 pt-6 mt-4">
                <h4 className="text-xs font-black text-indigo-400 mb-4 flex items-center gap-1.5 uppercase tracking-wide">
                  <Image className="w-4 h-4 text-indigo-400 animate-pulse" />
                  <span>تخصيص بنر الترحيب الرئيسي بالصفحة التعريفية</span>
                </h4>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-950/40 p-4 rounded-xl border border-slate-800/80">
                  
                  {/* Banner image URL */}
                  <div className="md:col-span-2">
                    <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                      <Image className="w-3.5 h-3.5 text-indigo-400" />
                      <span>رابط صورة خلفية البانر الترحيبي (Banner Image URL)</span>
                    </label>
                    <input
                      type="text"
                      value={bannerBgImage}
                      onChange={e => setBannerBgImage(e.target.value)}
                      placeholder="أدخل رابط الصورة المباشر أو صورة Base64..."
                      className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                    <p className="text-[9px] text-slate-500 mt-1">
                      يتحكم هذا الرابط في الصورة الخلفية لبانر الترحيب. يمكنك استخدام روابط Unsplash أو أي رابط مباشر لصور سيارات فاخرة.
                    </p>
                  </div>

                  {/* Banner Height */}
                  <div>
                    <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                      <Maximize2 className="w-3.5 h-3.5 text-indigo-400" />
                      <span>ارتفاع البانر (Banner Height)</span>
                    </label>
                    <input
                      type="text"
                      value={bannerBgHeight}
                      onChange={e => setBannerBgHeight(e.target.value)}
                      placeholder="مثال: 520px أو 450px"
                      className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                    <p className="text-[9px] text-slate-500 mt-1">
                      يتحكم في طول البانر الترحيبي رأسياً بالبكسل. القيمة الموصى بها: 400px إلى 600px.
                    </p>
                  </div>

                  {/* Banner Width */}
                  <div>
                    <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                      <Maximize2 className="w-3.5 h-3.5 text-indigo-400" />
                      <span>عرض البانر (Banner Width)</span>
                    </label>
                    <input
                      type="text"
                      value={bannerBgWidth}
                      onChange={e => setBannerBgWidth(e.target.value)}
                      placeholder="مثال: 100% أو 90% أو max-w-5xl"
                      className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                    <p className="text-[9px] text-slate-500 mt-1">
                      يتحكم في عرض البانر الترحيبي أفقياً. يمكنك استخدام النسبة المئوية أو فئات Tailwind مثل max-w-7xl.
                    </p>
                  </div>

                  {/* Title Font Color */}
                  <div>
                    <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                      <Type className="w-3.5 h-3.5 text-indigo-400" />
                      <span>لون خط العنوان الرئيسي (Title Color)</span>
                    </label>
                    <div className="flex gap-2">
                      <input
                        type="color"
                        value={bannerTitleColor.startsWith('#') && bannerTitleColor.length === 7 ? bannerTitleColor : '#ffffff'}
                        onChange={e => setBannerTitleColor(e.target.value)}
                        className="w-8 h-8 rounded border border-slate-800 bg-slate-950 cursor-pointer shrink-0"
                      />
                      <input
                        type="text"
                        value={bannerTitleColor}
                        onChange={e => setBannerTitleColor(e.target.value)}
                        placeholder="#ffffff"
                        className="w-full text-xs px-3 py-1.5 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                      />
                    </div>
                  </div>

                  {/* Subtitle Font Color */}
                  <div>
                    <label className="block text-xs font-bold text-slate-400 mb-1 flex items-center gap-1">
                      <Type className="w-3.5 h-3.5 text-indigo-400" />
                      <span>لون خط الوصف الفرعي (Subtitle Color)</span>
                    </label>
                    <div className="flex gap-2">
                      <input
                        type="color"
                        value={bannerSubtitleColor.startsWith('#') && bannerSubtitleColor.length === 7 ? bannerSubtitleColor : '#e2e8f0'}
                        onChange={e => setBannerSubtitleColor(e.target.value)}
                        className="w-8 h-8 rounded border border-slate-800 bg-slate-950 cursor-pointer shrink-0"
                      />
                      <input
                        type="text"
                        value={bannerSubtitleColor}
                        onChange={e => setBannerSubtitleColor(e.target.value)}
                        placeholder="#e2e8f0"
                        className="w-full text-xs px-3 py-1.5 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                      />
                    </div>
                  </div>

                  {/* Text Background Backdrop Enable */}
                  <div className="flex items-center gap-2 mt-2 md:col-span-2">
                    <input
                      type="checkbox"
                      id="bannerTextBgEnable"
                      checked={bannerTextBgEnable}
                      onChange={e => setBannerTextBgEnable(e.target.checked)}
                      className="w-4 h-4 rounded text-indigo-600 bg-slate-950 border-slate-800 focus:ring-indigo-500 cursor-pointer"
                    />
                    <label htmlFor="bannerTextBgEnable" className="text-xs font-bold text-slate-300 cursor-pointer select-none">
                      تفعيل خلفية معتمة للنص الترحيبي لضمان وضوح الكلمات فوق صور البانر المختلفة
                    </label>
                  </div>

                  {/* Text Background Backdrop Opacity */}
                  {bannerTextBgEnable && (
                    <div className="md:col-span-2 bg-slate-950/60 p-3 rounded border border-slate-800/60 space-y-1.5 mt-1">
                      <div className="flex justify-between items-center text-[10px] font-bold text-slate-400">
                        <span>درجة تعتيم وشفافية خلفية النص</span>
                        <span className="text-indigo-400">{bannerTextBgOpacity}%</span>
                      </div>
                      <input
                        type="range"
                        min="10"
                        max="100"
                        step="5"
                        value={bannerTextBgOpacity}
                        onChange={e => setBannerTextBgOpacity(parseInt(e.target.value))}
                        className="w-full accent-indigo-500 h-1 bg-slate-950 rounded-lg cursor-pointer"
                      />
                      <p className="text-[9px] text-slate-500 leading-normal">
                        اسحب المؤشر لزيادة أو تقليل درجة تعتيم الخلفية الزجاجية للنص لضمان ملاءمته لألوان الخلفية.
                      </p>
                    </div>
                  )}

                </div>
              </div>

            </div>
          )}

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-800">
            <button
              type="submit"
              className="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition flex items-center gap-1.5 cursor-pointer shadow-md shadow-indigo-650/10"
            >
              <Save className="w-4 h-4" />
              <span>حفظ وتطبيق الإعدادات</span>
            </button>
          </div>

        </form>
        )}
      </div>

      {/* 2. DURABLE CLOUD DATABASE MANAGEMENT */}
      <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex flex-col justify-between space-y-4">
        <div>
          <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
            <Database className="w-4 h-4 text-indigo-400" />
            <span>النسخ الاحتياطي والاستعادة الدورية</span>
          </h3>
          <p className="text-[10px] text-slate-500 mt-0.5">توليد نسخة كاملة لقاعدة البيانات أو استرجاعها لضمان متانة البيانات (Disaster Recovery)</p>
        </div>

        {errorMsg && (
          <div className="p-2.5 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}

        <div className="space-y-3">
          
          {/* Backup Button */}
          <div className="p-3 rounded border border-slate-800 bg-slate-950 flex flex-col justify-between gap-2.5">
            <span className="text-xs font-bold text-slate-300 block">حفظ نسخة احتياطية فورية</span>
            <p className="text-[9px] text-slate-500 leading-normal">
              يقوم بتوليد ملف JSON يحتوي على كافة الفروع والمستخدمين والمخزون والحجوزات المسجلة للتحميل.
            </p>
            <button
              onClick={handleBackup}
              className="w-full py-2 text-xs font-bold text-slate-300 bg-slate-900 hover:bg-slate-850 border border-slate-800 rounded transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer"
            >
              <Database className="w-4 h-4 text-indigo-400" />
              <span>توليد وتحميل النسخة احتياطية</span>
            </button>
          </div>

          {/* Restore Button Input */}
          <div className="p-3 rounded border border-rose-500/10 bg-rose-500/5 flex flex-col justify-between gap-2.5">
            <span className="text-xs font-bold text-rose-400 block flex items-center gap-1">
              <ShieldAlert className="w-3.5 h-3.5" />
              <span>استعادة وتجاوز قاعدة البيانات</span>
            </span>
            <p className="text-[9px] text-slate-500 leading-normal">
              تحذير: استعادة ملف البيانات سيلغي ويستبدل كافة البيانات والتغيرات الحالية في المخزون تماماً.
            </p>
            <input
              type="file"
              ref={fileInputRef}
              onChange={handleRestore}
              className="hidden"
              accept=".json"
            />
            <button
              onClick={() => fileInputRef.current?.click()}
              disabled={loading}
              className="w-full py-2 text-xs font-bold text-rose-400 bg-slate-900 hover:bg-slate-850 border border-slate-800 rounded transition flex items-center justify-center gap-1.5 shadow-sm disabled:opacity-50 cursor-pointer"
            >
              <Upload className="w-4 h-4 text-rose-400" />
              <span>{loading ? 'جاري الاستيراد والاستبدال...' : 'رفع واستعادة قاعدة البيانات'}</span>
            </button>
          </div>

          {/* PHP Installer Wizard Simulation Card */}
          {onOpenInstallerSim && (
            <div className="p-3 rounded border border-indigo-500/10 bg-indigo-500/5 flex flex-col justify-between gap-2.5">
              <span className="text-xs font-bold text-indigo-400 block flex items-center gap-1">
                <Terminal className="w-3.5 h-3.5" />
                <span>معالج التثبيت والتهيئة (PHP / MySQL)</span>
              </span>
              <p className="text-[9px] text-slate-500 leading-normal">
                تشغيل معالج التثبيت التفاعلي لتجربة إنشاء الجداول، فحص بيئة PHP وتوليد ملف config.php أوتوماتيكياً.
              </p>
              <button
                type="button"
                onClick={onOpenInstallerSim}
                className="w-full py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-950/20 cursor-pointer"
              >
                <Terminal className="w-4 h-4 text-white" />
                <span>تشغيل معالج التثبيت الاحترافي</span>
              </button>
            </div>
          )}

        </div>
      </div>

    </div>
  );
}
