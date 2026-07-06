/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useState } from 'react';
import { 
  Inbox, 
  MessageSquare, 
  Phone, 
  Trash2, 
  CheckCircle, 
  Clock, 
  Loader2, 
  TrendingUp,
  AlertCircle,
  FileCheck2,
  XCircle,
  ExternalLink,
  RotateCw,
  Car
} from 'lucide-react';
import { CustomerOrder, Car as CarType } from '../types';

interface CustomerOrdersInboxProps {
  lang: 'ar' | 'en';
  cars: CarType[];
}

export default function CustomerOrdersInbox({ lang, cars }: CustomerOrdersInboxProps) {
  const [orders, setOrders] = useState<CustomerOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'new' | 'in_progress' | 'completed' | 'cancelled'>('all');
  const [search, setSearch] = useState('');

  const fetchOrders = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch('/api/customer-orders', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        const data = await res.json();
        setOrders(data);
      }
    } catch (err) {
      console.error('Failed to load customer orders:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, []);

  const handleUpdateStatus = async (id: string, status: CustomerOrder['status']) => {
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch(`/api/customer-orders/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ status })
      });
      if (res.ok) {
        const updated = await res.json();
        setOrders(prev => prev.map(o => o.id === id ? updated : o));
      }
    } catch (err) {
      console.error('Failed to update status:', err);
    }
  };

  const handleDeleteOrder = async (id: string) => {
    if (!window.confirm(lang === 'ar' ? 'هل أنت متأكد من حذف هذا الطلب نهائياً؟' : 'Are you sure you want to permanently delete this request?')) {
      return;
    }
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch(`/api/customer-orders/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        setOrders(prev => prev.filter(o => o.id !== id));
      }
    } catch (err) {
      console.error('Failed to delete order:', err);
    }
  };

  const getCarDetails = (carId: string) => {
    return cars.find(c => c.id === carId);
  };

  const filteredOrders = orders.filter(order => {
    const matchesFilter = filter === 'all' || order.status === filter;
    const car = getCarDetails(order.carId);
    const carName = car ? `${car.make} ${car.model} ${car.year}` : '';
    const matchesSearch = 
      order.customerName.toLowerCase().includes(search.toLowerCase()) ||
      order.customerPhone.includes(search) ||
      carName.toLowerCase().includes(search.toLowerCase());
    return matchesFilter && matchesSearch;
  });

  // Calculate statistics
  const totalCount = orders.length;
  const newCount = orders.filter(o => o.status === 'new').length;
  const inProgressCount = orders.filter(o => o.status === 'in_progress').length;
  const completedCount = orders.filter(o => o.status === 'completed').length;

  const statusMap = {
    new: { label: 'طلب جديد', color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' },
    in_progress: { label: 'قيد المتابعة', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
    completed: { label: 'مكتمل', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    cancelled: { label: 'ملغي', color: 'bg-rose-500/10 text-rose-450 text-rose-400 border-rose-500/20' }
  };

  return (
    <div className="space-y-6" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      {/* Header section */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-[#0e1424] p-5 rounded-xl border border-slate-800/80 shadow-lg">
        <div>
          <h2 className="text-lg font-black text-white flex items-center gap-2">
            <Inbox className="w-5 h-5 text-indigo-500" />
            <span>{lang === 'ar' ? 'صندوق طلبات العملاء' : 'Customer Orders Inbox'}</span>
          </h2>
          <p className="text-[11px] text-slate-400 mt-1">
            {lang === 'ar' ? 'مراقبة وإدارة الطلبات المباشرة المرسلة من صفحة صالة عرض العملاء الخارجية.' : 'Monitor and manage direct inquiries sent from the public customer showroom.'}
          </p>
        </div>
        <button 
          onClick={fetchOrders}
          className="flex items-center gap-1.5 px-3.5 py-1.5 rounded bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition text-xs font-black self-start md:self-center cursor-pointer"
        >
          <RotateCw className="w-3.5 h-3.5" />
          <span>{lang === 'ar' ? 'تحديث البيانات' : 'Refresh'}</span>
        </button>
      </div>

      {/* KPI Stats cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total inquiries */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 font-bold block">{lang === 'ar' ? 'إجمالي طلبات الشراء' : 'Total Inquiries'}</span>
            <span className="text-xl font-extrabold text-white font-mono mt-1 block">{totalCount}</span>
          </div>
          <div className="w-9 h-9 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
            <Inbox className="w-4 h-4" />
          </div>
        </div>

        {/* New */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 font-bold block">{lang === 'ar' ? 'طلبات جديدة معلقة' : 'Pending New'}</span>
            <span className="text-xl font-extrabold text-indigo-400 font-mono mt-1 block">{newCount}</span>
          </div>
          <div className="w-9 h-9 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
            <Clock className="w-4 h-4 animate-pulse" />
          </div>
        </div>

        {/* In Progress */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 font-bold block">{lang === 'ar' ? 'قيد المتابعة والبيع' : 'In Progress'}</span>
            <span className="text-xl font-extrabold text-amber-400 font-mono mt-1 block">{inProgressCount}</span>
          </div>
          <div className="w-9 h-9 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
            <TrendingUp className="w-4 h-4" />
          </div>
        </div>

        {/* Completed */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 font-bold block">{lang === 'ar' ? 'صفقات ناجحة مكتملة' : 'Completed Deals'}</span>
            <span className="text-xl font-extrabold text-emerald-400 font-mono mt-1 block">{completedCount}</span>
          </div>
          <div className="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
            <CheckCircle className="w-4 h-4" />
          </div>
        </div>
      </div>

      {/* Filter and Search controls */}
      <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex flex-col md:flex-row gap-3 items-center justify-between">
        {/* Filters */}
        <div className="flex bg-[#070b15] border border-slate-850 rounded-lg p-1 w-full md:w-auto overflow-x-auto scrollbar-none shrink-0 gap-1">
          {[
            { id: 'all', label: lang === 'ar' ? 'الكل' : 'All' },
            { id: 'new', label: lang === 'ar' ? 'طلبات جديدة' : 'New' },
            { id: 'in_progress', label: lang === 'ar' ? 'قيد المتابعة' : 'In Progress' },
            { id: 'completed', label: lang === 'ar' ? 'مكتملة' : 'Completed' },
            { id: 'cancelled', label: lang === 'ar' ? 'ملغاة' : 'Cancelled' }
          ].map(item => (
            <button
              key={item.id}
              onClick={() => setFilter(item.id as any)}
              className={`px-3.5 py-1.5 rounded text-[10px] font-black transition whitespace-nowrap cursor-pointer ${
                filter === item.id 
                  ? 'bg-indigo-600 text-white shadow shadow-indigo-600/10' 
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>

        {/* Search */}
        <div className="relative w-full md:max-w-md">
          <input
            type="text"
            placeholder={lang === 'ar' ? "ابحث باسم العميل، رقم الجوال، أو تفاصيل السيارة..." : "Search client name, phone, or car..."}
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full text-xs pr-8 pl-3.5 py-2 rounded border border-slate-800 bg-[#070b15] text-slate-250 placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-sans text-right"
          />
          <Inbox className="w-3.5 h-3.5 text-slate-500 absolute top-2.5 right-3" />
        </div>
      </div>

      {/* Grid of Inquiries */}
      {loading ? (
        <div className="flex flex-col items-center justify-center py-20 space-y-3">
          <Loader2 className="w-7 h-7 text-indigo-500 animate-spin" />
          <p className="text-[11px] text-slate-500">{lang === 'ar' ? 'جاري تحميل صندوق طلبات الشراء...' : 'Loading orders inbox...'}</p>
        </div>
      ) : filteredOrders.length === 0 ? (
        <div className="bg-[#0e1424] p-12 rounded-xl border border-slate-800/50 text-center space-y-3">
          <AlertCircle className="w-10 h-10 text-slate-600 mx-auto" />
          <h3 className="font-extrabold text-sm text-slate-200">{lang === 'ar' ? 'لا توجد طلبات تطابق الفلتر' : 'No inquiries found'}</h3>
          <p className="text-[11px] text-slate-500 max-w-xs mx-auto">
            {lang === 'ar' ? 'لا يوجد طلبات شراء مسجلة تحت هذا التصنيف أو تطابق الكلمة المفتاحية في الوقت الحالي.' : 'No customer inquiries matches your current status filter or keywords.'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          {filteredOrders.map(order => {
            const car = getCarDetails(order.carId);
            const statusStyle = statusMap[order.status] || statusMap.new;
            const waLink = `https://wa.me/${order.customerPhone.replace(/[\s+]/g, '')}`;

            return (
              <div 
                key={order.id} 
                className="bg-[#0e1424] rounded-xl border border-slate-800/80 hover:border-slate-700/80 transition-all shadow-md duration-300 flex flex-col h-full overflow-hidden"
              >
                {/* Associated Car Banner */}
                <div className="relative h-32 bg-slate-950/60 flex items-center justify-center overflow-hidden border-b border-slate-850">
                  {car?.mainImage ? (
                    <img 
                      src={car.mainImage} 
                      alt={car.make} 
                      className="w-full h-full object-cover opacity-80"
                      referrerPolicy="no-referrer"
                    />
                  ) : (
                    <div className="text-center text-slate-600">
                      <Car className="w-8 h-8 mx-auto opacity-30 mb-1" />
                      <span className="text-[10px] block">{lang === 'ar' ? 'لا توجد صورة' : 'No photo'}</span>
                    </div>
                  )}
                  {/* Status Badge overlay */}
                  <div className={`absolute top-2.5 right-2.5 px-2 py-0.5 rounded text-[9px] font-black border ${statusStyle.color}`}>
                    {statusStyle.label}
                  </div>
                  {/* Price banner overlay */}
                  {car && (
                    <div className="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-[#070b15]/90 border border-slate-800 text-[10px] font-black text-indigo-400 font-sans">
                      {car.price.toLocaleString(lang === 'ar' ? 'ar-SA' : 'en-US')} {car.currency || 'SAR'}
                    </div>
                  )}
                </div>

                {/* Main Content */}
                <div className="p-4 flex-1 flex flex-col space-y-3.5 text-right">
                  {/* Car name */}
                  {car ? (
                    <div>
                      <h4 className="font-extrabold text-xs text-white tracking-tight">{car.make} {car.model}</h4>
                      <span className="text-[9px] text-slate-500 font-mono">موديل: {car.year} | {car.trim}</span>
                    </div>
                  ) : (
                    <div>
                      <h4 className="font-extrabold text-xs text-rose-400 tracking-tight">{lang === 'ar' ? 'سيارة غير معرفة' : 'Deleted Car'}</h4>
                      <span className="text-[9px] text-slate-500 font-mono">ID: {order.carId}</span>
                    </div>
                  )}

                  {/* Customer Information Block */}
                  <div className="bg-[#070b15]/60 p-3 rounded-lg border border-slate-850 space-y-2 text-xs">
                    <div className="flex justify-between items-center text-[10px]">
                      <span className="font-extrabold text-slate-200">{order.customerName}</span>
                      <span className="text-slate-500 font-mono">{lang === 'ar' ? 'اسم العميل' : 'Customer'}</span>
                    </div>
                    <div className="flex justify-between items-center text-[10px]">
                      <span className="font-mono text-slate-300">{order.customerPhone}</span>
                      <span className="text-slate-500 font-mono">{lang === 'ar' ? 'رقم الجوال' : 'Phone'}</span>
                    </div>
                    {order.notes && (
                      <div className="border-t border-slate-850/40 pt-1.5 mt-1.5">
                        <span className="text-[9px] text-slate-500 block mb-0.5 font-bold">{lang === 'ar' ? 'ملاحظات العميل:' : 'Notes:'}</span>
                        <p className="text-[10px] text-slate-400 bg-slate-900/50 p-2 rounded border border-slate-800/40 text-right font-sans leading-relaxed">
                          {order.notes}
                        </p>
                      </div>
                    )}
                  </div>

                  {/* Metadata */}
                  <div className="text-[9px] text-slate-500 font-mono flex justify-between items-center">
                    <span>{new Date(order.createdAt).toLocaleString(lang === 'ar' ? 'ar-SA' : 'en-US')}</span>
                    <span>تاريخ الطلب</span>
                  </div>
                </div>

                {/* Footer Controls */}
                <div className="bg-[#0c101d] border-t border-slate-850 p-3 flex items-center gap-2">
                  {/* Status update dropdown */}
                  <div className="flex-1">
                    <select
                      value={order.status}
                      onChange={e => handleUpdateStatus(order.id, e.target.value as any)}
                      className="w-full text-[10px] font-black p-1.5 rounded border border-slate-800 bg-[#070b15] text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer text-center font-sans"
                    >
                      <option value="new">🆕 {lang === 'ar' ? 'طلب جديد' : 'New'}</option>
                      <option value="in_progress">⚙️ {lang === 'ar' ? 'قيد المتابعة' : 'In Progress'}</option>
                      <option value="completed">✅ {lang === 'ar' ? 'مكتمل ناجح' : 'Completed'}</option>
                      <option value="cancelled">❌ {lang === 'ar' ? 'ملغي ومستبعد' : 'Cancelled'}</option>
                    </select>
                  </div>

                  {/* Contact Button (WhatsApp link) */}
                  <a
                    href={waLink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="p-1.5 px-3 rounded bg-emerald-650 hover:bg-emerald-600 border border-emerald-500/20 text-white transition flex items-center gap-1.5 text-[10px] font-black cursor-pointer"
                    title="تواصل مباشرة عبر واتساب"
                  >
                    <MessageSquare className="w-3.5 h-3.5 shrink-0" />
                    <span>واتساب</span>
                  </a>

                  {/* Discard / Delete Button */}
                  <button
                    onClick={() => handleDeleteOrder(order.id)}
                    className="p-2 rounded bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition cursor-pointer border border-rose-500/25"
                    title="حذف الطلب نهائياً"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
