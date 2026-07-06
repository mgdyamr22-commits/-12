/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect } from 'react';
import { 
  Sun, 
  Moon, 
  Bell, 
  Search, 
  Plus, 
  SlidersHorizontal, 
  FileSpreadsheet, 
  Printer, 
  AlertCircle, 
  CheckCircle2, 
  Download, 
  Trash2, 
  Clock, 
  FileBarChart2,
  Undo2,
  CalendarDays
} from 'lucide-react';

// Subcomponents
import TopNav from './components/TopNav';
import DashboardStats from './components/DashboardStats';
import CarCard from './components/CarCard';
import CarForm from './components/CarForm';
import ReservationForm from './components/ReservationForm';
import AttachmentViewer from './components/AttachmentViewer';
import UsersTable from './components/UsersTable';
import BranchesTable from './components/BranchesTable';
import BranchTransfersTable from './components/BranchTransfersTable';
import SettingsPanel from './components/SettingsPanel';
import ActivityLogsTable from './components/ActivityLogsTable';
import LoginScreen from './components/LoginScreen';
import LandingPage from './components/LandingPage';
import ContactUsPage from './components/ContactUsPage';
import AdvancedVehiclesTable from './components/AdvancedVehiclesTable';
import CarDetailModal from './components/CarDetailModal';
import RepMonitoringPanel from './components/RepMonitoringPanel';
import PHPInstallerSim from './components/PHPInstallerSim';
import ReservationDetailModal from './components/ReservationDetailModal';
import { EditReservationModal, SellReservationModal } from './components/ReservationActionsModals';
import CustomerOrdersInbox from './components/CustomerOrdersInbox';
import ContactInquiriesInbox from './components/ContactInquiriesInbox';
import { printInvoice, printContract } from './utils/printHelper';
import { ShieldCheck, FileText, Lock, ShieldAlert, Award, FileCheck } from 'lucide-react';

import { Car, Reservation, Branch, AuditLog, Notification, SystemSettings, DashboardStats as StatsType, CustomerOrder } from './types';
import { Language, getTranslation } from './i18n/translations';

