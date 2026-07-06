/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useState } from 'react';
import { 
  Mail, 
  Trash2, 
  CheckCircle, 
  Clock, 
  Loader2, 
  RotateCw,
  Phone,
  BookOpen,
  MessageCircle,
  MessageSquare,
  AlertCircle
} from 'lucide-react';
import { ContactInquiry } from '../types';

interface ContactInquiriesInboxProps {
  lang: 'ar' | 'en';
}

export default function ContactInquiriesInbox({ lang }: ContactInquiriesInboxProps) {
  const [inquiries, setInquiries] = useState<ContactInquiry[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'new' | 'read' | 'replied' | 'completed'>('all');
  const [search, setSearch] = useState('');

  const fetchInquiries = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch('/api/contact-inquiries', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        const data = await res.json();
        setInquiries(data);
      }
    } catch (err) {
      console.error('Failed to load contact inquiries:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInquiries();
  }, []);

  const handleUpdateStatus = async (id: string, status: ContactInquiry['status']) => {
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch(`/api/contact-inquiries/${id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ status })
      });
      if (res.ok) {
        const updated = await res.json();
        setInquiries(prev => prev.map(i => i.id === id ? updated : i));
      }
    } catch (err) {
      console.error('Failed to update status:', err);
    }
  };

  const handleDeleteInquiry = async (id: string) => {
    if (!window.confirm(lang === 'ar' ? 'هل أنت متأكد من حذف هذه الرسالة نهائياً؟' : 'Are you sure you want to permanently delete this message?')) {
      return;
    }
    try {
      const token = localStorage.getItem('car_stock_token');
      const res = await fetch(`/api/contact-inquiries/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        setInquiries(prev => prev.filter(i => i.id !== id));
      }
    } catch (err) {
      console.error('Failed to delete inquiry:', err);
    }
  };

  const filteredInquiries = inquiries.filter(inquiry => {
    const matchesFilter = filter === 'all' || inquiry.status === filter;
    const matchesSearch = 
      inquiry.name.toLowerCase().includes(search.toLowerCase()) ||
      inquiry.phone.includes(search) ||
      (inquiry.email && inquiry.email.toLowerCase().includes(search.toLowerCase())) ||
      (inquiry.subject && inquiry.subject.toLowerCase().includes(search.toLowerCase())) ||
      inquiry.message.toLowerCase().includes(search.toLowerCase());
    return matchesFilter && matchesSearch;
  });

  const totalCount = inquiries.length;
  const newCount = inquiries.filter(i => i.status === 'new').length;
  const readCount = inquiries.filter(i => i.status === 'read').length;
  const completedCount = inquiries.filter(i => i.status === 'completed').length;

  const statusMap = {
    new: { label: lang === 'ar' ? 'رسالة جديدة' : 'New Message', color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' },
    read: { label: lang === 'ar' ? 'تمت القراءة' : 'Read', color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    replied: { label: lang === 'ar' ? 'تم الرد' : 'Replied', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
    completed: { label: lang === 'ar' ? 'مكتمل' : 'Completed', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' }
  };

  return (
    <div className="space-y-6" dir={lang === 'ar' ? 'rtl' : 'ltr'}>
      {/* Header section */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-[#0e1424] p-5 rounded-xl border border-slate-800/80 shadow-lg">
        <div>
          <h2 className="text-lg font-black text-white flex items-center gap-2">
            <Mail className="w-5 h-5 text-indigo-500" />
            <span>{lang === 'ar' ? 'صندوق رسائل اتصل بنا' : 'Contact Us Inquiries Inbox'}</span>
          </h2>
          <p className="text-[11px] text-slate-400 mt-1">
            {lang === 'ar' ? 'مراقبة وإدارة الرسائل المباشرة والواردة من صفحة اتصل بنا للزوار.' : 'Monitor and manage direct messages sent from the public Contact Us form.'}
          </p>
        </div>
        <button 
          onClick={fetchInquiries}
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
            <span className="text-[10px] text-slate-400 block">{lang === 'ar' ? 'إجمالي الرسائل' : 'Total Messages'}</span>
            <span className="text-xl font-black text-white block mt-1">{totalCount}</span>
          </div>
          <div className="w-9 h-9 rounded bg-indigo-500/10 flex items-center justify-center text-indigo-400 border border-indigo-500/10">
            <Mail className="w-4 h-4" />
          </div>
        </div>

        {/* New inquiries */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 block">{lang === 'ar' ? 'رسائل جديدة (غير مقروءة)' : 'Unread / New'}</span>
            <span className="text-xl font-black text-amber-500 block mt-1">{newCount}</span>
          </div>
          <div className="w-9 h-9 rounded bg-amber-500/10 flex items-center justify-center text-amber-500 border border-amber-500/10 animate-pulse">
            <Clock className="w-4 h-4" />
          </div>
        </div>

        {/* Read inquiries */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 block">{lang === 'ar' ? 'تمت قراءتها' : 'Read'}</span>
            <span className="text-xl font-black text-blue-400 block mt-1">{readCount}</span>
          </div>
          <div className="w-9 h-9 rounded bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/10">
            <BookOpen className="w-4 h-4" />
          </div>
        </div>

        {/* Completed inquiries */}
        <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
          <div>
            <span className="text-[10px] text-slate-400 block">{lang === 'ar' ? 'رسائل منتهية' : 'Completed / Archived'}</span>
            <span className="text-xl font-black text-emerald-400 block mt-1">{completedCount}</span>
          </div>
          <div className="w-9 h-9 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-450 text-emerald-400 border border-emerald-500/10">
            <CheckCircle className="w-4 h-4" />
          </div>
        </div>
      </div>

      {/* Search & Filter bar */}
      <div className="bg-[#0e1424] p-4 rounded-xl border border-slate-800/60 flex flex-col md:flex-row items-center gap-3.5 justify-between">
        <div className="flex flex-wrap gap-1.5 w-full md:w-auto">
          {(['all', 'new', 'read', 'replied', 'completed'] as const).map(tab => (
            <button
              key={tab}
              onClick={() => setFilter(tab)}
              className={`px-3 py-1.5 rounded text-[10px] font-black transition border cursor-pointer ${
                filter === tab 
                  ? 'bg-indigo-600 border-indigo-500 text-white shadow-md' 
                  : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-white'
              }`}
            >
              {tab === 'all' && (lang === 'ar' ? 'الكل' : 'All')}
              {tab === 'new' && (lang === 'ar' ? 'جديد' : 'New')}
              {tab === 'read' && (lang === 'ar' ? 'تمت القراءة' : 'Read')}
              {tab === 'replied' && (lang === 'ar' ? 'تم الرد' : 'Replied')}
              {tab === 'completed' && (lang === 'ar' ? 'مكتمل' : 'Completed')}
            </button>
          ))}
        </div>

        <div className="w-full md:w-80">
          <input
            type="text"
            placeholder={lang === 'ar' ? 'ابحث بالاسم، الجوال، البريد، موضوع الرسالة...' : 'Search by name, phone, email, subject...'}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full px-3 py-2 text-xs rounded border border-slate-800 bg-slate-900 text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
        </div>
      </div>

      {/* Main inbox container */}
      {loading ? (
        <div className="bg-[#0e1424] py-16 rounded-xl border border-slate-800/60 flex flex-col items-center justify-center text-slate-400 gap-3">
          <Loader2 className="w-7 h-7 text-indigo-500 animate-spin" />
          <span className="text-xs font-bold">{lang === 'ar' ? 'جاري تحميل رسائل التواصل...' : 'Loading contact inquiries...'}</span>
        </div>
      ) : filteredInquiries.length === 0 ? (
        <div className="bg-[#0e1424] py-16 rounded-xl border border-slate-800/60 flex flex-col items-center justify-center text-slate-500 gap-3">
          <Mail className="w-10 h-10 text-slate-700" />
          <span className="text-xs font-bold">{lang === 'ar' ? 'لا توجد رسائل مطابقة حالياً.' : 'No contact messages found.'}</span>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredInquiries.map(inquiry => {
            const statusConfig = statusMap[inquiry.status] || { label: inquiry.status, color: 'bg-slate-500/10 text-slate-400 border-slate-500/20' };
            return (
              <div 
                key={inquiry.id}
                className="bg-[#0e1424] rounded-xl border border-slate-800/60 p-5 shadow-md flex flex-col justify-between hover:border-slate-700/60 transition group relative"
              >
                {/* Header of message card */}
                <div className="space-y-2.5">
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-mono text-slate-500 block">
                      {new Date(inquiry.createdAt).toLocaleString(lang === 'ar' ? 'ar-SA' : 'en-US', {
                        dateStyle: 'short',
                        timeStyle: 'short'
                      })}
                    </span>
                    <span className={`px-2 py-0.5 rounded border text-[9px] font-bold ${statusConfig.color}`}>
                      {statusConfig.label}
                    </span>
                  </div>

                  {/* Customer personal details */}
                  <div className="space-y-1">
                    <h3 className="text-xs font-black text-white group-hover:text-indigo-400 transition-colors">
                      {inquiry.name}
                    </h3>
                    <div className="flex flex-col text-[10px] text-slate-400 gap-1 pt-1">
                      <a href={`tel:${inquiry.phone}`} className="flex items-center gap-1.5 hover:text-white transition">
                        <Phone className="w-3 h-3 text-slate-500 shrink-0" />
                        <span className="font-sans" dir="ltr">{inquiry.phone}</span>
                      </a>
                      {inquiry.email && (
                        <a href={`mailto:${inquiry.email}`} className="flex items-center gap-1.5 hover:text-white transition truncate">
                          <Mail className="w-3 h-3 text-slate-500 shrink-0" />
                          <span className="font-sans truncate">{inquiry.email}</span>
                        </a>
                      )}
                    </div>
                  </div>

                  {/* Subject and Message block */}
                  <div className="bg-slate-950/45 p-3 rounded-lg border border-slate-900 mt-2 text-right">
                    <h4 className="text-[11px] font-extrabold text-indigo-300 mb-1 truncate">
                      {inquiry.subject}
                    </h4>
                    <p className="text-[10px] text-slate-300 leading-relaxed max-h-24 overflow-y-auto whitespace-pre-wrap">
                      {inquiry.message}
                    </p>
                  </div>
                </div>

                {/* Actions at bottom of card */}
                <div className="flex items-center justify-between gap-2 pt-4 mt-4 border-t border-slate-900">
                  <div className="flex flex-wrap gap-1">
                    {inquiry.status === 'new' && (
                      <button
                        onClick={() => handleUpdateStatus(inquiry.id, 'read')}
                        className="px-2 py-1 rounded bg-blue-650 hover:bg-blue-600 text-white font-extrabold text-[9px] cursor-pointer transition"
                      >
                        {lang === 'ar' ? 'تحديد كمقروء' : 'Mark Read'}
                      </button>
                    )}
                    {inquiry.status !== 'replied' && inquiry.status !== 'completed' && (
                      <button
                        onClick={() => handleUpdateStatus(inquiry.id, 'replied')}
                        className="px-2 py-1 rounded bg-amber-650 hover:bg-amber-600 text-white font-extrabold text-[9px] cursor-pointer transition"
                      >
                        {lang === 'ar' ? 'تم الرد' : 'Replied'}
                      </button>
                    )}
                    {inquiry.status !== 'completed' && (
                      <button
                        onClick={() => handleUpdateStatus(inquiry.id, 'completed')}
                        className="px-2 py-1 rounded bg-emerald-650 hover:bg-emerald-650 text-white font-extrabold text-[9px] cursor-pointer transition"
                      >
                        {lang === 'ar' ? 'إكمال وأرشفة' : 'Complete'}
                      </button>
                    )}
                  </div>

                  <button
                    onClick={() => handleDeleteInquiry(inquiry.id)}
                    className="p-1.5 rounded text-rose-450 hover:text-white hover:bg-rose-650 shrink-0 transition cursor-pointer"
                    title={lang === 'ar' ? 'مسح الرسالة' : 'Delete message'}
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
