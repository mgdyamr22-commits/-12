/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { Globe, Search, ArrowLeftRight, CheckCircle2, AlertCircle, Save, Sparkles, Layout } from 'lucide-react';
import { SystemSettings, SEOSettingsMap } from '../types';

interface SEOSettingsPanelProps {
  settings: SystemSettings;
  onSaveSettings: (nextSettings: SystemSettings) => void;
}

const PAGE_KEYS = [
  { key: 'landing', label: 'الصفحة الرئيسية للزوار' },
  { key: 'dashboard', label: 'لوحة القيادة والمؤشرات' },
  { key: 'inventory', label: 'إدارة مخزن وصالة السيارات' },
  { key: 'sales', label: 'عقود المبيعات والأرشيف' },
  { key: 'users', label: 'الموظفين وحوكمة الصلاحيات' },
  { key: 'branches', label: 'إدارة الفروع والمعارض' },
  { key: 'settings', label: 'إعدادات النظام والتحليلات' },
  { key: 'customer-orders', label: 'صندوق طلبات العملاء المباشرة' },
  { key: 'logs', label: 'سجل عمليات وتدقيق النظام' }
];

export default function SEOSettingsPanel({ settings, onSaveSettings }: SEOSettingsPanelProps) {
  const [seoMap, setSeoMap] = useState<SEOSettingsMap>({});
  const [activePageKey, setActivePageKey] = useState<string>('landing');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [isSaved, setIsSaved] = useState(false);

  // Load SEO from settings
  useEffect(() => {
    if (settings && settings.seo) {
      setSeoMap(settings.seo);
    } else {
      // Setup fallback defaults
      const fallback: SEOSettingsMap = {};
      PAGE_KEYS.forEach(p => {
        fallback[p.key] = {
          title: `${p.label} | ${settings?.companyName || 'مؤسسة المخزون'}`,
          description: `عرض تفاصيل ومستندات ${p.label} بنظام المخزون برو المتكامل للتحكم بالمعارض اللوجستية.`
        };
      });
      setSeoMap(fallback);
    }
  }, [settings]);

  // Update inputs when active page key changes or seoMap is populated
  useEffect(() => {
    if (seoMap[activePageKey]) {
      setTitle(seoMap[activePageKey].title);
      setDescription(seoMap[activePageKey].description);
    } else {
      setTitle('');
      setDescription('');
    }
    setIsSaved(false);
  }, [activePageKey, seoMap]);

  const handleUpdateCurrentPage = () => {
    const updatedMap = {
      ...seoMap,
      [activePageKey]: {
        title: title.trim(),
        description: description.trim()
      }
    };
    setSeoMap(updatedMap);
    
    // Save to server
    onSaveSettings({
      ...settings,
      seo: updatedMap
    });

    setIsSaved(true);
    setTimeout(() => setIsSaved(false), 3000);
  };

  return (
    <div className="space-y-5 text-right font-sans">
      <div className="p-3 bg-slate-950 rounded-lg border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="space-y-1">
          <span className="text-xs font-bold text-indigo-400 block flex items-center gap-1">
            <Sparkles className="w-3.5 h-3.5" />
            <span>نظام حوكمة محركات البحث (SEO Engine)</span>
          </span>
          <p className="text-[10px] text-slate-500 leading-normal">
            اضبط وخصّص عناوين النوافذ والوصف التعريفي (Meta Description) لكل صفحة بشكل منفصل لتحسين الأرشفة وظهور الرابط بوضوح لمتصفحي الويب والعملاء.
          </p>
        </div>
        <div className="shrink-0 flex items-center gap-1 text-[10px] bg-slate-900 border border-slate-800 text-slate-400 px-2.5 py-1 rounded">
          <Globe className="w-3.5 h-3.5 text-indigo-400" />
          <span>منشئ أوسمة Meta ديناميكي</span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        
        {/* Left Column: Select Page List */}
        <div className="md:col-span-1 space-y-2">
          <label className="block text-xs font-bold text-slate-400">اختر الصفحة المستهدفة</label>
          <div className="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden divide-y divide-slate-800/60 max-h-[340px] overflow-y-auto">
            {PAGE_KEYS.map(p => {
              const isActive = activePageKey === p.key;
              return (
                <button
                  key={p.key}
                  type="button"
                  onClick={() => setActivePageKey(p.key)}
                  className={`w-full text-right px-3 py-2.5 text-[11px] font-bold transition-all flex items-center justify-between cursor-pointer ${isActive ? 'bg-indigo-600/10 text-white border-r-3 border-indigo-500' : 'text-slate-450 hover:bg-slate-900 hover:text-white'}`}
                >
                  <span>{p.label}</span>
                  <span className="font-mono text-[9px] text-slate-500">{p.key}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Right Column: Title, Description & Google Preview */}
        <div className="md:col-span-2 space-y-4 bg-slate-950/40 p-4 rounded-lg border border-slate-800/80">
          
          <div className="space-y-3">
            <div>
              <div className="flex justify-between items-center mb-1">
                <label className="block text-xs font-bold text-slate-300">عنوان الصفحة (Title Tag)</label>
                <span className="text-[10px] text-slate-500 font-mono">{title.length} / 60 حرف</span>
              </div>
              <input
                type="text"
                value={title}
                onChange={e => { setTitle(e.target.value); setIsSaved(false); }}
                placeholder="عنوان الصفحة التوضيحي..."
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
              />
            </div>

            <div>
              <div className="flex justify-between items-center mb-1">
                <label className="block text-xs font-bold text-slate-300">الوصف التعريفي (Meta Description)</label>
                <span className="text-[10px] text-slate-500 font-mono">{description.length} / 160 حرف</span>
              </div>
              <textarea
                value={description}
                onChange={e => { setDescription(e.target.value); setIsSaved(false); }}
                rows={3}
                placeholder="نبذة مختصرة وجذابة لتظهر في نتائج بحث جوجل ومواقع التواصل الاجتماعي..."
                className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed font-sans"
              />
            </div>
          </div>

          {/* Google Result Simulator */}
          <div className="border border-slate-800/60 bg-[#070b15]/60 rounded-lg p-3 space-y-2">
            <span className="text-[9px] font-bold text-slate-500 flex items-center gap-1 uppercase tracking-wide">
              <Search className="w-3 h-3 text-slate-500" />
              <span>محاكاة مظهر نتائج بحث Google المباشرة</span>
            </span>
            
            <div className="space-y-1 text-right font-sans" dir="rtl">
              {/* Fake url */}
              <div className="flex items-center gap-1.5 text-[10px] text-slate-400">
                <div className="w-4 h-4 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center text-[8px] text-slate-500 shrink-0">G</div>
                <div className="truncate text-slate-500">
                  {settings.website || 'www.al-makhzoun.com'} <span className="text-slate-600 font-mono">› {activePageKey}</span>
                </div>
              </div>

              {/* Fake title */}
              <h4 className="text-sm font-medium text-[#8ab4f8] hover:underline cursor-pointer leading-tight truncate">
                {title || 'يرجى كتابة عنوان للصفحة...'}
              </h4>

              {/* Fake description */}
              <p className="text-[11px] text-[#bdc1c6] leading-normal text-right font-medium">
                {description || 'يرجى إدخال الوصف التعريفي لمساعدة أرشفة جوجل على فهرسة محتوى صفحتك اللوجستية وعرضها للعملاء.'}
              </p>
            </div>
          </div>

          <div className="flex justify-between items-center pt-2.5 border-t border-slate-800/80">
            <div>
              {isSaved && (
                <span className="text-[11px] font-bold text-emerald-400 flex items-center gap-1 animate-fade-in">
                  <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                  <span>تم حفظ وتطبيق التعديل الفوري!</span>
                </span>
              )}
            </div>
            
            <button
              type="button"
              onClick={handleUpdateCurrentPage}
              className="px-4 py-1.5 text-[11px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition flex items-center gap-1.5 cursor-pointer shadow-md"
            >
              <Save className="w-3.5 h-3.5" />
              <span>حفظ وتثبيت الصفحة الحالية</span>
            </button>
          </div>

        </div>

      </div>
    </div>
  );
}
