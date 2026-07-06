/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { 
  Users, 
  Globe, 
  Monitor, 
  Smartphone, 
  Tablet, 
  Compass, 
  Clock, 
  History, 
  TrendingUp, 
  ArrowDownRight,
  Shield, 
  MapPin, 
  Activity, 
  Calendar,
  Search,
  Filter
} from 'lucide-react';
import { 
  ResponsiveContainer, 
  AreaChart, 
  Area, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  Legend, 
  BarChart, 
  Bar 
} from 'recharts';

interface AnalyticsDashboardProps {
  token: string;
}

export default function AnalyticsDashboard({ token }: AnalyticsDashboardProps) {
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState('');
  const [stats, setStats] = useState<any>(null);
  const [logSearch, setLogSearch] = useState('');
  const [logFilterAction, setLogFilterAction] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const logsPerPage = 10;

  useEffect(() => {
    fetchStats();
  }, [token]);

  const fetchStats = async () => {
    try {
      setLoading(true);
      setErrorMsg('');
      const res = await fetch('/api/analytics/stats', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (!res.ok) {
        throw new Error('فشل تحميل إحصائيات زوار الموقع والتحليلات.');
      }
      const data = await res.json();
      setStats(data);
    } catch (err: any) {
      setErrorMsg(err.message || 'فشل تحميل البيانات.');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="py-12 text-center text-slate-400 font-sans space-y-3">
        <div className="w-8 h-8 rounded-full border-2 border-indigo-500 border-t-transparent animate-spin mx-auto"></div>
        <span className="text-xs font-bold block">جاري تحميل تحليلات ومصفوفة حركة الزوار...</span>
      </div>
    );
  }

  if (errorMsg || !stats) {
    return (
      <div className="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-lg flex items-center gap-2 font-sans">
        <Shield className="w-4 h-4 shrink-0" />
        <span>{errorMsg || 'لا تتوفر إحصائيات حالياً.'}</span>
      </div>
    );
  }

  // Filter logs
  const filteredLogs = (stats.recentLogs || []).filter((log: any) => {
    const matchesSearch = 
      (log.ip || '').includes(logSearch) ||
      (log.browser || '').toLowerCase().includes(logSearch.toLowerCase()) ||
      (log.os || '').toLowerCase().includes(logSearch.toLowerCase()) ||
      (log.country || '').includes(logSearch) ||
      (log.path || '').toLowerCase().includes(logSearch.toLowerCase()) ||
      (log.action || '').toLowerCase().includes(logSearch.toLowerCase());
      
    const matchesFilter = logFilterAction === 'all' || 
      (logFilterAction === 'visiting' && (log.path === '/' || log.path === '/api')) ||
      (logFilterAction === 'internal' && log.path !== '/');

    return matchesSearch && matchesFilter;
  });

  // Pagination logic
  const totalPages = Math.ceil(filteredLogs.length / logsPerPage);
  const paginatedLogs = filteredLogs.slice((currentPage - 1) * logsPerPage, currentPage * logsPerPage);

  return (
    <div className="space-y-6 text-right font-sans">
      
      {/* KPIs Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        {/* KPI 1: Total Visits */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-[11px] text-slate-500 font-bold block">إجمالي الزيارات والصفحات المفتوحة</span>
            <div className="flex items-baseline gap-1.5">
              <h3 className="text-2xl font-black font-sans text-white">{stats.kpis.totalVisits}</h3>
              <span className="text-[9px] text-emerald-400 font-bold">نشط</span>
            </div>
            <p className="text-[9px] text-slate-500">تم تسجيلها من متصفحات المناديب والزوار</p>
          </div>
          <div className="w-9 h-9 rounded bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
            <Activity className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 2: Unique IPs */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-[11px] text-slate-500 font-bold block">الزوار الفريدين (Unique Visitors)</span>
            <div className="flex items-baseline gap-1.5">
              <h3 className="text-2xl font-black font-sans text-emerald-400">{stats.kpis.uniqueIps}</h3>
              <span className="text-[9px] text-slate-500 font-sans">IP متميز</span>
            </div>
            <p className="text-[9px] text-slate-500">مؤشر على اهتمام العملاء المتزايد</p>
          </div>
          <div className="w-9 h-9 rounded bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
            <Users className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 3: Traffic intensity */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-[11px] text-slate-500 font-bold block">النشاط التقريبي اليوم</span>
            <div className="flex items-baseline gap-1.5">
              <h3 className="text-2xl font-black font-sans text-indigo-400">{stats.kpis.activeToday}</h3>
              <span className="text-[9px] text-indigo-400 font-bold">نشط حالياً</span>
            </div>
            <p className="text-[9px] text-slate-500">معدل التحميل لكل ساعة</p>
          </div>
          <div className="w-9 h-9 rounded bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
            <Clock className="w-5 h-5" />
          </div>
        </div>

        {/* KPI 4: Average Session Duration */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex items-center justify-between">
          <div className="space-y-1">
            <span className="text-[11px] text-slate-500 font-bold block">متوسط وقت بقاء الزائر (Dwell Time)</span>
            <div className="flex items-baseline gap-1.5">
              <h3 className="text-base sm:text-lg lg:text-xl font-black font-sans text-amber-500 truncate">{stats.kpis.avgSessionFormatted || '85 ثانية'}</h3>
            </div>
            <p className="text-[9px] text-slate-500">معدل البقاء والبحث في الصالة لكل زائر</p>
          </div>
          <div className="w-9 h-9 rounded bg-amber-500/10 text-amber-500 flex items-center justify-center animate-pulse">
            <Clock className="w-5 h-5" />
          </div>
        </div>

      </div>

      {/* Recharts Timeline chart */}
      <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-3">
        <div>
          <h4 className="font-extrabold text-xs text-white flex items-center gap-1.5">
            <TrendingUp className="w-4 h-4 text-emerald-400" />
            <span>رسم بياني لحجم حركات المرور والزيارات اليومية</span>
          </h4>
          <p className="text-[10px] text-slate-500 mt-0.5">مخطط تفاعلي يحلل الزيارات على مدار الـ 7 أيام الماضية</p>
        </div>

        <div className="h-64 font-sans text-[10px]">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={stats.timeline} margin={{ top: 10, right: 10, left: -10, bottom: 0 }}>
              <defs>
                <linearGradient id="colorVisits" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#10b981" stopOpacity={0.4}/>
                  <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" />
              <XAxis dataKey="date" stroke="#64748b" />
              <YAxis stroke="#64748b" />
              <Tooltip 
                contentStyle={{ backgroundColor: '#020617', borderColor: '#1e293b', borderRadius: '8px' }}
                labelStyle={{ color: '#94a3b8', fontWeight: 'bold' }}
              />
              <Area type="monotone" name="عدد الزيارات والعمليات" dataKey="count" stroke="#10b981" strokeWidth={2.5} fillOpacity={1} fill="url(#colorVisits)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Device & Browser Analysis Bento Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        
        {/* Devices breakdown */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex flex-col justify-between">
          <div>
            <h5 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Monitor className="w-3.5 h-3.5 text-indigo-400" />
              <span>تحليل الأجهزة المستخدمة</span>
            </h5>
            <p className="text-[9px] text-slate-500 mt-0.5">توزيع متصفحي النظام حسب نوع الجهاز</p>
          </div>

          <div className="space-y-3 my-4">
            {/* Desktop */}
            <div className="space-y-1">
              <div className="flex justify-between items-center text-[10px] text-slate-350 font-bold">
                <span className="flex items-center gap-1"><Monitor className="w-3 h-3 text-indigo-400" />حواسب مكتبية</span>
                <span>{stats.devices.desktop || 0} زيارة</span>
              </div>
              <div className="h-1.5 bg-slate-900 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-indigo-500 rounded-full transition-all duration-1000" 
                  style={{ width: `${stats.kpis.totalVisits > 0 ? Math.round(((stats.devices.desktop || 0) / stats.kpis.totalVisits) * 100) : 0}%` }}
                ></div>
              </div>
            </div>

            {/* Mobile */}
            <div className="space-y-1">
              <div className="flex justify-between items-center text-[10px] text-slate-350 font-bold">
                <span className="flex items-center gap-1"><Smartphone className="w-3 h-3 text-emerald-450 text-emerald-400" />هواتف ذكية</span>
                <span>{stats.devices.mobile || 0} زيارة</span>
              </div>
              <div className="h-1.5 bg-slate-900 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-emerald-500 rounded-full transition-all duration-1000" 
                  style={{ width: `${stats.kpis.totalVisits > 0 ? Math.round(((stats.devices.mobile || 0) / stats.kpis.totalVisits) * 100) : 0}%` }}
                ></div>
              </div>
            </div>

            {/* Tablet */}
            <div className="space-y-1">
              <div className="flex justify-between items-center text-[10px] text-slate-350 font-bold">
                <span className="flex items-center gap-1"><Tablet className="w-3 h-3 text-amber-500" />أجهزة لوحية</span>
                <span>{stats.devices.tablet || 0} زيارة</span>
              </div>
              <div className="h-1.5 bg-slate-900 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-amber-500 rounded-full transition-all duration-1000" 
                  style={{ width: `${stats.kpis.totalVisits > 0 ? Math.round(((stats.devices.tablet || 0) / stats.kpis.totalVisits) * 100) : 0}%` }}
                ></div>
              </div>
            </div>
          </div>
        </div>

        {/* Operating Systems */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex flex-col justify-between">
          <div>
            <h5 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Monitor className="w-3.5 h-3.5 text-indigo-400" />
              <span>أنظمة التشغيل</span>
            </h5>
            <p className="text-[9px] text-slate-500 mt-0.5">أنظمة التشغيل الأكثر استخداماً لزيارة منصتنا</p>
          </div>

          <div className="space-y-2.5 my-4">
            {stats.os.map((item: any, idx: number) => {
              const max = stats.os[0]?.count || 1;
              const pct = Math.round((item.count / max) * 100);
              return (
                <div key={item.name} className="space-y-1">
                  <div className="flex justify-between items-center text-[10px] text-slate-300">
                    <span>{item.name}</span>
                    <span className="font-bold">{item.count}</span>
                  </div>
                  <div className="h-1 w-full bg-slate-900 rounded-full">
                    <div className="h-full bg-indigo-500 rounded-full" style={{ width: `${pct}%` }}></div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Browsers Analysis */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex flex-col justify-between">
          <div>
            <h5 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Compass className="w-3.5 h-3.5 text-indigo-400" />
              <span>المتصفحات النشطة</span>
            </h5>
            <p className="text-[9px] text-slate-500 mt-0.5">المتصفحات التي يستخدمها مناديب المبيعات والزوار</p>
          </div>

          <div className="space-y-2.5 my-4">
            {stats.browsers.map((item: any, idx: number) => {
              const max = stats.browsers[0]?.count || 1;
              const pct = Math.round((item.count / max) * 100);
              return (
                <div key={item.name} className="space-y-1">
                  <div className="flex justify-between items-center text-[10px] text-slate-300">
                    <span>{item.name}</span>
                    <span className="font-bold">{item.count}</span>
                  </div>
                  <div className="h-1 w-full bg-slate-900 rounded-full">
                    <div className="h-full bg-emerald-500 rounded-full" style={{ width: `${pct}%` }}></div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Geographic Distribution */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 flex flex-col justify-between">
          <div>
            <h5 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Globe className="w-3.5 h-3.5 text-indigo-400" />
              <span>التوزيع الجغرافي للزوار</span>
            </h5>
            <p className="text-[9px] text-slate-500 mt-0.5">الدول والمناطق الأكثر طلباً وحركة</p>
          </div>

          <div className="space-y-2.5 my-4">
            {stats.countries.map((item: any) => {
              const max = stats.countries[0]?.count || 1;
              const pct = Math.round((item.count / max) * 100);
              return (
                <div key={item.name} className="space-y-1">
                  <div className="flex justify-between items-center text-[10px] text-slate-300 font-bold">
                    <span className="flex items-center gap-1">
                      <MapPin className="w-3 h-3 text-rose-500 shrink-0" />
                      <span>{item.name}</span>
                    </span>
                    <span>{item.count} زيارة</span>
                  </div>
                  <div className="h-1 w-full bg-slate-900 rounded-full">
                    <div className="h-full bg-rose-500 rounded-full" style={{ width: `${pct}%` }}></div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

      </div>

      {/* ADVANCED TRACKING: TRAFFIC SOURCES, USER JOURNEYS & DWELL TIMES */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        {/* Widget 1: Traffic Sources (مصادر الزيارات) */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-4 lg:col-span-1">
          <div>
            <h4 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Compass className="w-4 h-4 text-emerald-450 text-emerald-400" />
              <span>مصادر زيارات المعرض والمنصة (Traffic Referrers)</span>
            </h4>
            <p className="text-[9px] text-slate-500 mt-0.5">تحليل القنوات ومحركات البحث ومواقع التواصل التي جلبت الزوار للخدمة</p>
          </div>

          <div className="h-44 font-sans text-[9px] -mr-4">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={stats.trafficSources || []} layout="vertical" margin={{ top: 5, right: 15, left: 15, bottom: 5 }}>
                <CartesianGrid strokeDasharray="2 2" stroke="#1e293b" />
                <XAxis type="number" stroke="#64748b" />
                <YAxis dataKey="name" type="category" stroke="#64748b" width={90} />
                <Tooltip 
                  contentStyle={{ backgroundColor: '#020617', borderColor: '#1e293b', borderRadius: '8px' }}
                  labelStyle={{ color: '#94a3b8', fontWeight: 'bold' }}
                />
                <Bar dataKey="count" fill="#10b981" name="عدد الزيارات" radius={[0, 4, 4, 0]} barSize={12} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Widget 2: Visitor Paths / User Journeys (مسارات الزوار) */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-4 lg:col-span-1">
          <div>
            <h4 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Activity className="w-4 h-4 text-indigo-400" />
              <span>مسارات حركات الزوار المتتالية (Visitor Journeys)</span>
            </h4>
            <p className="text-[9px] text-slate-500 mt-0.5">سير التنقل والمسارات الأكثر استخداماً بين صفحات المعرض الحية</p>
          </div>

          <div className="space-y-2.5 max-h-48 overflow-y-auto pr-1">
            {(stats.visitorPaths || []).map((path: any, idx: number) => (
              <div key={idx} className="p-2.5 bg-slate-900/60 border border-slate-800/60 rounded-lg flex items-center justify-between gap-3 hover:border-indigo-500/30 transition-all">
                <div className="flex-1 min-w-0 text-left">
                  <div className="flex items-center gap-1 text-[10px] text-slate-350 font-bold tracking-wide truncate" dir="ltr">
                    {path.name.split(' ➜ ').map((part: string, pIdx: number) => (
                      <React.Fragment key={pIdx}>
                        {pIdx > 0 && <span className="text-indigo-400 font-black px-1">➜</span>}
                        <span className={`${pIdx === 0 ? 'text-slate-400' : 'text-indigo-300 font-semibold bg-indigo-500/5 px-1 py-0.5 rounded border border-indigo-500/10'}`}>{part}</span>
                      </React.Fragment>
                    ))}
                  </div>
                </div>
                <div className="shrink-0 flex items-center gap-1 bg-slate-950 px-2 py-1 rounded border border-slate-800">
                  <span className="text-[9px] text-slate-500">معدل:</span>
                  <span className="text-[10px] font-black text-white font-sans">{path.count}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Widget 3: Page Dwell Times (متوسط وقت بقاء الزائر بالصفحة) */}
        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-4 lg:col-span-1">
          <div>
            <h4 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <Clock className="w-4 h-4 text-amber-500" />
              <span>أوقات البقاء ومتوسط البقاء بالصفحة (Dwell Times)</span>
            </h4>
            <p className="text-[9px] text-slate-500 mt-0.5">تحليل متوسط الوقت الذي يقضيه العميل متصفحاً لكل رابط</p>
          </div>

          <div className="space-y-2.5 max-h-48 overflow-y-auto pr-1">
            {(stats.dwellTimes || []).map((dwell: any, idx: number) => {
              const maxVal = Math.max(...(stats.dwellTimes || []).map((d: any) => d.count), 1);
              const pct = Math.round((dwell.count / maxVal) * 100);
              
              const pageLabels: { [key: string]: string } = {
                '/': 'الرئيسية للعملاء',
                '/dashboard': 'لوحة القيادة الإحصائية',
                '/inventory': 'معرض وصالة السيارات',
                '/sales': 'أرشيف عقود المبيعات',
                '/users': 'حوكمة الصلاحيات والمناديب',
                '/branches': 'قائمة المعارض وفروعها',
                '/settings': 'إعدادات المنصة والسيو',
                '/customer-orders': 'طلبات العملاء الحية',
                '/logs': 'سجل العمليات الأمني المطور'
              };

              return (
                <div key={idx} className="space-y-1.5 p-2 bg-slate-900/40 rounded border border-slate-850">
                  <div className="flex justify-between items-center text-[10px]">
                    <span className="font-bold text-slate-350 flex items-center gap-1">
                      <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                      <span>{pageLabels[dwell.name] || dwell.name}</span>
                    </span>
                    <span className="font-mono text-amber-500 font-bold">{dwell.formatted}</span>
                  </div>
                  <div className="h-1 bg-slate-950 rounded-full overflow-hidden w-full">
                    <div className="h-full bg-gradient-to-r from-amber-500 to-amber-400 rounded-full" style={{ width: `${pct}%` }}></div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

      </div>

      {/* Pages View Count Breakdown */}
      <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-4">
        <div>
          <h4 className="font-extrabold text-xs text-white">تحليل الصفحات والروابط المزارة (Pages Visited)</h4>
          <p className="text-[10px] text-slate-500 mt-0.5">مقارنة الروابط الأكثر طلباً من قبل الزوار والمناديب وتوزيع العمليات عليها</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {stats.pages.map((p: any) => {
            const pageLabels: { [key: string]: string } = {
              '/': 'الصفحة الرئيسية التعريفية للعملاء',
              '/dashboard': 'لوحة القيادة الإحصائية للمشرفين',
              '/inventory': 'معرض السيارات وصالة العرض النشطة',
              '/sales': 'أرشيف عقود المبيعات والفواتير',
              '/users': 'حوكمة الصلاحيات وإدارة المناديب',
              '/branches': 'قائمة المعارض ومستودعات الجمارك',
              '/settings': 'إعدادات المنصة والسيو العام',
              '/customer-orders': 'صندوق طلبات العملاء المباشرة',
              '/logs': 'سجل العمليات الأمني المطور'
            };

            return (
              <div key={p.name} className="p-3 bg-slate-900 border border-slate-800 rounded-lg flex flex-col justify-between space-y-2">
                <div className="space-y-1">
                  <span className="font-sans font-bold text-[11px] text-indigo-400 block truncate">{p.name}</span>
                  <span className="text-[10px] text-slate-450 block font-bold">{pageLabels[p.name] || 'عمليات مساعدة / غير محدد'}</span>
                </div>
                <div className="flex justify-between items-center text-[10px] text-slate-350 pt-2 border-t border-slate-850">
                  <span>عدد الطلبات:</span>
                  <span className="font-sans font-black text-white bg-slate-950 px-2 py-0.5 rounded border border-slate-800">{p.count} طلب</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Raw Traffic Stream Logs Table */}
      <div className="bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-4">
        
        {/* Filters and Search toolbar */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
          <div>
            <h4 className="font-extrabold text-xs text-white flex items-center gap-1.5">
              <History className="w-4 h-4 text-indigo-400" />
              <span>دفق ومصفوفة سجلات تتبع حركة المرور والزوار الحية</span>
            </h4>
            <p className="text-[10px] text-slate-500 mt-0.5">مراقبة حية وتدقيق دقيق لعنوان الآي بي والمتصفح والدولة وموقع الحركة لكل طلب وارد</p>
          </div>

          <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            {/* Search Input */}
            <div className="relative flex-1 sm:flex-initial">
              <Search className="absolute right-2.5 top-2.5 w-3.5 h-3.5 text-slate-500" />
              <input
                type="text"
                placeholder="ابحث بالـ IP، المتصفح، أو العملية..."
                value={logSearch}
                onChange={e => { setLogSearch(e.target.value); setCurrentPage(1); }}
                className="w-full sm:w-56 text-[10px] font-medium pr-8 pl-3 py-2 rounded bg-slate-900 border border-slate-800 text-slate-200 focus:outline-none focus:border-indigo-500"
              />
            </div>

            {/* Filter select */}
            <div className="relative">
              <select
                value={logFilterAction}
                onChange={e => { setLogFilterAction(e.target.value); setCurrentPage(1); }}
                className="text-[10px] px-3 py-2 rounded bg-slate-900 border border-slate-800 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer font-bold"
              >
                <option value="all">كل دفق الحركات</option>
                <option value="visiting">زيارات الصفحة الرئيسية فقط</option>
                <option value="internal">حركات المناديب والمنسقين الداخلية</option>
              </select>
            </div>
          </div>
        </div>

        {/* Logs Stream Table */}
        <div className="overflow-x-auto border border-slate-850 rounded-lg">
          <table className="w-full text-right text-[10px] divide-y divide-slate-850">
            <thead className="bg-slate-900 text-slate-400 font-bold">
              <tr>
                <th className="px-3 py-2.5">التوقيت والتاريخ</th>
                <th className="px-3 py-2.5">عنوان بروتوكول الإنترنت IP Address</th>
                <th className="px-3 py-2.5">الدولة وموقع الاتصال</th>
                <th className="px-3 py-2.5">نظام التشغيل والمتصفح</th>
                <th className="px-3 py-2.5">الرابط المزار Path</th>
                <th className="px-3 py-2.5">العملية المنجزة</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-850 text-slate-300">
              {paginatedLogs.map((log: any, idx: number) => {
                const dateFormated = log.createdAt ? new Date(log.createdAt).toLocaleString('ar-SA') : 'غير متوفر';
                return (
                  <tr key={idx} className="hover:bg-slate-900/50 transition-colors">
                    <td className="px-3 py-2.5 font-sans font-medium text-slate-450">{dateFormated}</td>
                    <td className="px-3 py-2.5 font-sans font-black text-white">{log.ip}</td>
                    <td className="px-3 py-2.5 font-bold">
                      <span className="inline-flex items-center gap-1 text-slate-250">
                        <MapPin className="w-3 h-3 text-rose-500 shrink-0" />
                        <span>{log.country || 'المملكة العربية السعودية'}</span>
                      </span>
                    </td>
                    <td className="px-3 py-2.5 font-medium">
                      <span className="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400 text-[9px] font-mono mr-1">
                        {log.os || 'Windows'}
                      </span>
                      <span className="text-slate-300 font-sans">
                        {log.browser || 'Chrome'}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 font-sans text-indigo-400 font-bold">{log.path}</td>
                    <td className="px-3 py-2.5">
                      <span className={`px-2 py-0.5 rounded font-bold text-[9px] ${log.path === '/' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'}`}>
                        {log.action || 'استعلام'}
                      </span>
                    </td>
                  </tr>
                );
              })}
              {paginatedLogs.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-8 text-slate-500 font-bold">
                    لا تتوفر أي سجلات تتبع لحركات المرور والزوار تطابق معايير البحث والفلترة المحددة.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination bar */}
        {totalPages > 1 && (
          <div className="flex justify-between items-center pt-2 text-[10px]">
            <span className="text-slate-500 font-bold">الصفحة {currentPage} من {totalPages} (إجمالي {filteredLogs.length} سجل مصفى)</span>
            <div className="flex gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                className="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 hover:bg-slate-800 disabled:opacity-40 text-white transition font-bold cursor-pointer disabled:cursor-not-allowed"
              >
                السابق
              </button>
              <button
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                className="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 hover:bg-slate-800 disabled:opacity-40 text-white transition font-bold cursor-pointer disabled:cursor-not-allowed"
              >
                التالي
              </button>
            </div>
          </div>
        )}

      </div>

    </div>
  );
}
