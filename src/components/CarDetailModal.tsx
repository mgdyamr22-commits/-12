/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { 
  X, 
  MapPin, 
  Wrench, 
  ShieldCheck, 
  DollarSign, 
  FileText, 
  Scale, 
  Bookmark, 
  Eye, 
  ExternalLink,
  ChevronRightSquare,
  Award
} from 'lucide-react';
import { Car, Branch, Reservation } from '../types';
import { Language, getTranslation } from '../i18n/translations';
import ImageGalleryModal from './ImageGalleryModal';

interface CarDetailModalProps {
  car: Car;
  branches: Branch[];
  reservation?: Reservation;
  lang: Language;
  onClose: () => void;
}

export default function CarDetailModal({
  car,
  branches,
  reservation,
  lang,
  onClose
}: CarDetailModalProps) {
  
  const [isZoomed, setIsZoomed] = React.useState(false);
  const branch = branches.find(b => b.id === car.branchId);
  const branchName = branch ? branch.name : getTranslation(lang, 'other');

  const hasImage = car.mainImage && 
    car.mainImage !== 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600' && 
    car.mainImage.trim() !== '';

  // Specs helper
  const hasSpec = (val?: boolean) => val ? '✔' : '✖';

  return (
    <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto text-right">
      <div className="bg-slate-900 rounded-xl w-full max-w-4xl shadow-2xl border border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <span className="text-[10px] text-indigo-400 font-extrabold uppercase tracking-widest font-mono">
              {car.vin}
            </span>
            <h3 className="font-extrabold text-sm text-white mt-0.5">
              {car.make} {car.model} <span className="text-slate-400 font-normal">({car.year})</span>
            </h3>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Bento Grid Body */}
        <div className="flex-1 p-4 space-y-4 overflow-y-auto custom-scrollbar text-slate-200">
          
          {/* Main Photo & Summary Card */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {hasImage ? (
              <div 
                className="md:col-span-2 relative h-48 bg-slate-950 rounded-lg overflow-hidden border border-slate-850 cursor-pointer group"
                onClick={() => setIsZoomed(true)}
              >
                <img src={car.mainImage} alt={car.model} className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-102" />
                <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
                <div className="absolute inset-0 bg-slate-950/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center text-white text-xs font-bold gap-1">
                  <span className="p-1.5 rounded-full bg-indigo-600/90 text-white shadow-lg border border-indigo-500 flex items-center justify-center">
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                    </svg>
                  </span>
                  <span className="text-[10px] bg-slate-950/80 px-1.5 py-0.5 rounded border border-slate-800 backdrop-blur-sm">
                    {lang === 'ar' ? 'عرض الصورة كاملة' : 'View Full Image'}
                  </span>
                </div>
                <div className="absolute bottom-3 right-3">
                  <span className="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-900/90 text-slate-200 border border-slate-800">
                    {car.vehicleCondition || 'جديد (أصفار)'}
                  </span>
                </div>
              </div>
            ) : (
              <div className="md:col-span-2 relative h-[135px] bg-slate-950/40 rounded-lg overflow-hidden border border-slate-800/80 flex items-center justify-center">
                <div className="flex flex-col items-center justify-center text-slate-600 gap-1.5">
                  <span className="p-3 rounded-full bg-slate-900 border border-slate-800">
                    <svg className="w-8 h-8 text-indigo-500/80" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.09-1.423a2.43 2.43 0 00-2.316-2.285L18.75 9.75H5.25L3.6 14.922A2.43 2.43 0 001.284 17.2l-.09 1.422C1.155 19.246 1.663 19.75h1.125m15-4.5v-2.25M12 9.75v-1.5m0 0H8.25m3.75 0h3.75M9 6h6"></path>
                    </svg>
                  </span>
                  <span className="text-[10px] text-slate-500 font-bold">{lang === 'ar' ? 'لا توجد صورة مرفوعة' : 'No image uploaded'}</span>
                </div>
                <div className="absolute bottom-3 right-3">
                  <span className="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-900/90 text-slate-200 border border-slate-800">
                    {car.vehicleCondition || 'جديد (أصفار)'}
                  </span>
                </div>
              </div>
            )}

            {/* Price Box */}
            <div className="bg-slate-950/40 p-4 border border-slate-800/80 rounded-lg flex flex-col justify-between">
              <div className="space-y-1">
                <span className="text-[10px] text-slate-500 font-bold block">{getTranslation(lang, 'price')}</span>
                <span className="text-xl font-black text-indigo-400 block font-sans">
                  {car.price.toLocaleString('en-US')} <span className="text-xs font-normal text-slate-500">{getTranslation(lang, 'currency')}</span>
                </span>
                <span className="text-[9px] text-slate-500 block">
                  + {getTranslation(lang, 'tax')}: {car.tax ? car.tax.toLocaleString() : '15% VAT'}
                </span>
              </div>

              <div className="pt-3 border-t border-slate-900 space-y-1.5 text-[10px]">
                <div className="flex justify-between">
                  <span className="text-slate-500">{getTranslation(lang, 'branch')}:</span>
                  <span className="font-bold text-slate-300">{branchName}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">{getTranslation(lang, 'transmission')}:</span>
                  <span className="font-bold text-slate-300">
                    {car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual')}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">{getTranslation(lang, 'fuelType')}:</span>
                  <span className="font-bold text-slate-300">{getTranslation(lang, car.fuelType as any)}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Grid Details */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            {/* Box 1: Logistic Identities */}
            <div className="bg-slate-950/20 border border-slate-850 p-3.5 rounded-lg space-y-2.5">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <Scale className="w-3.5 h-3.5" />
                <span>{lang === 'ar' ? 'الهوية الجمركية والترخيصية للسيارة' : 'Customs & Port Registration Logs'}</span>
              </h4>
              <div className="grid grid-cols-2 gap-x-3 gap-y-2 text-[10px] text-slate-300">
                <div>
                  <span className="text-slate-500 block">{getTranslation(lang, 'vin')}</span>
                  <span className="font-mono font-bold text-slate-200 block">{car.vin}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{getTranslation(lang, 'plateNumber')}</span>
                  <span className="font-bold text-slate-200 block">{car.plateNumber}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'نوع اللوحة' : 'Plate Type'}</span>
                  <span className="font-bold text-slate-300 block">{car.plateType || 'خصوصي'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{getTranslation(lang, 'odometer')}</span>
                  <span className="font-sans font-bold text-slate-200 block">{(car.odometer || 0).toLocaleString()} KM</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'الرقم التسلسلي للبطاقة' : 'Serial Card No'}</span>
                  <span className="font-sans font-bold text-slate-300 block">{car.serialNumber || '482-1192-30'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'رقم الاستمارة الإلكترونية' : 'Istimara Registration No'}</span>
                  <span className="font-sans font-bold text-slate-300 block">{car.registrationNumber || '2301948293'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'الرقم الجمركي للاستيراد' : 'Customs Inflow Number'}</span>
                  <span className="font-sans font-bold text-slate-300 block">{car.customsNumber || '8392-10'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'بلد المنشأ / التجميع' : 'Origin & Assembly'}</span>
                  <span className="font-bold text-slate-300 block">{car.originCountry || 'اليابان'} / {car.assemblyCountry || 'اليابان'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'مطابقة الهيكل' : 'VIN Matching'}</span>
                  <span className={`font-bold block ${car.vinMatching === 'mismatch' ? 'text-rose-400' : 'text-emerald-400'}`}>
                    {car.vinMatching === 'mismatch' ? (lang === 'ar' ? '❌ غير مطابق' : 'Mismatch') : (lang === 'ar' ? '✅ مطابق' : 'Matching')}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'المورد / المصدر' : 'Supplier'}</span>
                  <span className="font-bold text-slate-300 block">{car.supplier || (lang === 'ar' ? 'غير محدد' : 'N/A')}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'مالك المركبة' : 'Ownership Type'}</span>
                  <span className="font-bold text-slate-300 block">{car.ownershipType || (lang === 'ar' ? 'مباشر' : 'Direct')}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'التجيير' : 'Leasing Status'}</span>
                  <span className="font-bold text-slate-300 block">
                    {car.leasingStatus === 'leased' ? (lang === 'ar' ? 'مجير' : 'Leased') : (lang === 'ar' ? 'لم يجير' : 'Not Leased')}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'حالة السيارة بالمستودع' : 'Vehicle Status'}</span>
                  <span className="font-bold block text-indigo-400">
                    {car.status === 'available' && (lang === 'ar' ? '🟢 متوفرة' : 'Available')}
                    {car.status === 'reserved' && (lang === 'ar' ? '🟡 محجوزة' : 'Reserved')}
                    {car.status === 'sold' && (lang === 'ar' ? '🔵 مباعة' : 'Sold')}
                    {car.status === 'not_for_sale' && (lang === 'ar' ? '🔴 غير معروضة للبيع' : 'Not For Sale')}
                    {car.status === 'out_of_stock' && (lang === 'ar' ? '⚫ خارج المخزن' : 'Out of Stock')}
                    {!car.status && (lang === 'ar' ? '🟢 متوفرة' : 'Available')}
                  </span>
                </div>
                <div className="col-span-2 mt-1 pt-1.5 border-t border-slate-900 flex justify-between items-center">
                  <span className="text-slate-500">{lang === 'ar' ? 'مندوب الحجز المسؤول:' : 'Booking Rep:'}</span>
                  <span className="font-bold text-indigo-300 text-[11px]">
                    {car.repInCharge || (lang === 'ar' ? 'لا يوجد حجز نشط' : 'No active booking')}
                  </span>
                </div>
              </div>
            </div>

            {/* Box 2: Mechanics & Engine Specs */}
            <div className="bg-slate-950/20 border border-slate-850 p-3.5 rounded-lg space-y-2.5">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <Wrench className="w-3.5 h-3.5" />
                <span>{lang === 'ar' ? 'المواصفات الميكانيكية والهندسة' : 'Mechanical & Powertrain Specs'}</span>
              </h4>
              <div className="grid grid-cols-2 gap-x-3 gap-y-2 text-[10px] text-slate-300">
                <div>
                  <span className="text-slate-500 block">{getTranslation(lang, 'color')}</span>
                  <span className="font-bold text-slate-200 block">{car.color}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'اللون الداخلي' : 'Interior Cabin Color'}</span>
                  <span className="font-bold text-slate-200 block">{car.interiorColor || 'أسود جلد'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'هيكل السيارة' : 'Body Configuration'}</span>
                  <span className="font-bold text-slate-300 block">{car.bodyType || 'سيدان'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'السعة اللترية للمحرك' : 'Engine Capacity CC'}</span>
                  <span className="font-sans font-bold text-slate-200 block">{car.engineCapacity || '2000'} CC</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'عدد الاسطوانات (سلندر)' : 'Engine Cylinders'}</span>
                  <span className="font-sans font-bold text-slate-300 block">{car.cylinders || '4'} Cylinders</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'القوة الحصانية للمحرك' : 'Powertrain Output HP'}</span>
                  <span className="font-sans font-bold text-slate-300 block">{car.enginePower || '180'} HP</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'نظام الدفع والجر' : 'Drivetrain System'}</span>
                  <span className="font-bold text-slate-300 block">{car.drive || 'FWD'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'الضمان وعدد السنوات' : 'Dealer Extended Warranty'}</span>
                  <span className="font-bold text-emerald-400 block">{car.warranty || 'ضمان الوكيل'} ({car.warrantyDuration || 5} {lang === 'ar' ? 'سنوات' : 'Years'})</span>
                </div>
              </div>
            </div>

          </div>

          {/* Specifications Checkboxes bento panel */}
          {car.specs && (
            <div className="bg-slate-950/20 border border-slate-850 p-3.5 rounded-lg space-y-2.5">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <ShieldCheck className="w-3.5 h-3.5" />
                <span>{lang === 'ar' ? 'قائمة تفصيل المواصفات الفنية والتقنية والرفاهية' : 'Integrated Technical Features Checklist'}</span>
              </h4>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-[9px] text-slate-300 font-bold">
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.gulfSpecs)}</span>
                  <span>{getTranslation(lang, 'gulfSpecs')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.americanSpecs)}</span>
                  <span>{getTranslation(lang, 'americanSpecs')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.navigationSystem)}</span>
                  <span>{getTranslation(lang, 'navigationSystem')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.rearCamera)}</span>
                  <span>{getTranslation(lang, 'rearCamera')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.camera360)}</span>
                  <span>{getTranslation(lang, 'camera360')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.radar)}</span>
                  <span>{getTranslation(lang, 'radar')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.frontSensors)}</span>
                  <span>{getTranslation(lang, 'frontSensors')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.rearSensors)}</span>
                  <span>{getTranslation(lang, 'rearSensors')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.cruiseControl)}</span>
                  <span>{getTranslation(lang, 'cruiseControl')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.adaptiveCruise)}</span>
                  <span>{getTranslation(lang, 'adaptiveCruise')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.laneAssist)}</span>
                  <span>{getTranslation(lang, 'laneAssist')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.blindSpot)}</span>
                  <span>{getTranslation(lang, 'blindSpot')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.appleCarPlay)}</span>
                  <span>{getTranslation(lang, 'appleCarPlay')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.androidAuto)}</span>
                  <span>{getTranslation(lang, 'androidAuto')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.sunroof)}</span>
                  <span>{getTranslation(lang, 'sunroof')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.panorama)}</span>
                  <span>{getTranslation(lang, 'panorama')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.leatherSeats)}</span>
                  <span>{getTranslation(lang, 'leatherSeats')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.heatedSeats)}</span>
                  <span>{getTranslation(lang, 'heatedSeats')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.cooledSeats)}</span>
                  <span>{getTranslation(lang, 'cooledSeats')}</span>
                </div>
                <div className="flex items-center gap-1 p-1 bg-slate-900/40 rounded border border-slate-800">
                  <span className="text-indigo-400">{hasSpec(car.specs.pushButtonStart)}</span>
                  <span>{getTranslation(lang, 'pushButtonStart')}</span>
                </div>
              </div>
            </div>
          )}

          {/* Active Contract / Completed Receipt (Sale details section) */}
          {car && car.sale && (
            <div className="p-3.5 rounded-lg border border-indigo-500/20 bg-indigo-950/15 space-y-2.5">
              <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-1.5 flex items-center gap-1.5">
                <FileText className="w-3.5 h-3.5 text-indigo-400" />
                <span>{lang === 'ar' ? 'سجل العقد الضريبي وتفاصيل الفاتورة والمبيعات' : 'Tax invoice & sales contract report'}</span>
              </h4>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-[10px] text-slate-300">
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'رقم العقد القانوني' : 'Sales Contract No'}</span>
                  <span className="font-mono font-bold text-slate-200 block">{car.sale.contractNumber}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'رقم الفاتورة المعتمد' : 'Certified Invoice No'}</span>
                  <span className="font-mono font-bold text-slate-200 block">{car.sale.invoiceNumber}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'المندوب البائع' : 'Representative Seller'}</span>
                  <span className="font-bold text-slate-200 block">{car.sale.sellerName}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'طريقة السداد' : 'Payment Type'}</span>
                  <span className="font-bold text-slate-200 block">{car.sale.paymentMethod}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'حالة السداد والتحويل' : 'Payment Clearance Status'}</span>
                  <span className="font-bold text-emerald-400 block">{car.sale.paymentStatus === 'paid' ? 'خالص ومدفوع بالكامل' : 'تحت التسوية'}</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'المبلغ المسدد بالفعل' : 'Capitalized Paid Amount'}</span>
                  <span className="font-sans font-bold text-emerald-400 block">{car.sale.paidAmount.toLocaleString()} ر.س</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'المبلغ المتبقي المعلق' : 'Outstanding Remaining Debt'}</span>
                  <span className="font-sans font-bold text-rose-400 block">{car.sale.remainingAmount.toLocaleString()} ر.س</span>
                </div>
                <div>
                  <span className="text-slate-500 block">{lang === 'ar' ? 'موعد وطريقة التسليم' : 'Delivery Log Date'}</span>
                  <span className="font-bold text-slate-200 block">{car.sale.deliveryDate} ({car.sale.deliveryMethod.slice(0,18)})</span>
                </div>
              </div>
            </div>
          )}

        </div>

        {/* Footer Actions */}
        <div className="p-4 border-t border-slate-800 flex justify-end bg-slate-950">
          <button
            onClick={onClose}
            className="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded cursor-pointer"
          >
            {getTranslation(lang, 'close')}
          </button>
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
