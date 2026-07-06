/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { X, Check, AlertCircle, Calendar, ShieldAlert, Award, FileText, Truck } from 'lucide-react';
import { Car, Reservation, User, Branch } from '../types';
import { Language, getTranslation } from '../i18n/translations';

interface ReservationFormProps {
  car: Car;
  token: string;
  lang: Language;
  currentUser: User;
  branches: Branch[];
  users: User[];
  onClose: () => void;
  onSave: (reservationData: any) => void;
}

type OrderTab = 'reservation' | 'sale';

export default function ReservationForm({ car, token, lang, currentUser, branches, users, onClose, onSave }: ReservationFormProps) {
  const [activeTab, setActiveTab] = useState<OrderTab>('reservation');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Reservation details state
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('05');
  const [nationalId, setNationalId] = useState('');
  const [nationality, setNationality] = useState('سعودي');
  const [whatsApp, setWhatsApp] = useState('05');
  const [email, setEmail] = useState('');
  const [customerAddress, setCustomerAddress] = useState('');
  const [repInCharge, setRepInCharge] = useState('');
  
  // Representative details states
  const [createdByUserId, setCreatedByUserId] = useState('');
  const [repBranch, setRepBranch] = useState('');
  const [repEmail, setRepEmail] = useState('');
  const [repPhone, setRepPhone] = useState('');
  const [creationTime, setCreationTime] = useState(new Date().toLocaleString(lang === 'ar' ? 'ar-SA' : 'en-US'));
  const [updateTime, setUpdateTime] = useState(new Date().toLocaleString(lang === 'ar' ? 'ar-SA' : 'en-US'));

  const [duration, setDuration] = useState<number>(3);
  const [reason, setReason] = useState('طلب شراء شخصي كاش');
  const [notes, setNotes] = useState('');
  const [reservationDate, setReservationDate] = useState(new Date().toISOString().split('T')[0]);
  const [reservationEndDate, setReservationEndDate] = useState('');

  // Initialize representative details based on currentUser
  useEffect(() => {
    if (currentUser) {
      if (currentUser.role === 'representative') {
        setRepInCharge(currentUser.name);
        setCreatedByUserId(currentUser.id);
        setRepEmail(currentUser.email || '');
        setRepPhone(currentUser.phone || '');
        const b = branches.find(br => br.id === currentUser.branchId);
        setRepBranch(b ? b.name : 'غير معروف');
      } else {
        // Admin: can choose representative, but default is current admin's details
        if (!repInCharge) {
          setRepInCharge(currentUser.name);
          setCreatedByUserId(currentUser.id);
          setRepEmail(currentUser.email || '');
          setRepPhone(currentUser.phone || '');
          const b = branches.find(br => br.id === currentUser.branchId);
          setRepBranch(b ? b.name : 'غير معروف');
        }
      }
    }
  }, [currentUser, branches]);

  const handleRepChange = (repName: string) => {
    setRepInCharge(repName);
    const selectedUser = users.find(u => u.name === repName);
    if (selectedUser) {
      setCreatedByUserId(selectedUser.id);
      setRepEmail(selectedUser.email || '');
      setRepPhone(selectedUser.phone || '');
      const b = branches.find(br => br.id === selectedUser.branchId);
      setRepBranch(b ? b.name : 'غير معروف');
    }
  };

  // Sales Details state (if converting to a direct sale)
  const [sellerName, setSellerName] = useState('');
  const [buyerName, setBuyerName] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('نقداً (كاش)');
  const [contractNumber, setContractNumber] = useState('');
  const [invoiceNumber, setInvoiceNumber] = useState('');
  const [paymentStatus, setPaymentStatus] = useState('pending');
  const [paidAmount, setPaidAmount] = useState<number>(0);
  const [remainingAmount, setRemainingAmount] = useState<number>(0);
  const [deliveryMethod, setDeliveryMethod] = useState('استلام مباشر من فرع المعرض');
  const [deliveryDate, setDeliveryDate] = useState(new Date().toISOString().split('T')[0]);
  const [deliveryNotes, setDeliveryNotes] = useState('');

  const [errorMsg, setErrorMsg] = useState('');

  // Auto-calculate end date based on duration
  useEffect(() => {
    if (reservationDate && duration) {
      const start = new Date(reservationDate);
      start.setDate(start.getDate() + parseInt(duration.toString()));
      setReservationEndDate(start.toISOString().split('T')[0]);
    }
  }, [reservationDate, duration]);

  // Set initial default names
  useEffect(() => {
    if (customerName) {
      setBuyerName(customerName);
    }
  }, [customerName]);

  useEffect(() => {
    if (currentUser && !sellerName) {
      setSellerName(currentUser.name);
    }
  }, [currentUser]);

  // Auto-calculate remaining sales balances
  useEffect(() => {
    const taxValue = Math.round(car.price * 0.15);
    const finalPrice = car.price + taxValue - car.discount;
    setRemainingAmount(Math.max(0, finalPrice - paidAmount));
  }, [paidAmount, car]);

  // Initialize defaults
  useEffect(() => {
    setContractNumber(`CONT-4000${Date.now().toString().slice(-4)}`);
    setInvoiceNumber(`INV-2026-${Date.now().toString().slice(-4)}`);
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isSubmitting) return;
    setErrorMsg('');

    // REQUIRED VALIDATIONS
    if (!customerName || !customerPhone || !duration || !reason || !nationalId) {
      setErrorMsg(getTranslation(lang, 'valRequired'));
      return;
    }

    // SAUDI PHONE VALIDATION: Starts with 05, exactly 10 digits
    const saudiPhoneRegex = /^05\d{8}$/;
    if (!saudiPhoneRegex.test(customerPhone)) {
      setErrorMsg(getTranslation(lang, 'valPhoneInvalid'));
      return;
    }

    // SAUDI NATIONAL ID / IQAMA VALIDATION: 10 digits starting with 1 or 2
    const nationalIdRegex = /^[12]\d{9}$/;
    if (!nationalIdRegex.test(nationalId)) {
      setErrorMsg(getTranslation(lang, 'valIdInvalid'));
      return;
    }

    // Dates check
    const start = new Date(reservationDate);
    const end = new Date(reservationEndDate);
    if (end <= start) {
      setErrorMsg(getTranslation(lang, 'valDatesInvalid'));
      return;
    }

    const reservationPayload: any = {
      carId: car.id,
      customerName,
      customerPhone,
      nationalId,
      nationality,
      whatsApp,
      email,
      customerAddress,
      repInCharge,
      createdByUserId,
      duration: parseInt(duration.toString()),
      reason,
      notes,
      reservationDate,
      reservationEndDate,
      reservationStatus: 'active'
    };

    // If sales tab completed, append sales structure
    if (activeTab === 'sale') {
      const finalBuyer = buyerName || customerName;
      if (!sellerName || !finalBuyer) {
        setErrorMsg(lang === 'ar' ? 'يرجى إدخال اسم البائع واسم المشتري لتسجيل المبايعة.' : 'Please enter seller name and buyer name.');
        return;
      }
      reservationPayload.sale = {
        sellerName,
        buyerName: finalBuyer,
        paymentMethod,
        contractNumber: '',
        invoiceNumber: '',
        paymentStatus: 'paid',
        paidAmount: car.price,
        remainingAmount: 0,
        deliveryMethod: 'استلام مباشر',
        deliveryDate,
        deliveryNotes: 'تم التسجيل والبيع يدوياً خارج النظام'
      };
    }

    setIsSubmitting(true);
    onSave(reservationPayload);
  };

  return (
    <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto text-right">
      <div className="bg-slate-900 rounded-xl w-full max-w-4xl shadow-2xl border border-slate-800 overflow-hidden flex flex-col max-h-[92vh]">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-extrabold text-sm text-white flex items-center gap-1.5">
              <Calendar className="w-4 h-4 text-indigo-400" />
              <span>{getTranslation(lang, 'totalReservations')}: {car.make} {car.model}</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">
              {lang === 'ar' 
                ? 'لوحة ترخيص: ' 
                : 'Plate No: '} <strong>{car.plateNumber}</strong> | VIN: <strong className="font-mono">{car.vin}</strong>
            </p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Tab Selector */}
        <div className="flex border-b border-slate-800 bg-slate-950/40 text-xs px-2">
          <button
            type="button"
            onClick={() => setActiveTab('reservation')}
            className={`px-4 py-3 font-extrabold transition border-b-2 cursor-pointer ${activeTab === 'reservation' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-white'}`}
          >
            {lang === 'ar' ? 'أمر حجز مركبة مؤقت' : 'Temporary Holding Order'}
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('sale')}
            className={`px-4 py-3 font-extrabold transition border-b-2 cursor-pointer ${activeTab === 'sale' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-white'}`}
          >
            {lang === 'ar' ? 'تسجيل بيع السيارة' : 'Register Car as Sold'}
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-4 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
          
          {errorMsg && (
            <div className="p-2.5 bg-rose-500/10 border border-rose-500/20 rounded text-rose-400 text-xs font-extrabold flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 animate-pulse" />
              <span>{errorMsg}</span>
            </div>
          )}

          {/* SECTION 1: CUSTOMER DETAIL (Applies to both) */}
          <div className="space-y-4">
            <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
              <Award className="w-3.5 h-3.5" />
              <span>{lang === 'ar' ? 'بيانات وهوية المشتري المعتمد' : 'Buyer Identity Dossier'}</span>
            </h4>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3.5">
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'customerName')} *</label>
                <input
                  type="text"
                  required
                  placeholder="الاسم الثلاثي أو الرباعي"
                  value={customerName}
                  onChange={e => setCustomerName(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'customerPhone')} *</label>
                <input
                  type="text"
                  required
                  placeholder="05xxxxxxxx"
                  value={customerPhone}
                  onChange={e => setCustomerPhone(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'nationalId')} *</label>
                <input
                  type="text"
                  required
                  placeholder="الهوية الوطنية أو الإقامة"
                  value={nationalId}
                  onChange={e => setNationalId(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'nationality')}</label>
                <input
                  type="text"
                  value={nationality}
                  onChange={e => setNationality(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'whatsApp')}</label>
                <input
                  type="text"
                  value={whatsApp}
                  onChange={e => setWhatsApp(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'email')}</label>
                <input
                  type="email"
                  value={email}
                  onChange={e => setEmail(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div className="col-span-1 md:col-span-3">
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'customerAddress')}</label>
                <input
                  type="text"
                  value={customerAddress}
                  onChange={e => setCustomerAddress(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>
          </div>

          {/* TAB CONTENT: RESERVATION PARAMS */}
          {activeTab === 'reservation' && (
            <div className="space-y-4 pt-3 border-t border-slate-800">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <Calendar className="w-3.5 h-3.5" />
                <span>{lang === 'ar' ? 'تفاصيل الحجز المؤقت والمندوب' : 'Temporary holding parameters'}</span>
              </h4>

              {/* Representative Credentials Block */}
              <div className="bg-slate-950/60 p-3.5 rounded-lg border border-slate-800 space-y-3 mb-4 col-span-1 md:col-span-4 text-right">
                <div className="text-[10px] font-extrabold text-indigo-400 flex items-center gap-1.5 uppercase tracking-wide">
                  <span>🛡️ {lang === 'ar' ? 'بيانات المندوب المسؤول المعتمد' : 'Authorized Representative Credentials'}</span>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3.5">
                  {/* Rep Name */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'repInCharge')} *</label>
                    {currentUser.role === 'admin' ? (
                      <select
                        value={repInCharge}
                        onChange={e => handleRepChange(e.target.value)}
                        className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer font-bold"
                      >
                        <option value="">-- {lang === 'ar' ? 'اختر المندوب المسؤول' : 'Select Rep'} --</option>
                        {users.map(u => (
                          <option key={u.id} value={u.name}>{u.name} ({u.role === 'admin' ? (lang === 'ar' ? 'مدير' : 'Admin') : (lang === 'ar' ? 'مندوب' : 'Rep')})</option>
                        ))}
                      </select>
                    ) : (
                      <input
                        type="text"
                        disabled
                        value={repInCharge}
                        className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-bold"
                      />
                    )}
                  </div>

                  {/* User ID */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'رقم الموظف (User ID)' : 'Employee ID (User ID)'}</label>
                    <input
                      type="text"
                      disabled
                      value={createdByUserId}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-mono"
                    />
                  </div>

                  {/* Branch */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'branch')}</label>
                    <input
                      type="text"
                      disabled
                      value={repBranch}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed"
                    />
                  </div>

                  {/* Email */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'البريد الإلكتروني' : 'Email'}</label>
                    <input
                      type="text"
                      disabled
                      value={repEmail || (lang === 'ar' ? 'غير مسجل' : 'Not Registered')}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-mono"
                    />
                  </div>

                  {/* Phone */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'رقم الهاتف' : 'Phone'}</label>
                    <input
                      type="text"
                      disabled
                      value={repPhone || (lang === 'ar' ? 'غير مسجل' : 'Not Registered')}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-sans"
                    />
                  </div>

                  {/* Created At */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'تاريخ ووقت إنشاء الحجز' : 'Reservation Created At'}</label>
                    <input
                      type="text"
                      disabled
                      value={creationTime}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-mono"
                    />
                  </div>

                  {/* Last Updated At */}
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'آخر تحديث للحجز' : 'Last Updated At'}</label>
                    <input
                      type="text"
                      disabled
                      value={updateTime}
                      className="w-full text-xs px-3 py-2 rounded border border-slate-850 bg-slate-900/40 text-slate-400 cursor-not-allowed font-mono"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-3.5 col-span-1 md:col-span-4 w-full">

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'warrantyDuration')} ({lang === 'ar' ? 'أيام الحجز' : 'Hold Days'})</label>
                  <input
                    type="number"
                    min={1}
                    max={14}
                    value={duration}
                    onChange={e => setDuration(parseInt(e.target.value || '3'))}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'reservationDate')}</label>
                  <input
                    type="date"
                    value={reservationDate}
                    onChange={e => setReservationDate(e.target.value)}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'reservationEndDate')}</label>
                  <input
                    type="date"
                    disabled
                    value={reservationEndDate}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-900 text-slate-500 font-sans cursor-not-allowed"
                  />
                </div>

                <div className="col-span-1 md:col-span-3">
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'reservationReason')}</label>
                  <input
                    type="text"
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'reservationNotes')}</label>
                <textarea
                  rows={2}
                  value={notes}
                  onChange={e => setNotes(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>
          )}

          {/* TAB CONTENT: DIRECT CONTRACT / TAX INVOICE */}
          {activeTab === 'sale' && (
            <div className="space-y-4 pt-3 border-t border-slate-800">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <FileText className="w-3.5 h-3.5" />
                <span>{lang === 'ar' ? 'بيانات المشتري والمبيعات' : 'Buyer & Sales Details'}</span>
              </h4>

              <div className="p-3 bg-slate-950/60 rounded border border-indigo-500/10 text-xs text-indigo-300">
                {lang === 'ar' 
                  ? '💡 ملاحظة: يتم إعداد العقود وتوليد الفواتير الضريبية يدوياً خارج النظام، نكتفي هنا بتسجيل السيارة كمباعة لنقلها لبوابة المبيعات والتقارير.'
                  : '💡 Note: Invoices and contracts are issued manually offline. We only register the car as sold here to move it to the Sales portal and Reports.'}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'buyerName')} *</label>
                  <input
                    type="text"
                    required
                    value={buyerName}
                    onChange={e => setBuyerName(e.target.value)}
                    placeholder={lang === 'ar' ? 'اسم العميل المشتري الثنائي أو الثلاثي' : 'Buyer Full Name'}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'sellerName')} *</label>
                  <input
                    type="text"
                    required
                    value={sellerName}
                    onChange={e => setSellerName(e.target.value)}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{getTranslation(lang, 'paymentMethod')}</label>
                  <select
                    value={paymentMethod}
                    onChange={e => setPaymentMethod(e.target.value)}
                    className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none cursor-pointer font-bold"
                  >
                    <option value="نقداً (كاش)">نقداً (كاش) / Cash</option>
                    <option value="تمويل بنكي">تمويل بنكي / Bank Finance</option>
                    <option value="شيك مصدق">شيك مصدق / Certified Check</option>
                    <option value="قسط مؤجل">قسط مؤجل / Installment Hold</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'تاريخ البيع / تسليم السيارة' : 'Sale / Delivery Date'}</label>
                  <input
                    type="date"
                    value={deliveryDate}
                    onChange={e => setDeliveryDate(e.target.value)}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold cursor-pointer"
                  />
                </div>
              </div>
            </div>
          )}

        </form>

        {/* Footer Actions */}
        <div className="p-4 border-t border-slate-800 flex justify-end gap-2 bg-slate-950">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-bold text-slate-400 bg-slate-900 border border-slate-800 hover:bg-slate-850 rounded transition cursor-pointer"
          >
            {getTranslation(lang, 'cancel')}
          </button>
          
          <button
            type="button"
            onClick={handleSubmit}
            disabled={isSubmitting}
            className={`px-4 py-2 text-xs font-bold text-white rounded transition cursor-pointer shadow flex items-center gap-1 ${isSubmitting ? 'bg-indigo-800/60 text-slate-400 cursor-not-allowed shadow-none' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/10'}`}
          >
            <Check className="w-4.5 h-4.5" />
            <span>
              {isSubmitting 
                ? (lang === 'ar' ? 'جاري الحفظ...' : 'Saving...')
                : activeTab === 'sale' 
                  ? (lang === 'ar' ? 'تسجيل بيع السيارة ونقلها للمبيعات' : 'Register & Transition to Sales')
                  : getTranslation(lang, 'toastBookSuccess')}
            </span>
          </button>
        </div>

      </div>
    </div>
  );
}
