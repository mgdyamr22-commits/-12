/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useRef, useState } from 'react';
import { 
  LayoutDashboard, 
  Car, 
  CalendarDays, 
  Users, 
  MapPin, 
  FileBarChart2, 
  History, 
  Settings, 
  LogOut,
  UserCheck,
  Camera,
  ShieldCheck,
  Bell,
  Sun,
  Moon,
  Menu,
  X,
  ChevronDown,
  Globe,
  DollarSign,
  Inbox,
  ArrowLeftRight,
  Mail
} from 'lucide-react';
import { UserRole, User, Notification } from '../types';
import { Language } from '../i18n/translations';

interface TopNavProps {
  activeTab: string;
  setActiveTab: (tab: string) => void;
  userRole: UserRole;
  userName: string;
  onLogout: () => void;
  logo?: string;
  companyName?: string;
  user?: User | null;
  onUpdateAvatar?: (avatar: string) => void;
  viewMode?: 'card' | 'table';
  setViewMode?: (mode: 'card' | 'table') => void;
  lang: Language;
  setLang: (lang: Language) => void;
  darkMode: boolean;
  toggleDarkMode: () => void;
  notifications: Notification[];
  handleMarkNotificationsRead: (id?: string) => void;
}

export default function TopNav({ 
  activeTab, 
  setActiveTab, 
  userRole, 
  userName, 
  onLogout, 
  logo, 
  companyName,
  user,
  onUpdateAvatar,
  viewMode,
  setViewMode,
  lang,
  setLang,
  darkMode,
  toggleDarkMode,
  notifications,
  handleMarkNotificationsRead
}: TopNavProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const handleAvatarChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      alert('حجم الصورة الشخصية يجب أن يكون أقل من 2 ميجابايت.');
      return;
    }

    const reader = new FileReader();
    reader.onload = async () => {
      const base64 = reader.result as string;
      setUploading(true);
      try {
        const token = localStorage.getItem('car_stock_token');
        const res = await fetch('/api/users/profile-picture', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({ avatar: base64 })
        });
        if (res.ok) {
          const data = await res.json();
          if (onUpdateAvatar) {
            onUpdateAvatar(data.avatar);
          }
          alert('تم تحديث الصورة الشخصية بنجاح');
        } else {
          const data = await res.json();
          alert(data.error || 'فشل تحديث الصورة الشخصية');
        }
      } catch (err) {
        console.error('Error uploading avatar:', err);
        alert('حدث خطأ أثناء رفع الصورة الشخصية.');
      } finally {
        setUploading(false);
      }
    };
    reader.readAsDataURL(file);
  };

  const menuItems = userRole === 'representative' ? [
    { id: 'inventory-table', label: 'مخزن السيارات', icon: Car },
    { id: 'inventory-card', label: 'صالة عرض السيارات', icon: LayoutDashboard },
    { id: 'sales', label: 'قسم المبيعات', icon: DollarSign },
  ] : [
    { id: 'dashboard', label: lang === 'ar' ? 'لوحة التحكم' : 'Dashboard', icon: LayoutDashboard },
    { id: 'customer-orders', label: lang === 'ar' ? 'صندوق طلبات العملاء' : 'Customer Orders', icon: Inbox },
    { id: 'contact-inquiries', label: lang === 'ar' ? 'رسائل اتصل بنا' : 'Contact Inquiries', icon: Mail },
    { id: 'rep-monitoring', label: lang === 'ar' ? 'رقابة ومتابعة المناديب' : 'Rep Monitoring', icon: ShieldCheck },
    { id: 'inventory', label: 'مخزن وصالة السيارات', icon: Car },
    { id: 'sales', label: 'قسم المبيعات', icon: DollarSign },
    { id: 'reservations', label: 'إدارة الحجوزات', icon: CalendarDays },
    { id: 'users', label: 'إدارة المستخدمين', icon: Users },
    { id: 'branches', label: 'الفروع والمعارض', icon: MapPin },
    { id: 'transfers', label: 'التحويلات بين الفروع', icon: ArrowLeftRight },
    { id: 'reports', label: 'التقارير والتصدير', icon: FileBarChart2 },
    { id: 'logs', label: 'سجل العمليات', icon: History },
    { id: 'settings', label: 'الإعدادات العامة', icon: Settings },
  ];

  const handleItemClick = (id: string) => {
    if (id === 'inventory-table') {
      setActiveTab('inventory');
      if (setViewMode) setViewMode('table');
    } else if (id === 'inventory-card') {
      setActiveTab('inventory');
      if (setViewMode) setViewMode('card');
    } else {
      setActiveTab(id);
    }
    setMobileMenuOpen(false);
  };

  const checkIsActive = (id: string) => {
    if (id === 'inventory-table') {
      return activeTab === 'inventory' && viewMode === 'table';
    }
    if (id === 'inventory-card') {
      return activeTab === 'inventory' && viewMode === 'card';
    }
    return activeTab === id;
  };

  return (
    <header className="bg-slate-900 border-b border-slate-850 lg:border-b-0 lg:border-l lg:border-slate-800/80 text-slate-100 sticky top-0 z-40 w-full lg:w-64 lg:h-screen lg:flex lg:flex-col transition-all duration-300 font-sans shadow-lg select-none shrink-0 overflow-y-auto">
      {/* Container - flex-row on mobile, flex-col on desktop */}
      <div className="px-4 lg:px-5 py-3 lg:py-6 flex flex-row lg:flex-col justify-between items-center lg:items-stretch lg:h-full lg:min-h-0 w-full">
        
        {/* Logo and Brand */}
        <div className="flex items-center lg:flex-col lg:items-center gap-3 lg:gap-3.5 shrink-0 lg:mb-6 lg:text-center">
          {logo ? (
            <div className="w-8 h-8 lg:w-14 lg:h-14 rounded bg-slate-950 flex items-center justify-center overflow-hidden border border-slate-800 lg:rounded-xl">
              <img src={logo} alt="Logo" className="w-full h-full object-contain" />
            </div>
          ) : (
            <div className="w-8 h-8 lg:w-12 lg:h-12 rounded bg-indigo-600 flex items-center justify-center text-white font-extrabold text-lg shrink-0 lg:rounded-xl shadow-md">
              <Car className="w-4 h-4 lg:w-6 lg:h-6" />
            </div>
          )}
          <div className="text-right lg:text-center">
            <h1 className="font-extrabold text-[12px] lg:text-[13px] text-white tracking-tight leading-none truncate max-w-[150px] lg:max-w-none">
              {companyName || 'مؤسسة الرياض لتجارة السيارات'}
            </h1>
            <span className="text-[8px] text-indigo-400 font-mono block mt-1 tracking-wider uppercase">ALMAKHZOUN PRO</span>
          </div>
        </div>

        {/* Navigation Links for Desktop (as a vertical column!) */}
        <nav className="hidden lg:flex flex-col gap-1 flex-1 w-full overflow-y-auto scrollbar-none py-4 border-t border-b border-slate-800/40 my-1">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const isActive = checkIsActive(item.id);
            return (
              <button
                key={item.id}
                onClick={() => handleItemClick(item.id)}
                className={`flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg text-[11px] font-black transition-all w-full cursor-pointer text-right shrink-0 ${
                  isActive 
                    ? 'bg-indigo-650 text-white shadow shadow-indigo-600/10' 
                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                }`}
              >
                <Icon className="w-3.5 h-3.5 shrink-0 text-indigo-400" />
                <span className="truncate">{item.label}</span>
              </button>
            );
          })}
        </nav>

        {/* Action Buttons & Profile Panel (stacked on desktop!) */}
        <div className="hidden lg:flex flex-col gap-3.5 pt-4 mt-auto w-full">
          
          {/* Quick theme & lang toggles */}
          <div className="flex items-center justify-between gap-2">
            <button 
              onClick={() => setLang(lang === 'ar' ? 'en' : 'ar')}
              className="flex-1 p-2 rounded bg-slate-950 border border-slate-850 text-slate-450 hover:text-white text-[10px] font-black transition cursor-pointer flex justify-center items-center gap-1.5"
              title="Toggle Language / تبديل اللغة"
            >
              <Globe className="w-3.5 h-3.5 text-indigo-400" />
              <span>{lang === 'ar' ? 'English' : 'العربية'}</span>
            </button>

            <button 
              onClick={toggleDarkMode}
              className="flex-1 p-2 rounded bg-slate-950 border border-slate-850 text-slate-450 hover:text-white text-[10px] font-black transition cursor-pointer flex justify-center items-center gap-1.5"
              title="تبديل الإضاءة"
            >
              {darkMode ? <Sun className="w-3.5 h-3.5 text-amber-400" /> : <Moon className="w-3.5 h-3.5 text-indigo-400" />}
              <span>{lang === 'ar' ? 'المظهر' : 'Theme'}</span>
            </button>
          </div>

          {/* Notifications Trigger & Profile Stack */}
          <div className="flex flex-col gap-2">
            {/* Notification Bell */}
            <div className="relative w-full">
              <button 
                onClick={() => { setNotificationsOpen(!notificationsOpen); setProfileOpen(false); }}
                className="w-full flex items-center justify-between p-2 rounded bg-slate-950 border border-slate-855 text-slate-400 hover:text-white transition cursor-pointer text-[10px] font-black"
              >
                <div className="flex items-center gap-2">
                  <Bell className="w-3.5 h-3.5 text-indigo-400 animate-pulse" />
                  <span>{lang === 'ar' ? 'التنبيهات الحية' : 'Live Notifications'}</span>
                </div>
                {notifications.some(n => !n.isRead) ? (
                  <span className="px-1.5 py-0.5 rounded-full text-[8px] bg-rose-500 text-white font-sans font-bold animate-pulse">
                    {notifications.filter(n => !n.isRead).length}
                  </span>
                ) : (
                  <span className="text-[9px] text-slate-600 font-mono">0</span>
                )}
              </button>

              {notificationsOpen && (
                <div className="absolute bottom-full mb-2 right-0 left-0 w-72 bg-slate-900 border border-slate-800 rounded-lg shadow-2xl overflow-hidden z-50 animate-fade-in text-right">
                  <div className="p-3 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                    <span className="font-extrabold text-xs text-white">الإشعارات الحية</span>
                    <button 
                      onClick={() => handleMarkNotificationsRead()}
                      className="text-[10px] text-indigo-400 font-bold hover:underline cursor-pointer"
                    >
                      مسح الكل
                    </button>
                  </div>
                  <div className="divide-y divide-slate-800 max-h-56 overflow-y-auto">
                    {notifications.map(n => (
                      <div 
                        key={n.id} 
                        onClick={() => handleMarkNotificationsRead(n.id)}
                        className={`p-3 text-right cursor-pointer hover:bg-slate-800/50 transition ${!n.isRead ? 'bg-indigo-600/5' : ''}`}
                      >
                        <h5 className="font-bold text-xs text-slate-200">{n.title}</h5>
                        <p className="text-[10px] text-slate-400 mt-0.5">{n.message}</p>
                        <span className="text-[9px] text-slate-500 mt-0.5 font-mono block">
                          {new Date(n.createdAt).toLocaleTimeString('ar-SA')}
                        </span>
                      </div>
                    ))}
                    {notifications.length === 0 && (
                      <p className="text-center py-8 text-[10px] text-slate-500">لا يوجد إشعارات غير مقروءة حالياً.</p>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Profile Dropdown for Desktop */}
            <div className="relative w-full">
              <button 
                onClick={() => { setProfileOpen(!profileOpen); setNotificationsOpen(false); }}
                className="w-full flex items-center justify-between p-1.5 rounded bg-slate-950 border border-slate-850 hover:border-indigo-500/20 transition cursor-pointer"
              >
                <div className="flex items-center gap-2 overflow-hidden">
                  <div className="w-7 h-7 rounded-full bg-slate-850 border border-slate-750 overflow-hidden flex items-center justify-center shrink-0">
                    {user?.avatar ? (
                      <img src={user.avatar} alt={userName} className="w-full h-full object-cover" />
                    ) : (
                      <UserCheck className="w-3.5 h-3.5 text-indigo-400" />
                    )}
                  </div>
                  <div className="text-right overflow-hidden">
                    <span className="text-[10px] font-black text-slate-200 block truncate max-w-[110px]">{userName}</span>
                    <span className="text-[8px] text-indigo-400 block font-mono">
                      {userRole === 'admin' ? 'مدير' : 'مندوب'}
                    </span>
                  </div>
                </div>
                <ChevronDown className={`w-3.5 h-3.5 text-slate-500 transition-transform shrink-0 ${profileOpen ? 'rotate-180' : ''}`} />
              </button>

              {profileOpen && (
                <div className="absolute bottom-full mb-2 right-0 left-0 bg-slate-900 border border-slate-800 rounded-lg shadow-2xl overflow-hidden z-50 animate-fade-in text-right">
                  <div className="p-3 bg-slate-950/60 border-b border-slate-800 text-center">
                    <div className="w-12 h-12 rounded-full bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center mx-auto mb-1.5 relative group">
                      {user?.avatar ? (
                        <img src={user.avatar} alt={userName} className="w-full h-full object-cover" />
                      ) : (
                        <UserCheck className="w-5 h-5 text-indigo-400" />
                      )}
                      <button 
                        onClick={() => fileInputRef.current?.click()}
                        className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white cursor-pointer"
                        title="تحديث الصورة"
                      >
                        <Camera className="w-3.5 h-3.5" />
                      </button>
                    </div>
                    <span className="text-xs font-bold text-white block">{userName}</span>
                    <span className="text-[9px] text-slate-500 font-mono block mt-0.5">{userRole === 'admin' ? 'مدير عام النظام' : 'مندوب المبيعات المعين'}</span>
                  </div>

                  <div className="p-1.5 space-y-0.5">
                    <button
                      onClick={() => fileInputRef.current?.click()}
                      disabled={uploading}
                      className="w-full flex items-center gap-2.5 px-3 py-2 rounded text-right text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition cursor-pointer"
                    >
                      <Camera className="w-4 h-4 text-slate-400" />
                      <span>{uploading ? 'جاري الرفع...' : 'تغيير الصورة الشخصية'}</span>
                    </button>

                    <input
                      type="file"
                      ref={fileInputRef}
                      onChange={handleAvatarChange}
                      accept="image/*"
                      className="hidden"
                    />

                    <button
                      onClick={onLogout}
                      className="w-full flex items-center gap-2.5 px-3 py-2 rounded text-right text-xs font-bold text-rose-450 text-rose-400 hover:bg-rose-500/10 transition cursor-pointer border-t border-slate-800/80 mt-1"
                    >
                      <LogOut className="w-4 h-4 shrink-0" />
                      <span>تسجيل الخروج</span>
                    </button>
                  </div>
                </div>
              )}
            </div>

          </div>
        </div>

        {/* Action Buttons & Profile for Mobile (as a horizontal row!) */}
        <div className="flex lg:hidden items-center gap-2">
          {/* Mobile Buttons */}
          <button 
            onClick={() => { setMobileMenuOpen(!mobileMenuOpen); }}
            className="p-2 rounded bg-slate-950 border border-slate-800 text-slate-400 hover:text-white transition cursor-pointer"
          >
            {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
        </div>

      </div>

      {/* Mobile Collapse Menu Panel */}
      {mobileMenuOpen && (
        <div className="lg:hidden border-t border-slate-850 bg-slate-950 py-3.5 px-4 space-y-3 animate-fade-in text-right">
          
          {/* Quick info banner */}
          <div className="p-3 bg-slate-900 rounded-lg border border-slate-800 flex items-center gap-2.5 justify-between">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center">
                {user?.avatar ? (
                  <img src={user.avatar} alt={userName} className="w-full h-full object-cover" />
                ) : (
                  <UserCheck className="w-3.5 h-3.5 text-indigo-400" />
                )}
              </div>
              <div className="text-right">
                <span className="text-xs font-bold text-white block">{userName}</span>
                <span className="text-[9px] text-slate-400 font-mono block">{userRole === 'admin' ? 'المدير' : 'المندوب'}</span>
              </div>
            </div>

            <div className="flex gap-1.5">
              <button 
                onClick={() => setLang(lang === 'ar' ? 'en' : 'ar')}
                className="p-1.5 rounded bg-slate-950 border border-slate-800 text-slate-400 text-xs font-bold"
              >
                {lang === 'ar' ? 'EN' : 'AR'}
              </button>
              <button 
                onClick={toggleDarkMode}
                className="p-1.5 rounded bg-slate-950 border border-slate-800 text-slate-400"
              >
                {darkMode ? <Sun className="w-3.5 h-3.5 text-amber-400" /> : <Moon className="w-3.5 h-3.5 text-indigo-400" />}
              </button>
            </div>
          </div>

          {/* Menu Links */}
          <div className="space-y-1">
            {menuItems.map((item) => {
              const Icon = item.icon;
              const isActive = checkIsActive(item.id);
              return (
                <button
                  key={item.id}
                  onClick={() => handleItemClick(item.id)}
                  className={`w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded text-xs font-extrabold transition cursor-pointer ${
                    isActive 
                      ? 'bg-indigo-600 text-white' 
                      : 'text-slate-400 hover:bg-slate-900 hover:text-slate-200'
                  }`}
                >
                  <Icon className="w-4 h-4 text-indigo-400" />
                  <span>{item.label}</span>
                </button>
              );
            })}
          </div>

          {/* Logout Button */}
          <button
            onClick={onLogout}
            className="w-full flex items-center gap-2 px-3.5 py-2.5 rounded text-rose-400 font-bold text-xs bg-rose-500/5 border border-rose-500/10 hover:bg-rose-500 hover:text-white transition cursor-pointer"
          >
            <LogOut className="w-4 h-4" />
            <span>تسجيل الخروج من النظام</span>
          </button>

        </div>
      )}
    </header>
  );
}