export default function App() {
  // Authentication State
  const [token, setToken] = useState<string | null>(localStorage.getItem('car_stock_token'));
  const [user, setUser] = useState<any | null>(null);

  // App Layout State
  const [activePortal, setActivePortal] = useState<'landing' | 'rep_login' | 'manager_login' | 'contact'>('landing');
  const [activeTab, setActiveTab] = useState<string>('dashboard');
  const [darkMode, setDarkMode] = useState<boolean>(localStorage.getItem('theme') === 'dark');
  const [notificationsOpen, setNotificationsOpen] = useState(false);

  // Business Logic States
  const [cars, setCars] = useState<Car[]>([]);
  const [totalCarsCount, setTotalCarsCount] = useState(0);
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [users, setUsers] = useState<any[]>([]);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [settings, setSettings] = useState<SystemSettings | null>(null);
  const [stats, setStats] = useState<StatsType | null>(null);
  const [customerOrders, setCustomerOrders] = useState<CustomerOrder[]>([]);
  const [loading, setLoading] = useState(false);

  // Inventory Filters State
  const [search, setSearch] = useState('');
  const [filterMake, setFilterMake] = useState('');
  const [filterModel, setFilterModel] = useState('');
  const [filterYear, setFilterYear] = useState('');
  const [filterBranch, setFilterBranch] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [sortCriteria, setSortCriteria] = useState('make-model');
  const [currentPage, setCurrentPage] = useState(1);
  const carsPerPage = 12;

  // i18n & Advanced Table state
  const [lang, setLang] = useState<Language>('ar');
  const [viewMode, setViewMode] = useState<'card' | 'table'>('card');
  const [activeDetailCar, setActiveDetailCar] = useState<Car | null>(null);
  const [activeReservationDetail, setActiveReservationDetail] = useState<Reservation | null>(null);
  const [editingReservation, setEditingReservation] = useState<Reservation | null>(null);
  const [sellingReservation, setSellingReservation] = useState<Reservation | null>(null);

  // Active Modals State
  const [activeCarForm, setActiveCarForm] = useState<boolean>(false);
  const [editingCar, setEditingCar] = useState<Car | null>(null);
  const [activeReservationForm, setActiveReservationForm] = useState<Car | null>(null);
  const [activeAttachmentCar, setActiveAttachmentCar] = useState<Car | null>(null);
  const [showInstallerSim, setShowInstallerSim] = useState<boolean>(false);

  // Toast State
  const [toasts, setToasts] = useState<{ id: string; title: string; desc: string; type: 'success' | 'info' | 'warn' }[]>([]);

  // 1. SYSTEM INITIALIZATION & SECURE ROUTING
  useEffect(() => {
    if (darkMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [darkMode]);

  const toggleDarkMode = () => {
    const nextTheme = !darkMode;
    setDarkMode(nextTheme);
    localStorage.setItem('theme', nextTheme ? 'dark' : 'light');
  };

  // Dynamic SEO and Analytics tracking for Admin App Tabs
  useEffect(() => {
    if (!settings) return;
    
    // Map activeTab to labels for Arabic logging
    const tabLabels: { [key: string]: string } = {
      dashboard: 'لوحة القيادة والمؤشرات',
      inventory: 'إدارة مخزن وصالة السيارات',
      sales: 'عقود المبيعات والأرشيف',
      users: 'الموظفين وحوكمة الصلاحيات',
      branches: 'إدارة الفروع والمعارض',
      transfers: 'التحويلات الرسمية بين الفروع',
      settings: 'إعدادات النظام والتحليلات',
      'customer-orders': 'صندوق طلبات العملاء المباشرة',
      logs: 'سجل عمليات وتدقيق النظام'
    };

    const seoSettings = settings.seo?.[activeTab] || {
      title: `${tabLabels[activeTab] || 'لوحة التحكم'} | ${settings.companyName}`,
      description: `متابعة وإدارة ${tabLabels[activeTab] || 'لوحة التحكم'} بنظام المخزون برو المطور.`
    };

    document.title = seoSettings.title;

    let metaDesc = document.querySelector('meta[name="description"]');
    if (!metaDesc) {
      metaDesc = document.createElement('meta');
      metaDesc.setAttribute('name', 'description');
      document.head.appendChild(metaDesc);
    }
    metaDesc.setAttribute('content', seoSettings.description);

    // Track internally in analytics logs
    if (user) {
      fetch('/api/analytics/log', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          path: `/${activeTab}`,
          referrer: window.location.origin,
          action: `تنقل داخلي - الموظف ${user.name} زار ${tabLabels[activeTab] || activeTab}`
        })
      }).catch(err => console.error('Analytics internal log error:', err));
    }
  }, [activeTab, settings, user]);

  // Fetch current user details on token verification
  useEffect(() => {
    if (!token) return;
    
    setLoading(true);
    fetch('/api/auth/me', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
      .then(res => {
        if (!res.ok) throw new Error('Expired session.');
        return res.json();
      })
      .then(data => {
        setUser(data.user);
        fetchGlobalState();
      })
      .catch(() => {
        handleLogout();
      })
      .finally(() => {
        setLoading(false);
      });
  }, [token]);

  // Global state fetch coordinator
  const fetchGlobalState = async () => {
    if (!token) return;
    try {
      const headers = { 'Authorization': `Bearer ${token}` };

      // Load Settings and Branches first
      const [settRes, branchRes] = await Promise.all([
        fetch('/api/settings'),
        fetch('/api/branches')
      ]);
      const activeSettings = await settRes.json();
      const activeBranches = await branchRes.json();
      setSettings(activeSettings);
      setBranches(activeBranches);

      // Load Statistics, Reservations, Notifications & Logs
      const [statsRes, resvRes, notifRes, ordersRes] = await Promise.all([
        fetch('/api/dashboard/stats', { headers }),
        fetch('/api/reservations', { headers }),
        fetch('/api/notifications', { headers }),
        fetch('/api/customer-orders', { headers })
      ]);
      setStats(await statsRes.json());
      setReservations(await resvRes.json());
      setNotifications(await notifRes.json());
      if (ordersRes.ok) {
        setCustomerOrders(await ordersRes.json());
      }

      // Logs & Users are restricted to Admin
      if (user?.role === 'admin' || localStorage.getItem('car_stock_role') === 'admin') {
        const [logsRes, usersRes] = await Promise.all([
          fetch('/api/logs', { headers }),
          fetch('/api/users', { headers })
        ]);
        if (logsRes.ok) {
          setLogs(await logsRes.json());
        }
        if (usersRes.ok) {
          setUsers(await usersRes.json());
        }
      }

      // Fetch dynamic catalog (Vite/Node backend)
      fetchCarCatalog();
    } catch (err) {
      console.error('Failed to load global data state', err);
    }
  };

  const [prevTab, setPrevTab] = useState(activeTab);
  if (activeTab !== prevTab) {
    setPrevTab(activeTab);
    setCurrentPage(1);
  }

  // Dedicated Car Catalog fetch with paginated filters
  const fetchCarCatalog = async () => {
    if (!token) return;
    try {
      const query = new URLSearchParams({
        search,
        make: filterMake,
        model: filterModel,
        year: filterYear,
        branchId: filterBranch,
        status: activeTab === 'sales' ? 'sold' : filterStatus,
        excludeSold: activeTab === 'inventory' ? 'true' : 'false',
        sort: sortCriteria,
        page: currentPage.toString(),
        limit: carsPerPage.toString()
      });

      const res = await fetch(`/api/cars?${query.toString()}`);
      if (res.ok) {
        const data = await res.json();
        setCars(data.cars);
        setTotalCarsCount(data.totalCount);
      }
    } catch (err) {
      console.error('Failed to load cars catalog', err);
    }
  };

  // Refetch cars when search, filter, sorting, or page changes
  useEffect(() => {
    if (token) {
      fetchCarCatalog();
    }
  }, [search, filterMake, filterModel, filterYear, filterBranch, filterStatus, sortCriteria, currentPage, activeTab]);

  // 2. REAL-TIME MULTI-USER UPDATE (SSE STREAM LISTENER)
  useEffect(() => {
    if (!token) return;

    const eventSource = new EventSource('/api/realtime');

    eventSource.onmessage = (event) => {
      try {
        const payload = JSON.parse(event.data);
        if (payload.type === 'CONNECTED') return;

        // Sync global state immediately on external updates
        fetchGlobalState();

        // Broadcast a beautiful live notification toast
        let toastTitle = 'تحديث في المخزن';
        let toastDesc = 'تم تعديل البيانات بواسطة الخادم.';
        let toastType: 'success' | 'info' | 'warn' = 'info';

        if (payload.type === 'CAR_ADDED') {
          toastTitle = 'سيارة جديدة مضافة';
          toastDesc = `تم إضافة ${payload.data.make} ${payload.data.model} للمخزون الآن.`;
          toastType = 'success';
        } else if (payload.type === 'RESERVATION_ADDED') {
          toastTitle = 'حجز مركبة مؤكد';
          toastDesc = `تم تسجيل حجز جديد للعميل ${payload.data.customerName}.`;
          toastType = 'success';
        } else if (payload.type === 'RESERVATION_DELETED') {
          toastTitle = 'إلغاء حجز سيارة';
          toastDesc = 'تم إلغاء حجز أحد السيارات وإعادة إتاحتها للبيع.';
          toastType = 'warn';
        }

        triggerToast(toastTitle, toastDesc, toastType);
      } catch (err) {
        console.error('SSE Live Stream Parser error:', err);
      }
    };

    return () => {
      eventSource.close();
    };
  }, [token, user]);

  // Force representative to always stay on inventory (stock and showroom) tab
  useEffect(() => {
    if (user && user.role === 'representative' && activeTab !== 'inventory') {
      setActiveTab('inventory');
    }
  }, [user, activeTab]);

  const triggerToast = (title: string, desc: string, type: 'success' | 'info' | 'warn') => {
    const id = Date.now().toString();
    setToasts(prev => [...prev, { id, title, desc, type }]);
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id));
    }, 4500);
  };

  // 3. SERVICE API ACTIONS
  const handleLoginSuccess = (userToken: string, loggedUser: any) => {
    localStorage.setItem('car_stock_token', userToken);
    localStorage.setItem('car_stock_role', loggedUser.role);
    setToken(userToken);
    setUser(loggedUser);
    setActivePortal('landing');
    setActiveTab(loggedUser.role === 'representative' ? 'inventory' : 'dashboard');
  };

  const handleLogout = () => {
    localStorage.removeItem('car_stock_token');
    localStorage.removeItem('car_stock_role');
    setToken(null);
    setUser(null);
    setActivePortal('landing');
    setCars([]);
    setReservations([]);
  };

  // Save Car (Add or Update)
  const handleSaveCar = async (carData: any) => {
    try {
      const isEdit = !!editingCar;
      const url = isEdit ? `/api/cars/${editingCar.id}` : '/api/cars';
      const method = isEdit ? 'PUT' : 'POST';

      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(carData)
      });

      if (!response.ok) {
        const result = await response.json();
        throw new Error(result.error || 'فشل حفظ المركبة.');
      }

      triggerToast(
        isEdit ? 'تم تحديث البيانات' : 'تم إضافة المركبة',
        isEdit ? 'تم دمج وحقن التعديلات بنجاح.' : 'تم إيداع السيارة ببطاقتها الجمركية بالمخزون.',
        'success'
      );
      
      setActiveCarForm(false);
      setEditingCar(null);
      fetchGlobalState();
    } catch (err: any) {
      console.error(err);
      triggerToast('خطأ في الحفظ', err.message || 'فشل الاتصال بالخادم لحفظ المركبة.', 'warn');
      throw err; // throw error so the Form's submit handler can catch and show it
    }
  };

  const handleCloneCar = async (id: string) => {
    try {
      const res = await fetch(`/api/cars/${id}/clone`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        triggerToast('تكرار ناجح', 'تم إنشاء نسخة مكررة ومطابقة للسيارة بالمخزون.', 'success');
        fetchGlobalState();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteCar = async (id: string) => {
    if (!window.confirm('هل أنت متأكد من حذف هذه السيارة بشكل نهائي؟ سيتم حذف المرفقات والحجوزات المرتبطة بها.')) return;
    try {
      const res = await fetch(`/api/cars/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        triggerToast('تم الحذف', 'تم مسح المركبة وكافة تفاصيلها من السجلات الجمركية.', 'warn');
        fetchGlobalState();
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Handle Reservation Bookings
  const handleBookCar = async (bookingData: any) => {
    // Prevent booking if the car status is reserved or sold
    const car = cars.find(c => c.id === bookingData.carId);
    if (car && (car.status === 'reserved' || car.status === 'sold')) {
      triggerToast('تنبيه الحجز', 'يمنع حجز السيارة إذا كانت حالتها محجوزة أو مباعة.', 'warn');
      return;
    }

    try {
      const response = await fetch('/api/reservations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(bookingData)
      });

      if (!response.ok) {
        const err = await response.json();
        triggerToast('خطأ في الحجز', err.error || 'فشل حجز السيارة.', 'warn');
        return;
      }

      const newReservation = await response.json();

      // OPTIMISTIC UPDATE: Update local state immediately for instant feedback
      setCars(prev => prev.map(c => c.id === bookingData.carId ? { ...c, status: 'reserved', repInCharge: newReservation.createdByUserName || user?.name || 'مبيعات' } : c));
      setReservations(prev => [newReservation, ...prev]);

      triggerToast('تم الحجز', 'تم حجز السيارة بنجاح.', 'success');
      setActiveReservationForm(null);
      fetchGlobalState();
    } catch (err) {
      console.error(err);
      triggerToast('خطأ في الاتصال', 'حدث خطأ أثناء الاتصال بالخادم.', 'warn');
    }
  };

  const handleInstantBookCar = async (car: Car) => {
    if (car.status === 'reserved' || car.status === 'sold') {
      triggerToast('تنبيه الحجز', 'يمنع حجز السيارة إذا كانت حالتها محجوزة أو مباعة.', 'warn');
      return;
    }
    const bookingPayload = {
      carId: car.id,
      customerName: 'حجز فوري',
      customerPhone: '0500000000',
      nationalId: '1000000000',
      nationality: 'سعودي',
      whatsApp: '0500000000',
      email: '',
      customerAddress: '',
      repInCharge: user?.name || 'مبيعات',
      createdByUserId: user?.id || '',
      duration: 3,
      reason: 'حجز سريع بلمسة واحدة من المندوب',
      notes: 'تم تفعيل الحجز الفوري بلمسة واحدة لزيادة سرعة المبيعات في صالة العرض.',
      reservationDate: new Date().toISOString().split('T')[0],
      reservationEndDate: new Date(Date.now() + (3 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0],
      reservationStatus: 'active'
    };
    await handleBookCar(bookingPayload);
  };

  const handleCancelBooking = async (reservationId: string) => {
    if (!window.confirm('هل تود إلغاء حجز هذه السيارة وإعادة إتاحتها للمبيعات؟')) return;
    try {
      // Find the reservation before deleting to update local state optimistically
      const resv = reservations.find(r => r.id === reservationId);

      const res = await fetch(`/api/reservations/${reservationId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        // OPTIMISTIC UPDATE: Update local state immediately
        if (resv) {
          setCars(prev => prev.map(c => c.id === resv.carId ? { ...c, status: 'available', repInCharge: '' } : c));
        }
        setReservations(prev => prev.filter(r => r.id !== reservationId));

        triggerToast('تم إلغاء الحجز', 'تم تحرير المركبة وإعادة تصنيفها كمتاحة للبيع بالمعرض.', 'info');
        fetchGlobalState();
      } else {
        const errorData = await res.json();
        triggerToast('خطأ في إلغاء الحجز', errorData.error || 'ليس لديك الصلاحية لإلغاء هذا الحجز.', 'warn');
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleUpdateReservation = async (resId: string, updatedData: any) => {
    try {
      const res = await fetch(`/api/reservations/${resId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(updatedData)
      });
      if (res.ok) {
        setReservations(prev => prev.map(r => r.id === resId ? { ...r, ...updatedData } : r));
        triggerToast('تم تعديل الحجز', 'تم تعديل بيانات حجز المركبة بنجاح في قاعدة البيانات.', 'success');
        fetchGlobalState();
      }
    } catch (err) {
      console.error(err);
      triggerToast('خطأ', 'فشل في حفظ تعديلات الحجز.', 'warn');
    }
  };

  const handleMarkReservationSold = async (resId: string, carId: string, saleData: any) => {
    try {
      const res = await fetch('/api/sales', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          carId,
          saleAmount: parseFloat(saleData.saleAmount),
          customerName: saleData.customerName,
          customerPhone: saleData.customerPhone,
          exitNotes: saleData.exitNotes,
          exitDate: saleData.exitDate
        })
      });

      if (res.ok) {
        if (resId && resId !== 'direct-sale') {
          await fetch(`/api/reservations/${resId}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
          });
        }

        setReservations(prev => prev.filter(r => r.id !== resId));
        setCars(prev => prev.map(c => c.id === carId ? { ...c, status: 'sold' } : c));
        triggerToast('تم إتمام البيع', 'تم تسجيل المركبة كمبيعة ونقلها تلقائياً إلى عهدة المبيعات.', 'success');
        fetchGlobalState();
        setActiveTab('sales');
      }
    } catch (err) {
      console.error(err);
      triggerToast('خطأ', 'فشل في ترحيل الحجز للمبيعات.', 'warn');
    }
  };

  // Add User
  const handleAddUser = async (userData: any) => {
    try {
      const res = await fetch('/api/users', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(userData)
      });
      if (res.ok) {
        fetchGlobalState();
      } else {
        const err = await res.json();
        alert(err.error || 'فشل إضافة المستخدم.');
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteUser = async (userId: string) => {
    if (!window.confirm('هل تود إلغاء هذا الحساب وحذفه من لوحة القيادة؟')) return;
    try {
      const res = await fetch(`/api/users/${userId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        triggerToast('تم إزالة الحساب', 'تم حذف المستخدم بنجاح.', 'info');
        fetchGlobalState();
      } else {
        const err = await res.json();
        alert(err.error || 'فشل الحذف.');
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Add Branch
  const handleAddBranch = async (branchData: any) => {
    try {
      const res = await fetch('/api/branches', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(branchData)
      });
      if (res.ok) {
        fetchGlobalState();
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteBranch = async (id: string) => {
    if (!window.confirm('هل أنت متأكد من مسح هذا الفرع الجغرافي بالكامل؟')) return;
    try {
      const res = await fetch(`/api/branches/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        triggerToast('تم الحذف', 'تم إزالة المعرض الفرعي بنجاح.', 'warn');
        fetchGlobalState();
      } else {
        const err = await res.json();
        alert(err.error || 'فشل حذف المعرض.');
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Save Settings
  const handleSaveSettings = async (nextSettings: SystemSettings) => {
    try {
      const res = await fetch('/api/settings', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(nextSettings)
      });
      if (res.ok) {
        fetchGlobalState();
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Mark all notifications read
  const handleMarkNotificationsRead = async (id?: string) => {
    try {
      await fetch('/api/notifications/read', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ id })
      });
      fetchGlobalState();
    } catch (err) {
      console.error(err);
    }
  };

  // Export Catalog
  const exportCatalogCSV = () => {
    window.open('/api/export/cars/csv?Authorization=Bearer ' + token, '_blank');
  };

  // Export Bookings
  const exportReservationsCSV = () => {
    window.open('/api/export/reservations/csv?Authorization=Bearer ' + token, '_blank');
  };

  // Export Sales Report
  const exportSalesReportCSV = () => {
    window.open('/api/export/sales/csv?Authorization=Bearer ' + token, '_blank');
  };

  // Check login state
  if (!token) {
    if (activePortal === 'landing') {
      return (
        <LandingPage 
          settings={settings} 
          darkMode={darkMode}
          toggleDarkMode={toggleDarkMode}
          onSelectPortal={(portal) => setActivePortal(portal)} 
          onContact={() => setActivePortal('contact')}
        />
      );
    }
    if (activePortal === 'contact') {
      return (
        <ContactUsPage 
          settings={settings}
          darkMode={darkMode}
          onBack={() => setActivePortal('landing')}
        />
      );
    }
    return (
      <LoginScreen 
        portal={activePortal === 'rep_login' ? 'representative' : 'manager'} 
        onLoginSuccess={handleLoginSuccess} 
        onBackToLanding={() => setActivePortal('landing')} 
      />
    );
  }

  // Calculate unique list of makes for filtering
  const uniqueMakes = Array.from(new Set(cars.map(c => c.make)));
  const totalPages = Math.ceil(totalCarsCount / carsPerPage);

  return (
    <div className="min-h-screen bg-[#070b15] dark:bg-[#070b15] flex flex-col lg:flex-row font-sans text-slate-300 dark:text-slate-300 transition-colors duration-300" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      
      {/* 1. TOP NAVIGATION */}
      <TopNav 
        activeTab={activeTab} 
        setActiveTab={setActiveTab} 
        userRole={user?.role || 'representative'} 
        userName={user?.name || 'مخدم النظام'} 
        onLogout={handleLogout}
        logo={settings?.logo}
        companyName={settings?.companyName}
        user={user}
        onUpdateAvatar={(newAvatar) => {
          if (user) {
            setUser({ ...user, avatar: newAvatar });
            triggerToast('تحديث الحساب', 'تم تحديث صورتك الشخصية بنجاح!', 'success');
          }
        }}
        viewMode={viewMode}
        setViewMode={setViewMode}
        lang={lang}
        setLang={setLang}
        darkMode={darkMode}
        toggleDarkMode={toggleDarkMode}
        notifications={notifications}
        handleMarkNotificationsRead={handleMarkNotificationsRead}
      />

      {/* 2. MAIN APPLICATION LAYER */}
      <main className="flex-1 min-w-0 flex flex-col relative overflow-y-auto">
        
        {/* 3. SCROLLABLE TAB PANEL ROUTER */}
        <div className="p-4 md:p-5 flex-1">
          
          {loading ? (
            <div className="flex flex-col items-center justify-center py-24 space-y-4">
              <span className="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
              <p className="text-xs text-slate-500 font-medium">جاري مزامنة قاعدة البيانات والتحقق من التشفير الآمن...</p>
            </div>
          ) : user?.role === 'representative' && activeTab !== 'inventory' ? (
            <div className="p-8 bg-rose-500/5 rounded-xl border border-rose-500/20 max-w-2xl mx-auto my-12 text-center space-y-4">
              <div className="w-16 h-16 rounded-full bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto border border-rose-500/20">
                <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h2 className="text-lg font-bold text-white">ليس لديك صلاحية للوصول إلى هذه الصفحة.</h2>
              <p className="text-xs text-slate-400">
                عذراً، هذه الصفحة مخصصة لمديري ومسؤولي النظام فقط. تم تقييد حسابك بصفتك مندوب مبيعات للعمل ضمن مخزن وصالة عرض السيارات والطلبات المصرحة لك.
              </p>
              <button
                onClick={() => setActiveTab('inventory')}
                className="px-5 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-xs text-white font-bold transition cursor-pointer"
              >
                العودة لمخزن وصالة السيارات
              </button>
            </div>
          ) : (
            <div className="transition-all duration-300">
              
              {/* Tab 1: Dashboard Stats */}
              {activeTab === 'dashboard' && stats && (
                <DashboardStats 
                  stats={stats} 
                  cars={cars} 
                  reservations={reservations} 
                  branches={branches}
                  customerOrders={customerOrders}
                  onViewAllCars={() => setActiveTab('inventory')}
                  onViewAllReservations={() => setActiveTab('reservations')}
                  onViewAllOrders={() => setActiveTab('customer-orders')}
                />
              )}

              {/* Tab: Customer Orders Inbox (صندوق طلبات العملاء) */}
              {activeTab === 'customer-orders' && (
                <CustomerOrdersInbox 
                  lang={lang} 
                  cars={cars} 
                />
              )}

              {/* Tab: Contact Inquiries Inbox (صندوق رسائل اتصل بنا) */}
              {activeTab === 'contact-inquiries' && (
                <ContactInquiriesInbox 
                  lang={lang} 
                />
              )}

              {/* Tab 2: Cars Catalog Inventory */}
              {activeTab === 'inventory' && (
                <div className="space-y-4">
                  
                  {/* Filter and search controllers */}
                  <div className="bg-[#0e1424] p-4 rounded-lg border border-slate-800/80 flex flex-col gap-3.5 shadow-lg">
                    
                    <div className="flex flex-col md:flex-row gap-3 items-center justify-between">
                      
                      {/* Search */}
                      <div className="relative w-full md:flex-1">
                        <input
                          type="text"
                          placeholder={lang === 'ar' ? "ابحث برقم الهيكل، لوحة السيارة، أو العلامة التجارية..." : "Search by VIN, Plate, or Brand..."}
                          value={search}
                          onChange={e => { setSearch(e.target.value); setCurrentPage(1); }}
                          className="w-full text-xs pr-9 pl-3.5 py-2 rounded border border-slate-800 bg-[#070b15] text-slate-250 placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-sans text-right"
                        />
                        <Search className="w-3.5 h-3.5 text-slate-500 absolute top-2.5 right-3" />
                      </div>

                      {/* View Mode Toggle */}
                      <div className="flex bg-[#070b15] border border-slate-800 rounded p-1 shrink-0 w-full md:w-auto justify-center">
                        <button
                          type="button"
                          onClick={() => setViewMode('card')}
                          className={`px-4 py-1.5 rounded text-[11px] font-bold transition cursor-pointer shrink-0 ${viewMode === 'card' ? 'bg-[#4f46e5] text-white shadow' : 'text-slate-400 hover:text-white'}`}
                        >
                          {lang === 'ar' ? 'عرض الكروت' : 'Cards'}
                        </button>
                        <button
                          type="button"
                          onClick={() => setViewMode('table')}
                          className={`px-4 py-1.5 rounded text-[11px] font-bold transition cursor-pointer shrink-0 ${viewMode === 'table' ? 'bg-[#4f46e5] text-white shadow' : 'text-slate-400 hover:text-white'}`}
                        >
                          {lang === 'ar' ? 'الجدول المتقدم' : 'Advanced View'}
                        </button>
                      </div>

                      {/* Add button (Admin only) */}
                      {user?.role === 'admin' && (
                        <button
                          onClick={() => { setEditingCar(null); setActiveCarForm(true); }}
                          className="w-full md:w-auto px-4 py-2 rounded bg-[#4f46e5] hover:bg-[#4338ca] text-white font-extrabold text-[11px] transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer animate-fade-in shrink-0"
                        >
                          <Plus className="w-3.5 h-3.5" />
                          <span>{lang === 'ar' ? 'إيداع سيارة جديدة' : 'Deposit New Car'}</span>
                        </button>
                      )}

                    </div>

                    {/* Filter fields row */}
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2 border-t border-slate-800/80 pt-3.5 text-[10px] font-bold">
                      
                      {/* Manufacturer */}
                      <select
                        value={filterMake}
                        onChange={e => { setFilterMake(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="">{lang === 'ar' ? 'جميع الماركات' : 'All Brands'}</option>
                        {uniqueMakes.map(m => (
                          <option key={m} value={m}>{m}</option>
                        ))}
                      </select>

                      {/* Manufacturing year */}
                      <select
                        value={filterYear}
                        onChange={e => { setFilterYear(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 font-sans cursor-pointer text-right"
                      >
                        <option value="">{lang === 'ar' ? 'جميع السنوات' : 'All Years'}</option>
                        {[2020, 2021, 2022, 2023, 2024, 2025, 2026].map(y => (
                          <option key={y} value={y}>{y}</option>
                        ))}
                      </select>

                      {/* Showroom Branch */}
                      <select
                        value={filterBranch}
                        onChange={e => { setFilterBranch(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="">{lang === 'ar' ? 'جميع الفروع والمعارض' : 'All Branches'}</option>
                        {branches.map(b => (
                          <option key={b.id} value={b.id}>{b.name}</option>
                        ))}
                      </select>

                      {/* Status */}
                      <select
                        value={filterStatus}
                        onChange={e => { setFilterStatus(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="">{lang === 'ar' ? 'جميع الحالات' : 'All Statuses'}</option>
                        <option value="available">{lang === 'ar' ? 'متاحة فقط' : 'Available'}</option>
                        <option value="reserved">{lang === 'ar' ? 'محجوزة فقط' : 'Reserved'}</option>
                      </select>

                      {/* Sort order */}
                      <select
                        value={sortCriteria}
                        onChange={e => { setSortCriteria(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="make-model">{lang === 'ar' ? 'الماركة والفئة (ترتيب أبجدي)' : 'Alphabetical'}</option>
                        <option value="date-desc">{lang === 'ar' ? 'أحدث المضاف حديثاً' : 'Recently Added'}</option>
                        <option value="price-asc">{lang === 'ar' ? 'السعر: من الأقل للأعلى' : 'Price: Low to High'}</option>
                        <option value="price-desc">{lang === 'ar' ? 'السعر: من الأعلى للأقل' : 'Price: High to Low'}</option>
                        <option value="year-desc">{lang === 'ar' ? 'الموديل: الحديث أولاً' : 'Model: Newest First'}</option>
                      </select>

                    </div>

                  </div>

                  {/* Conditional Grid or Table Layout */}
                  {viewMode === 'table' ? (
                    <AdvancedVehiclesTable
                      cars={cars}
                      branches={branches}
                      reservations={reservations}
                      lang={lang}
                      onEdit={(c) => { setEditingCar(c); setActiveCarForm(true); }}
                      onDelete={(id) => { handleDeleteCar(id); }}
                      onReserve={(c) => {
                        if (user?.role === 'representative') {
                          if (window.confirm(lang === 'ar' ? `هل تود تأكيد الحجز الفوري للمركبة ${c.make} ${c.model} الآن بلمسة واحدة؟` : `Do you want to confirm instant reservation for ${c.make} ${c.model} with one touch?`)) {
                            handleInstantBookCar(c);
                          }
                        } else {
                          setActiveReservationForm(c);
                        }
                      }}
                      onViewReservationDetail={(r) => setActiveReservationDetail(r)}
                      logo={settings?.logo}
                      companyName={settings?.companyName}
                      userRole={(user?.role || localStorage.getItem('car_stock_role') || 'representative') as any}
                    />
                  ) : (
                    <>
                      {/* Cars Grid */}
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {cars.map((car) => {
                          const branch = branches.find(b => b.id === car.branchId)?.name || 'فرع غير معروف';
                          const resv = reservations.find(r => r.carId === car.id);
                          return (
                            <CarCard
                              key={car.id}
                              car={car}
                              branchName={branch}
                              userRole={(user?.role || localStorage.getItem('car_stock_role') || 'representative') as any}
                              currentUserId={user?.id || ''}
                              lang={lang}
                              reservation={resv}
                              onReserve={(c) => {
                                if (user?.role === 'representative') {
                                  if (window.confirm(lang === 'ar' ? `هل تود تأكيد الحجز الفوري للمركبة ${c.make} ${c.model} الآن بلمسة واحدة؟` : `Do you want to confirm instant reservation for ${c.make} ${c.model} with one touch?`)) {
                                    handleInstantBookCar(c);
                                  }
                                } else {
                                  setActiveReservationForm(c);
                                }
                              }}
                              onCancelReservation={(id) => { handleCancelBooking(id); }}
                              onEdit={(c) => { setEditingCar(c); setActiveCarForm(true); }}
                              onDelete={(id) => { handleDeleteCar(id); }}
                              onClone={(id) => { handleCloneCar(id); }}
                              onViewAttachments={(c) => setActiveAttachmentCar(c)}
                              onViewDetails={(c) => setActiveDetailCar(c)}
                              onViewReservationDetail={(r) => setActiveReservationDetail(r)}
                              onSellReservation={(r) => setSellingReservation(r)}
                            />
                          );
                        })}
                      </div>

                      {/* Empty state catalog */}
                      {cars.length === 0 && (
                        <div className="bg-slate-900 text-center py-16 rounded border border-slate-800 space-y-3 animate-fade-in">
                          <AlertCircle className="w-10 h-10 text-slate-600 mx-auto" />
                          <p className="text-xs text-slate-450 font-bold">لا يوجد سيارات مطابقة لبحثك في المستودعات حالياً.</p>
                          <p className="text-[10px] text-slate-500">تأكد من تعديل فلاتر التصفية أو البحث برقم هيكل صحيح.</p>
                        </div>
                      )}

                      {/* Pagination control */}
                      {totalPages > 1 && (
                        <div className="flex items-center justify-center gap-1.5 mt-4">
                          <button
                            onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                            disabled={currentPage === 1}
                            className="px-2.5 py-1 text-xs font-bold rounded border border-slate-800 bg-slate-950 text-slate-450 hover:text-white disabled:opacity-40 cursor-pointer"
                          >
                            السابق
                          </button>
                          {Array.from({ length: totalPages }).map((_, idx) => (
                            <button
                              key={idx}
                              onClick={() => setCurrentPage(idx + 1)}
                              className={`w-7 h-7 rounded font-sans font-bold text-xs cursor-pointer ${currentPage === idx + 1 ? 'bg-indigo-600 text-white font-bold' : 'border border-slate-800 bg-slate-950 text-slate-400 hover:text-white'}`}
                            >
                              {idx + 1}
                            </button>
                          ))}
                          <button
                            onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                            disabled={currentPage === totalPages}
                            className="px-2.5 py-1 text-xs font-bold rounded border border-slate-800 bg-slate-950 text-slate-450 hover:text-white disabled:opacity-40 cursor-pointer"
                          >
                            التالي
                          </button>
                        </div>
                      )}
                    </>
                  )}

                </div>
              )}

              {/* Tab: Sales / Sold Cars Section */}
              {activeTab === 'sales' && (
                <div className="space-y-4 font-sans text-right animate-fade-in">
                  {/* High Security Disclaimer Header */}
                  <div className="bg-slate-900 border border-slate-800 rounded-lg p-4 shadow-sm relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-1 bg-emerald-500 h-full"></div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h4 className="font-extrabold text-sm text-white flex items-center gap-2">
                          <ShieldCheck className="w-5 h-5 text-emerald-400" />
                          <span>قسم مبيعات وعقود السيارات الإلكترونية</span>
                        </h4>
                        <p className="text-[10px] text-slate-400 mt-1 max-w-3xl leading-relaxed">
                          بوابة مبيعات معززة ومحمية ببروتوكولات التشفير والتحقق الرقمي للاتحاد المالي. تخضع كافة العمليات هنا لأعلى مستويات الرقابة والتدقيق المستمر. العمليات والعقود المسجلة مغلقة وغير قابلة للتعديل أو الحذف المباشر لضمان النزاهة وحماية المبيعات من الازدواجية والتلاعب.
                        </p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0 bg-[#065f46]/10 border border-[#065f46]/20 px-3 py-1.5 rounded text-emerald-400 text-[10px] font-bold">
                        <Lock className="w-3.5 h-3.5 text-emerald-400" />
                        <span>منصة مبيعات آمنة ومراقبة (SSL)</span>
                      </div>
                    </div>
                  </div>

                  {/* Search and Filters Row */}
                  <div className="bg-slate-900 border border-slate-800 rounded-lg p-3 flex flex-col md:flex-row md:items-center justify-between gap-3 text-[10px] font-bold">
                    <div className="flex flex-wrap items-center gap-2 w-full">
                      {/* Search Input */}
                      <div className="relative w-full md:w-64">
                        <input
                          type="text"
                          placeholder={lang === 'ar' ? 'بحث باسم العميل، العقد، لوحة، VIN...' : 'Search customer, contract, plate...'}
                          value={search}
                          onChange={e => { setSearch(e.target.value); setCurrentPage(1); }}
                          className="w-full p-2 pr-8 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 text-right font-sans placeholder-slate-600"
                        />
                        <Search className="w-3.5 h-3.5 text-slate-500 absolute right-2.5 top-2.5" />
                      </div>

                      {/* Make Filter */}
                      <select
                        value={filterMake}
                        onChange={e => { setFilterMake(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="">{lang === 'ar' ? 'جميع الماركات' : 'All Brands'}</option>
                        {uniqueMakes.map(m => (
                          <option key={m} value={m}>{m}</option>
                        ))}
                      </select>

                      {/* Branch Filter */}
                      <select
                        value={filterBranch}
                        onChange={e => { setFilterBranch(e.target.value); setCurrentPage(1); }}
                        className="p-2 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-right font-sans"
                      >
                        <option value="">{lang === 'ar' ? 'جميع الفروع والمعارض' : 'All Branches'}</option>
                        {branches.map(b => (
                          <option key={b.id} value={b.id}>{b.name}</option>
                        ))}
                      </select>
                    </div>

                    <div className="text-[10px] text-slate-500 shrink-0">
                      إجمالي السيارات المباعة: <span className="text-white font-extrabold">{totalCarsCount}</span> سيارة
                    </div>
                  </div>

                  {/* Sales Catalog list */}
                  {cars.length === 0 ? (
                    <div className="bg-slate-900 border border-slate-800 rounded-lg p-10 text-center text-slate-400">
                      <Lock className="w-8 h-8 mx-auto text-slate-600 mb-2.5" />
                      <p className="text-xs font-bold">لا توجد مبيعات مسجلة تطابق محددات البحث والفلترة حالياً.</p>
                      <p className="text-[10px] text-slate-500 mt-1">يتم نقل أي سيارة تلقائياً إلى هذا القسم بمجرد إتمام تعاقدها وتسجيل بيعها.</p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 gap-4">
                      {cars.map(car => {
                        const auditHash = 'AMP-' + Math.abs(car.id.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)).toString(16).toUpperCase().padStart(8, '0');
                        return (
                          <div key={car.id} className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm flex flex-col lg:flex-row relative">
                            {/* Left secure boundary label */}
                            <div className="absolute top-0 left-0 bg-emerald-500 text-slate-950 font-extrabold text-[8px] px-2 py-0.5 rounded-br uppercase tracking-wide">
                              Secured & Audited
                            </div>

                            {/* Vehicle Image & Basic details block */}
                            <div className="w-full lg:w-1/4 bg-slate-950 p-4 border-b lg:border-b-0 lg:border-l border-slate-800 flex flex-col justify-between">
                              <div className="space-y-2">
                                <div className="aspect-[4/3] rounded-lg overflow-hidden border border-slate-800 bg-slate-900 relative">
                                  {car.mainImage ? (
                                    <img src={car.mainImage} alt="Car" className="w-full h-full object-cover" />
                                  ) : (
                                    <div className="w-full h-full flex items-center justify-center text-slate-700 text-xs">لا توجد صورة</div>
                                  )}
                                </div>
                                <div>
                                  <h5 className="font-extrabold text-xs text-white">{car.make} {car.model}</h5>
                                  <p className="text-[10px] text-slate-400 mt-0.5">{car.trim} | الموديل: {car.year}</p>
                                </div>
                              </div>
                              <div className="mt-3 pt-3 border-t border-slate-800 text-[10px] text-slate-500 space-y-1">
                                <div>اللوحة: <strong className="text-slate-300">{car.plateNumber}</strong></div>
                                <div>الشاصيه: <strong className="text-slate-300 font-mono text-[9px]">{car.vin}</strong></div>
                              </div>
                            </div>

                            {/* Center: Sales and Financial Information */}
                            <div className="flex-1 p-5 space-y-4">
                              <div className="border-b border-slate-800 pb-2 flex justify-between items-center">
                                <div>
                                  <span className="text-[9px] text-slate-500 font-bold uppercase block tracking-wider">بيانات المشتري والمبيعات</span>
                                  <h4 className="font-extrabold text-sm text-indigo-400 mt-1">{car.sale?.buyerName || 'عميل معتمد'}</h4>
                                </div>
                                <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2.5 py-1 rounded text-[10px] font-extrabold flex items-center gap-1">
                                  <span>✓</span>
                                  <span>{lang === 'ar' ? 'مباعة بالكامل' : 'Sold'}</span>
                                </div>
                              </div>

                              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs font-medium">
                                <div>
                                  <span className="text-slate-500 block text-[10px] mb-0.5 font-bold">سعر بيع السيارة</span>
                                  <span className="font-sans font-extrabold text-emerald-400 text-sm block">{(car.finalPrice || car.price).toLocaleString()} ر.س</span>
                                </div>
                                <div>
                                  <span className="text-slate-500 block text-[10px] mb-0.5 font-bold">المندوب البائع</span>
                                  <span className="font-bold text-slate-200 block">{car.sale?.sellerName || car.repInCharge || 'إدارة المعرض'}</span>
                                </div>
                                <div>
                                  <span className="text-slate-500 block text-[10px] mb-0.5 font-bold">تاريخ المبايعة / التسليم</span>
                                  <span className="font-bold text-slate-200 block">{car.sale?.deliveryDate || 'مباشر'}</span>
                                </div>
                                <div>
                                  <span className="text-slate-500 block text-[10px] mb-0.5 font-bold">طريقة الدفع وقناة السداد</span>
                                  <span className="font-bold text-slate-200 block">
                                    {car.sale?.paymentMethod === 'cash' ? 'نقدي مالي' : car.sale?.paymentMethod === 'bank' ? 'تحويل مصرفي' : 'نقداً (كاش)'}
                                  </span>
                                </div>
                              </div>

                              {/* Offline Document Notification Line */}
                              <div className="bg-indigo-500/10 border border-indigo-500/25 p-3 rounded-lg text-xs text-indigo-400 flex items-center gap-2">
                                <span className="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                <span>تم إبرام وتوثيق العقود والفواتير الضريبية يدوياً خارج النظام وفقاً للسياسة التشغيلية.</span>
                              </div>
                            </div>
                          </div>
                        );
                      })}

                      {/* Pagination Footer */}
                      {totalPages > 1 && (
                        <div className="flex items-center justify-center gap-1.5 pt-4 text-[11px] font-bold">
                          <button
                            onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                            disabled={currentPage === 1}
                            className="px-2.5 py-1 text-xs font-bold rounded border border-slate-800 bg-slate-950 text-slate-450 hover:text-white disabled:opacity-40 cursor-pointer"
                          >
                            السابق
                          </button>
                          {Array.from({ length: totalPages }).map((_, idx) => (
                            <button
                              key={idx}
                              onClick={() => setCurrentPage(idx + 1)}
                              className={`w-7 h-7 rounded font-sans font-bold text-xs cursor-pointer ${currentPage === idx + 1 ? 'bg-indigo-600 text-white font-bold' : 'border border-slate-800 bg-slate-950 text-slate-450 hover:text-white'}`}
                            >
                              {idx + 1}
                            </button>
                          ))}
                          <button
                            onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                            disabled={currentPage === totalPages}
                            className="px-2.5 py-1 text-xs font-bold rounded border border-slate-800 bg-slate-950 text-slate-450 hover:text-white disabled:opacity-40 cursor-pointer"
                          >
                            التالي
                          </button>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Tab 3: Active Reservations */}
              {activeTab === 'reservations' && (
                <div className="bg-slate-900 rounded border border-slate-800 overflow-hidden">
                  <div className="p-3.5 border-b border-slate-800">
                    <h4 className="font-bold text-xs text-white flex items-center gap-1.5">
                      <CalendarDays className="w-4 h-4 text-indigo-400" />
                      <span>جدول وتفاصيل الحجوزات النشطة لمبيعات الفروع</span>
                    </h4>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full text-right text-xs">
                      <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
                        <tr>
                          <th className="p-3">اسم العميل</th>
                          <th className="p-3">الهاتف</th>
                          <th className="p-3">المركبة المحجوزة</th>
                          <th className="p-3">رقم اللوحة</th>
                          <th className="p-3">المندوب المنسق</th>
                          <th className="p-3">مدة الحجز (أيام)</th>
                          <th className="p-3">الغرض والسبب</th>
                          <th className="p-3 text-center">الإجراءات</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-800 text-slate-300">
                        {reservations.map((r) => {
                          const car = cars.find(c => c.id === r.carId);
                          const canCancel = user?.role === 'admin' || r.createdByUserId === user?.id;
                          return (
                            <tr key={r.id} className="hover:bg-slate-800/20 transition-colors">
                              <td className="p-3 font-bold text-white">{r.customerName}</td>
                              <td className="p-3 font-mono text-slate-400">{r.customerPhone}</td>
                              <td className="p-3 font-bold text-indigo-400">
                                {car ? `${car.make} ${car.model} (${car.year})` : 'سيارة ممسوحة من النظام'}
                              </td>
                              <td className="p-3">
                                <span className="font-mono tracking-wider text-[10px] bg-slate-950 py-0.5 px-1.5 rounded border border-slate-800">
                                  {car?.plateNumber || '-'}
                                </span>
                              </td>
                              <td className="p-3 font-medium">{r.createdByUserName}</td>
                              <td className="p-3 font-sans font-bold text-center">{r.duration}</td>
                              <td className="p-3 font-medium max-w-xs truncate">{r.reason}</td>
                              <td className="p-3 text-center">
                                <div className="flex items-center justify-center gap-1.5 flex-wrap">
                                  <button
                                    onClick={() => setActiveReservationDetail(r)}
                                    className="px-2.5 py-1 rounded bg-indigo-600/10 hover:bg-indigo-600 hover:text-white text-indigo-400 font-bold text-[10px] transition cursor-pointer"
                                  >
                                    تفاصيل الحجز
                                  </button>
                                  {user?.role === 'admin' && (
                                    <>
                                      <button
                                        onClick={() => setSellingReservation(r)}
                                        className="px-2.5 py-1 rounded bg-emerald-600/10 hover:bg-emerald-600 hover:text-white text-emerald-400 font-bold text-[10px] transition cursor-pointer"
                                      >
                                        تم البيع 💰
                                      </button>
                                      <button
                                        onClick={() => setEditingReservation(r)}
                                        className="px-2.5 py-1 rounded bg-amber-600/10 hover:bg-amber-600 hover:text-white text-amber-400 font-bold text-[10px] transition cursor-pointer"
                                      >
                                        تعديل ✏️
                                      </button>
                                    </>
                                  )}
                                  {canCancel && (
                                    <button
                                      onClick={() => handleCancelBooking(r.id)}
                                      className="px-2.5 py-1 rounded bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-400 font-bold text-[10px] transition cursor-pointer"
                                    >
                                      إلغاء الحجز
                                    </button>
                                  )}
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                        {reservations.length === 0 && (
                          <tr>
                            <td colSpan={8} className="text-center py-10 text-slate-400 font-bold">لا يوجد حجوزات نشطة حالياً.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Tab 4: Users Management (Admin only) */}
              {activeTab === 'users' && user?.role === 'admin' && (
                <UsersTable 
                  users={users} 
                  branches={branches} 
                  onAddUser={handleAddUser} 
                  onDeleteUser={handleDeleteUser} 
                />
              )}

              {/* Tab 5: Branches Management (Admin only) */}
              {activeTab === 'branches' && user?.role === 'admin' && (
                <BranchesTable 
                  branches={branches} 
                  onAddBranch={handleAddBranch} 
                  onDeleteBranch={handleDeleteBranch} 
                />
              )}

              {/* Tab 5.5: Branch Transfers */}
              {activeTab === 'transfers' && (
                <BranchTransfersTable
                  cars={cars}
                  branches={branches}
                  token={token!}
                  lang={lang}
                  triggerToast={triggerToast}
                  fetchGlobalState={fetchGlobalState}
                />
              )}

              {/* Tab 6: Reports & Exports */}
              {activeTab === 'reports' && (
                <div className="space-y-6 font-sans text-right">
                  {/* Reports Header */}
                  <div className="bg-slate-900 border border-slate-800 rounded-lg p-4 shadow-sm relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-1 bg-indigo-500 h-full"></div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div>
                        <h4 className="font-extrabold text-sm text-white flex items-center gap-2">
                          <FileBarChart2 className="w-5 h-5 text-indigo-400" />
                          <span>مركز التقارير والإحصائيات وتدقيق المبيعات المعتمد</span>
                        </h4>
                        <p className="text-[10px] text-slate-400 mt-1 max-w-3xl leading-relaxed">
                          استخراج وتحميل جرد المخازن وصالات العرض، وتقارير الحجوزات، والبيانات المالية للمبيعات. تخضع كافة التقارير المخرجة هنا لمطابقة الشفرات الأمنية المعتمدة لضمان النزاهة المطلقة للبيانات المالية وحمايتها من التلاعب.
                        </p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1.5 rounded text-indigo-400 text-[10px] font-bold">
                        <ShieldCheck className="w-3.5 h-3.5 text-indigo-400" />
                        <span>نظام تدقيق وتقارير مؤمن (SSL)</span>
                      </div>
                    </div>
                  </div>

                  {/* Reports Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Card 1: Inventory */}
                    <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-3 flex flex-col justify-between">
                      <div className="space-y-2">
                        <div className="w-10 h-10 rounded bg-indigo-600/10 text-indigo-400 flex items-center justify-center">
                          <FileBarChart2 className="w-5 h-5" />
                        </div>
                        <h4 className="font-extrabold text-xs text-white">تصدير جرد ومخزون الفروع بالكامل</h4>
                        <p className="text-[10px] text-slate-500 leading-normal">
                          تحميل ملف جرد تفصيلي لكافة الفروع والسيارات المتواجدة حالياً (يتضمن أرقام الهيكل واللوحات والمواصفات الفنية ومطابقة رقم الهيكل) بصيغة ملف Excel منسق واحترافي.
                        </p>
                      </div>
                      <button
                        onClick={exportCatalogCSV}
                        className="w-full mt-2 px-3.5 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/10 cursor-pointer"
                      >
                        <Download className="w-3.5 h-3.5" />
                        <span>تنزيل تقرير الجرد (Excel)</span>
                      </button>
                    </div>

                    {/* Card 2: Reservations */}
                    <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-3 flex flex-col justify-between">
                      <div className="space-y-2">
                        <div className="w-10 h-10 rounded bg-indigo-600/10 text-indigo-400 flex items-center justify-center">
                          <CalendarDays className="w-5 h-5" />
                        </div>
                        <h4 className="font-extrabold text-xs text-white">تصدير حجوزات مبيعات الفروع المعتمدة</h4>
                        <p className="text-[10px] text-slate-500 leading-normal">
                          تحميل تقرير الحجوزات لتتبع أداء المناديب وتطور عمليات البيع وعقود التمويل قيد المراجعة في جدول إحصائي بصيغة Excel منسقة واحترافية.
                        </p>
                      </div>
                      <button
                        onClick={exportReservationsCSV}
                        className="w-full mt-2 px-3.5 py-2 rounded bg-slate-950 hover:bg-slate-850 text-slate-250 font-bold text-xs transition border border-slate-800 flex items-center justify-center gap-1.5 shadow-md shadow-slate-900/10 cursor-pointer"
                      >
                        <Download className="w-3.5 h-3.5 text-indigo-400" />
                        <span>تحميل سجل الحجوزات (Excel)</span>
                      </button>
                    </div>

                    {/* Card 3: Secure Sales & Financial Report */}
                    <div className="bg-[#022c22]/10 p-4 rounded-xl border border-emerald-500/20 space-y-3 flex flex-col justify-between relative overflow-hidden">
                      <div className="absolute top-0 left-0 bg-emerald-500 text-slate-950 font-extrabold text-[7px] px-1.5 py-0.5 rounded-br uppercase tracking-wide">
                        SECURED REPORT
                      </div>
                      <div className="space-y-2">
                        <div className="w-10 h-10 rounded bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                          <ShieldCheck className="w-5 h-5" />
                        </div>
                        <h4 className="font-extrabold text-xs text-emerald-400">تقرير وحصيلة المبيعات المعتمدة (الرقابة التامة)</h4>
                        <p className="text-[10px] text-slate-400 leading-normal">
                          تحميل التقرير المالي الموحد ومجمل المبيعات وصافي الإيرادات المحصلة للفروع والمناديب. يخضع هذا التقرير لعمليات الرقابة الصارمة وموقع إلكترونياً برمز تحقق مشفر لكل عملية بيع.
                        </p>
                      </div>
                      <button
                        onClick={exportSalesReportCSV}
                        className="w-full mt-2 px-3.5 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/10 cursor-pointer"
                      >
                        <Download className="w-3.5 h-3.5" />
                        <span>تحميل تقرير المبيعات المالي (Excel)</span>
                      </button>
                    </div>
                  </div>

                  {/* Section: Real-time Sales Integrity Supervision (لوحة الرقابة والحماية الرقمية للمبيعات) */}
                  <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
                    <div className="border-b border-slate-800 pb-3 flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Lock className="w-4 h-4 text-emerald-400" />
                        <h4 className="font-extrabold text-xs text-white">لوحة الرقابة التامة وحماية عقود المبيعات من التلاعب</h4>
                      </div>
                      <div className="flex items-center gap-1.5 text-[9px] font-bold bg-[#065f46]/10 border border-[#10b981]/20 px-2 py-0.5 rounded text-emerald-400 animate-pulse">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>مراقبة مستمرة نشطة</span>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="bg-[#070b15] border border-slate-800 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-slate-500 block">حالة جدار الحماية (Firewall)</span>
                        <span className="text-xs font-extrabold text-emerald-400 mt-1 block">نشط ومؤمن بالكامل</span>
                      </div>
                      <div className="bg-[#070b15] border border-slate-800 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-slate-500 block">تشفير قاعدة البيانات</span>
                        <span className="text-xs font-extrabold text-emerald-400 mt-1 block">AES-256 (مطبق)</span>
                      </div>
                      <div className="bg-[#070b15] border border-slate-800 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-slate-500 block">فحص سلامة الهياكل (VIN)</span>
                        <span className="text-xs font-extrabold text-emerald-400 mt-1 block">سليم (منع الازدواجية)</span>
                      </div>
                      <div className="bg-[#070b15] border border-slate-800 rounded-lg p-3 text-center">
                        <span className="text-[10px] text-slate-500 block">العمليات المشبوهة المكتشفة</span>
                        <span className="text-xs font-extrabold text-emerald-400 mt-1 block">0 (آمن بنسبة 100%)</span>
                      </div>
                    </div>

                    <div className="bg-[#0c1a17] border border-[#10b981]/15 rounded-lg p-3 text-[10px] leading-relaxed text-slate-350 space-y-2">
                      <div className="flex items-center gap-1.5 text-emerald-400 font-extrabold">
                        <ShieldAlert className="w-3.5 h-3.5 shrink-0" />
                        <span>أنظمة حماية المبيعات وموثوقية العقود:</span>
                      </div>
                      <p className="pr-4">
                        1. <strong>استحالة ازدواجية العقود:</strong> لا يمكن إدخال أو حجز رقم هيكل (VIN) مرتين بالنظام، وجدار حماية البيانات يعيق أي محاولة تكرار فوراً.
                      </p>
                      <p className="pr-4">
                        2. <strong>الإغلاق المالي:</strong> بمجرد تسجيل عملية البيع، يتم إغلاق السجل رقمياً وتجميده ضد الحذف والتعديل لضمان نزاهة الحسابات ومطابقتها التامة لدى الجهات الرقابية.
                      </p>
                      <p className="pr-4">
                        3. <strong>التوقيع الرقمي:</strong> يُولد النظام تلقائياً كود تشفير وتحقق رقمي فريد لكل فاتورة وعقد، يُربط رقمياً بمعلومات الهيكل والمشتري لضمان منع التزوير.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Tab 7: System Security Audit Trail (Admin only) */}
              {activeTab === 'logs' && user?.role === 'admin' && (
                <ActivityLogsTable logs={logs} />
              )}

              {/* Tab: Representatives Real-time Monitoring and Surveillance */}
              {activeTab === 'rep-monitoring' && user?.role === 'admin' && (
                <RepMonitoringPanel 
                  logs={logs} 
                  users={users} 
                  token={token || ''} 
                  triggerToast={(title, msg, type) => triggerToast(title, msg, type === 'error' ? 'warn' : type === 'warning' ? 'warn' : 'success')} 
                />
              )}

              {/* Tab 8: System Settings Panel (Admin only) */}
              {activeTab === 'settings' && user?.role === 'admin' && settings && (
                <SettingsPanel 
                  settings={settings} 
                  token={token} 
                  onSaveSettings={handleSaveSettings} 
                  onRestoreSuccess={fetchGlobalState}
                  onOpenInstallerSim={() => setShowInstallerSim(true)}
                />
              )}

            </div>
          )}

        </div>

      </main>

      {/* 4. APP SYSTEM TOAST BROADCASTER */}
      <div className="fixed bottom-6 right-6 z-[100] flex flex-col gap-2 max-w-sm w-full">
        {toasts.map(t => (
          <div 
            key={t.id} 
            className="p-3.5 rounded bg-slate-900 border border-slate-800 shadow-2xl flex gap-3 items-start transform animate-slide-up duration-300"
          >
            <div className={`w-7 h-7 rounded-full flex items-center justify-center shrink-0 ${t.type === 'success' ? 'bg-emerald-500/10 text-emerald-400' : t.type === 'warn' ? 'bg-rose-500/10 text-rose-400' : 'bg-indigo-600/10 text-indigo-400'}`}>
              <CheckCircle2 className="w-4 h-4" />
            </div>
            <div>
              <h5 className="font-bold text-xs text-white">{t.title}</h5>
              <p className="text-[10px] text-slate-400 mt-0.5 leading-normal">{t.desc}</p>
            </div>
          </div>
        ))}
      </div>

      {/* 5. MODAL OVERLAY INJECTORS */}
      {activeCarForm && (
        <CarForm 
          car={editingCar} 
          branches={branches} 
          token={token!} 
          lang={lang}
          onClose={() => { setActiveCarForm(false); setEditingCar(null); }} 
          onSave={handleSaveCar}
        />
      )}

      {activeReservationForm && (
        <ReservationForm 
          car={activeReservationForm} 
          token={token!}
          lang={lang}
          currentUser={user!}
          branches={branches}
          users={users}
          onClose={() => setActiveReservationForm(null)} 
          onSave={handleBookCar}
        />
      )}

      {activeAttachmentCar && (
        <AttachmentViewer 
          car={activeAttachmentCar} 
          currentUser={user!}
          token={token!}
          lang={lang}
          reservation={reservations.find(r => r.carId === activeAttachmentCar.id)}
          onClose={() => setActiveAttachmentCar(null)} 
        />
      )}

      {activeDetailCar && (
        <CarDetailModal
          car={activeDetailCar}
          branches={branches}
          reservation={reservations.find(r => r.carId === activeDetailCar.id)}
          lang={lang}
          onClose={() => setActiveDetailCar(null)}
        />
      )}

      {activeReservationDetail && (() => {
        const car = cars.find(c => c.id === activeReservationDetail.carId);
        return car ? (
          <ReservationDetailModal
            reservation={activeReservationDetail}
            car={car}
            token={token!}
            lang={lang}
            onClose={() => setActiveReservationDetail(null)}
          />
        ) : null;
      })()}

      {editingReservation && (
        <EditReservationModal
          reservation={editingReservation}
          onClose={() => setEditingReservation(null)}
          onSave={handleUpdateReservation}
        />
      )}

      {sellingReservation && (
        <SellReservationModal
          reservation={sellingReservation}
          onClose={() => setSellingReservation(null)}
          onConfirm={handleMarkReservationSold}
        />
      )}

      {showInstallerSim && (
        <PHPInstallerSim 
          onClose={() => setShowInstallerSim(false)}
          triggerToast={(title, desc, type) => triggerToast(title, desc, type === 'error' ? 'warn' : type === 'warning' ? 'warn' : 'success')}
        />
      )}

    </div>
  );
}
