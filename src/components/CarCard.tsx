/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { 
  CalendarDays, 
  MapPin, 
  Wrench, 
  Fuel, 
  FileText, 
  Trash2, 
  Edit3, 
  Copy, 
  Printer, 
  BookOpen,
  Undo2,
  Info,
  X
} from 'lucide-react';
import { Car, Branch, UserRole, Reservation } from '../types';
import { Language, getTranslation } from '../i18n/translations';
import ImageGalleryModal from './ImageGalleryModal';

interface CarCardProps {
  key?: string | number;
  car: Car;
  branchName: string;
  userRole: UserRole;
  currentUserId: string;
  lang: Language;
  reservation?: Reservation;
  onReserve: (car: Car) => void;
  onCancelReservation: (reservationId: string) => void;
  onEdit: (car: Car) => void;
  onDelete: (carId: string) => void;
  onClone: (carId: string) => void;
  onViewAttachments: (car: Car) => void;
  onViewDetails: (car: Car) => void; // Support full details view
  onViewReservationDetail?: (reservation: Reservation) => void;
  onSellReservation?: (reservation: Reservation) => void;
}

export default function CarCard({
  car,
  branchName,
  userRole,
  currentUserId,
  lang,
  reservation,
  onReserve,
  onCancelReservation,
  onEdit,
  onDelete,
  onClone,
  onViewAttachments,
  onViewDetails,
  onViewReservationDetail,
  onSellReservation
}: CarCardProps) {

  const [isZoomed, setIsZoomed] = React.useState(false);
  const isAdmin = userRole === 'admin';
  const isRepresentative = userRole === 'representative';
  const isReserved = car.status === 'reserved';
  const hasImage = car.mainImage && 
    car.mainImage !== 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600' && 
    car.mainImage.trim() !== '';
  
  const canCancelBooking = isReserved && reservation &&
    (userRole === 'admin' || reservation.createdByUserId === currentUserId);

  const printCarDetails = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    printWindow.document.write(`
      <html lang="${lang}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}">
      <head>
        <title>${getTranslation(lang, 'printCard')} - ${car.make} ${car.model}</title>
        <style>
          body { font-family: 'Inter', system-ui, -apple-system, sans-serif; padding: 40px; color: #1e293b; }
          .header { text-align: center; border-bottom: 2px solid #cbd5e1; padding-bottom: 20px; margin-bottom: 30px; }
          .title { font-size: 22px; font-weight: 800; color: #0f172a; }
          .meta { font-size: 13px; color: #64748b; margin-top: 5px; }
          .grid { display: grid; grid-template-cols: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
          .item { border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 12px; }
          .label { font-weight: bold; color: #475569; }
          .val { float: ${lang === 'ar' ? 'left' : 'right'}; font-family: monospace; font-weight: bold; }
          .section-title { font-size: 14px; font-weight: bold; color: #4f46e5; margin-top: 25px; border-bottom: 1px solid #4f46e5; padding-bottom: 5px; }
          .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 40px; }
          @media print {
            body { padding: 0; }
          }
        </style>
      </head>
      <body>
        <div class="header">
          <div class="title">${getTranslation(lang, 'companyName')}</div>
          <div class="meta">${getTranslation(lang, 'printCard')}</div>
        </div>
        
        <div class="grid">
          <div class="item"><span class="label">${getTranslation(lang, 'make')}:</span> <span class="val">${car.make}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'model')}:</span> <span class="val">${car.model}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'trim')}:</span> <span class="val">${car.trim}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'modelYear')}:</span> <span class="val">${car.year}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'color')}:</span> <span class="val">${car.color}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'interiorColor')}:</span> <span class="val">${car.interiorColor || ''}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'vin')}:</span> <span class="val">${car.vin}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'plateNumber')}:</span> <span class="val">${car.plateNumber}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'transmission')}:</span> <span class="val">${car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual')}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'fuelType')}:</span> <span class="val">${getTranslation(lang, car.fuelType as any)}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'odometer')}:</span> <span class="val">${car.odometer.toLocaleString('en-US')} KM</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'price')}:</span> <span class="val" style="color: #4f46e5; font-size: 14px;">${car.price.toLocaleString('en-US')} ${getTranslation(lang, 'currency')}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'branch')}:</span> <span class="val">${branchName}</span></div>
          <div class="item"><span class="label">${getTranslation(lang, 'warranty')}:</span> <span class="val">${car.warranty || ''}</span></div>
        </div>

        <div class="section-title">${getTranslation(lang, 'gulfSpecs')}</div>
        <div style="font-size: 11px; color: #475569; line-height: 1.6; margin-top: 10px;">
          ${car.notes || ''}
        </div>

        ${isReserved && reservation ? `
          <div style="border: 1px solid #f43f5e; background: #fff1f2; padding: 15px; border-radius: 8px; margin-top: 30px;">
            <h4 style="margin: 0 0 10px 0; color: #be123c; font-size: 13px;">${getTranslation(lang, 'reservationNotes')}</h4>
            <div style="font-size: 11px; line-height: 1.6;">
              <strong>${getTranslation(lang, 'customerName')}:</strong> ${reservation.customerName} <br/>
              <strong>${getTranslation(lang, 'customerPhone')}:</strong> ${reservation.customerPhone} <br/>
              <strong>${getTranslation(lang, 'repInCharge')}:</strong> ${reservation.createdByUserName}
            </div>
          </div>
        ` : ''}

        <div class="footer">
          Printed automatically from Elite Enterprise Showroom Terminal. Authorized and Verified.
        </div>
        <script>window.print();</script>
      </body>
      </html>
    `);
    printWindow.document.close();
  };

  return (
    <div id={`car-card-${car.id}`} className="bg-[#0e1424] border border-slate-800/80 rounded-lg p-3 flex flex-col gap-3 relative overflow-hidden hover:border-slate-700 transition-all group h-full text-slate-200 shadow-md">
      
      {/* 1. CARD TOP IMAGE CONTAINER */}
      <div className="relative h-36 overflow-hidden bg-slate-950 shrink-0 rounded">
        {hasImage ? (
          <div className="relative w-full h-full cursor-pointer" onClick={() => setIsZoomed(true)}>
            <img 
              src={car.mainImage} 
              alt={`${car.make} ${car.model}`}
              className="w-full h-full object-cover group-hover:scale-102 transition-transform duration-500"
              referrerPolicy="no-referrer"
            />
            <div className="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center text-white text-xs font-bold gap-1">
              <span className="p-1.5 rounded-full bg-indigo-600/90 text-white shadow-lg border border-indigo-500 flex items-center justify-center">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                </svg>
              </span>
              <span className="text-[10px] bg-slate-950/80 px-1.5 py-0.5 rounded border border-slate-800 backdrop-blur-sm">
                {lang === 'ar' ? 'عرض الصورة كاملة' : 'View Full Image'}
              </span>
            </div>
          </div>
        ) : (
          <div className="w-full h-full bg-slate-950/40 flex flex-col items-center justify-center text-slate-600 gap-1">
            <span className="p-2 rounded-full bg-slate-900 border border-slate-800">
              <svg className="w-6 h-6 text-indigo-500/80" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.124V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.09-1.423a2.43 2.43 0 00-2.316-2.285L18.75 9.75H5.25L3.6 14.922A2.43 2.43 0 001.284 17.2l-.09 1.422C1.155 19.246 1.663 19.75h1.125m15-4.5v-2.25M12 9.75v-1.5m0 0H8.25m3.75 0h3.75M9 6h6"></path>
              </svg>
            </span>
          </div>
        )}
        
        {/* Branch Pin Badge (Top Right) */}
        <div className="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-950/70 text-slate-200 backdrop-blur-sm border border-slate-800/40 flex items-center gap-0.5">
          <MapPin className="w-2.5 h-2.5 text-indigo-400" />
          <span>{branchName}</span>
        </div>

        {/* Status indicator overlay (Top Left) */}
        <div className="absolute top-2 left-2">
          {!isReserved ? (
            <span className="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-0.5">
              ● {lang === 'ar' ? 'متاحة للبيع' : 'Available for Sale'}
            </span>
          ) : (
            <span className="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-0.5">
              ● {lang === 'ar' ? 'محجوزة' : 'Reserved'}
            </span>
          )}
        </div>
      </div>

      {/* 2. SPEC SHEET INFORMATION */}
      <div className="flex-1 flex flex-col justify-between gap-2.5">
        <div className="space-y-2.5">
          
          {/* Header (Make, Model, Year) */}
          <div className="flex items-start justify-between min-h-[38px] text-right">
            <span className="text-[11px] text-slate-500 font-mono self-start pt-0.5">{car.year}</span>
            <div>
              <span className="text-[12px] font-extrabold text-blue-500 block leading-none">{car.make}</span>
              <h4 className="font-bold text-[11px] text-white mt-1 leading-tight">
                {car.model} <span className="text-[10px] text-slate-400 font-normal">({car.trim})</span>
              </h4>
            </div>
          </div>

          {/* Quick specs row (Fuel & Transmission) */}
          <div className="flex items-center justify-between text-[10px] text-slate-400 border-t border-slate-800/60 pt-2 pb-0.5">
            <div className="flex items-center gap-1">
              <Fuel className="w-3 h-3 text-slate-500" />
              <span>{getTranslation(lang, car.fuelType as any)}</span>
            </div>
            <div className="flex items-center gap-1">
              <Wrench className="w-3 h-3 text-slate-500" />
              <span>{car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual')}</span>
            </div>
          </div>

          {/* Plate and VIN block (Dark Centered Plate Container) */}
          <div className="bg-[#080d1a] border border-slate-800/80 rounded p-2 text-center space-y-1">
            <div className="text-[10px] font-bold text-slate-200">
              {lang === 'ar' ? 'رقم اللوحة:' : 'Plate:'} {car.plateNumber}
            </div>
            <div className="text-[9px] font-mono text-slate-500">
              {lang === 'ar' ? 'رقم الهيكل:' : 'VIN:'} {car.vin}
            </div>
          </div>

          {/* Reserved Status Box (if applicable) */}
          {isReserved && reservation && (
            <div className="p-2 rounded bg-rose-500/5 border border-rose-500/10 text-rose-450 text-rose-400 text-[9px] leading-relaxed text-right">
              <div className="font-bold flex items-center gap-1 mb-0.5 text-rose-400 justify-end">
                <CalendarDays className="w-3 h-3" />
                <span>{getTranslation(lang, 'customerName')}: {reservation.customerName}</span>
              </div>
              <div className="text-slate-400">{getTranslation(lang, 'repInCharge')}: {reservation.createdByUserName}</div>
              <div className="text-slate-500">
                {lang === 'ar' ? `المدة: ${reservation.duration} أيام` : `Hold: ${reservation.duration} Days`}
              </div>
              {onViewReservationDetail && (
                <button
                  onClick={() => onViewReservationDetail(reservation)}
                  className="mt-1.5 w-full py-1 text-[9px] bg-indigo-600/15 hover:bg-indigo-650 text-indigo-300 font-bold rounded border border-indigo-500/20 transition cursor-pointer"
                >
                  {lang === 'ar' ? 'عرض تفاصيل الحجز الفوري' : 'View Reservation Details'}
                </button>
              )}
            </div>
          )}

          {/* 📂 المرفقات والمستندات المتاحة للسيارة */}
          <div className="border-t border-slate-800/80 pt-2.5 space-y-1.5 text-right">
            <span className="text-[10px] text-slate-400 font-bold flex items-center gap-1 justify-start">
              <span>📂</span>
              <span>{lang === 'ar' ? 'المرفقات والمستندات المتاحة للسيارة:' : 'Available Car Documents & Attachments:'}</span>
            </span>
            <div className="w-full">
              {(userRole === 'admin' || (isReserved && reservation && reservation.createdByUserId === currentUserId)) ? (
                <>
                  {(car.cardFilePath || (car.attachments && car.attachments.some(att => att.category === 'customs_document' || att.category === 'customs_card' || att.category === 'customs_file'))) ? (
                    <button
                      type="button"
                      onClick={() => onViewAttachments && onViewAttachments(car)}
                      className="w-full flex items-center justify-center gap-1.5 py-1.5 rounded bg-indigo-500/10 hover:bg-indigo-600/20 border border-indigo-500/20 hover:border-indigo-500/40 text-indigo-300 hover:text-white transition text-[10px] font-bold cursor-pointer"
                    >
                      <span>📁</span>
                      <span>{lang === 'ar' ? 'مستند جمركي' : 'Customs Document'}</span>
                    </button>
                  ) : (
                    <div className="text-[9px] text-slate-500 bg-slate-950/40 py-1.5 rounded border border-slate-850/60 text-center select-none">
                      {lang === 'ar' ? 'لا توجد بطاقة جمركية مرفوعة' : 'No customs document uploaded'}
                    </div>
                  )}
                </>
              ) : (
                <div className="text-[9px] text-slate-500 bg-slate-950/45 py-1.5 px-2 rounded border border-slate-850/60 select-none flex items-center gap-1 w-full justify-center text-center">
                  <span className="text-amber-500">🔒</span>
                  <span>{lang === 'ar' ? 'مستند جمركي (لا يظهر للمندوب إلا بعد الحجز لنفسه)' : 'Customs Card (Only shown to representative after booking themselves)'}</span>
                </div>
              )}
            </div>
          </div>

        </div>

        {/* 3. CARD BOTTOM PRICE & ACTIONS */}
        <div className="mt-2 pt-2 border-t border-slate-800/80 flex flex-col gap-2">
          
          {/* Price block */}
          <div className="flex items-center justify-between text-right">
            <span className="text-[10px] text-slate-500">{lang === 'ar' ? 'سعر البيع النقدي' : 'Cash Selling Price'}</span>
            <div className="flex items-baseline gap-1">
              <span className="text-[9px] text-slate-500">{lang === 'ar' ? 'العملة' : 'Currency'}</span>
              <span className="text-xs font-extrabold font-sans text-blue-400">
                {car.price.toLocaleString('en-US')} <span className="text-[9px] font-normal text-slate-500">{getTranslation(lang, 'currency')}</span>
              </span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-1.5 mt-1">

            {/* Main Action Button (Purple 📖 Action) */}
            {!isReserved ? (
              <button
                onClick={() => onReserve(car)}
                className="col-span-2 w-full py-1.5 rounded bg-[#4f46e5] hover:bg-[#4338ca] text-white font-bold text-[10px] transition flex items-center justify-center gap-1 cursor-pointer shadow-sm"
              >
                <BookOpen className="w-3.5 h-3.5 shrink-0" />
                <span>{lang === 'ar' ? 'تأكيد الحجز الفوري' : 'Confirm Instant Reservation'}</span>
              </button>
            ) : canCancelBooking ? (
              <button
                onClick={() => onCancelReservation(reservation!.id)}
                className="col-span-2 w-full py-1.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 font-bold text-[10px] hover:bg-rose-500 hover:text-white transition flex items-center justify-center gap-1 cursor-pointer"
              >
                <Undo2 className="w-3.5 h-3.5 shrink-0" />
                <span>{lang === 'ar' ? 'إلغاء الحجز الفوري' : 'Cancel Holding'}</span>
              </button>
            ) : (
              <div className="col-span-2 text-center text-[10px] font-bold text-slate-500 bg-slate-950 py-1.5 rounded border border-dashed border-slate-800 select-none">
                {getTranslation(lang, 'reserved')}
              </div>
            )}

            {isAdmin && car.status !== 'sold' && onSellReservation && (
              <button
                onClick={() => onSellReservation(reservation || {
                  id: 'direct-sale',
                  carId: car.id,
                  customerName: '',
                  customerPhone: '',
                  nationalId: '',
                  nationality: '',
                  whatsApp: '',
                  email: '',
                  customerAddress: '',
                  repInCharge: '',
                  duration: 0,
                  reason: '',
                  reservationStatus: 'completed',
                  createdByUserId: currentUserId,
                  createdByUserName: '',
                  createdAt: '',
                  reservationDate: '',
                  reservationEndDate: ''
                })}
                className="col-span-2 w-full py-1.5 rounded bg-emerald-650 hover:bg-emerald-600 text-white font-bold text-[10px] transition flex items-center justify-center gap-1 cursor-pointer shadow-md hover:shadow-emerald-950/20 border border-emerald-500/20"
              >
                <span>💰</span>
                <span>{lang === 'ar' ? 'تم البيع' : 'Mark as Sold'}</span>
              </button>
            )}

            {/* View Full Specs details block */}
            <button
              onClick={() => onViewDetails(car)}
              className="py-1 px-1.5 rounded bg-[#0a0e1a]/80 text-slate-300 hover:bg-slate-900 hover:text-white font-bold text-[10px] border border-slate-800 flex items-center justify-center gap-1.5 cursor-pointer col-span-2"
            >
              <Info className="w-3.5 h-3.5 text-blue-400 shrink-0" />
              <span>{lang === 'ar' ? 'مواصفات وتفاصيل السيارة' : 'Specifications & Details'}</span>
            </button>

            <button
              onClick={printCarDetails}
              className="py-1 px-1.5 rounded bg-[#0a0e1a]/80 text-slate-300 hover:bg-slate-900 hover:text-white font-bold text-[10px] border border-slate-800 flex items-center justify-center gap-1.5 cursor-pointer col-span-2"
            >
              <Printer className="w-3.5 h-3.5 text-indigo-400 shrink-0" />
              <span>{getTranslation(lang, 'طباعة البطاقة' as any) || (lang === 'ar' ? 'طباعة البطاقة' : 'Print Card')}</span>
            </button>

            {/* Admin-only buttons (تعديل - تكرار - حذف) */}
            {isAdmin && (
              <div className="col-span-2 grid grid-cols-3 gap-1 mt-1">
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    console.log("CarCard: Edit button clicked for car:", car.id);
                    onEdit(car);
                  }}
                  className="py-1 rounded bg-slate-900 border border-slate-800 text-blue-400 hover:text-white font-bold text-[10px] hover:bg-blue-650 transition flex items-center justify-center gap-0.5 cursor-pointer"
                >
                  <Edit3 className="w-3 h-3" />
                  <span>{lang === 'ar' ? 'تعديل' : 'Edit'}</span>
                </button>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    console.log("CarCard: Clone button clicked for car id:", car.id);
                    onClone(car.id);
                  }}
                  className="py-1 rounded bg-slate-900 border border-slate-800 text-indigo-400 hover:text-white font-bold text-[10px] hover:bg-indigo-650 transition flex items-center justify-center gap-0.5 cursor-pointer"
                >
                  <Copy className="w-3 h-3" />
                  <span>{lang === 'ar' ? 'تكرار' : 'Clone'}</span>
                </button>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    console.log("CarCard: Delete button clicked for car id:", car.id);
                    onDelete(car.id);
                  }}
                  className="py-1 rounded bg-slate-900 border border-slate-800 text-rose-400 hover:text-white font-bold text-[10px] hover:bg-rose-650 transition flex items-center justify-center gap-0.5 cursor-pointer"
                >
                  <Trash2 className="w-3 h-3" />
                  <span>{lang === 'ar' ? 'حذف' : 'Del'}</span>
                </button>
              </div>
            )}

          </div>
        </div>

      </div>

      {isZoomed && hasImage && (
        <ImageGalleryModal
          car={car}
          lang={lang}
          onClose={() => setIsZoomed(false)}
        />
      )}

    </div>
  );
}
