/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { X, User, Calendar, FileText, Download } from 'lucide-react';
import { Car, Reservation } from '../types';
import { Language } from '../i18n/translations';

interface ReservationDetailModalProps {
  reservation: Reservation;
  car: Car;
  token: string;
  lang: Language;
  onClose: () => void;
}

export default function ReservationDetailModal({
  reservation,
  car,
  token,
  lang,
  onClose
}: ReservationDetailModalProps) {
  
  const hasImage = car.mainImage && 
    car.mainImage !== 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600' && 
    car.mainImage.trim() !== '';

  // Format creation date beautifully
  const formattedDate = new Date(reservation.createdAt).toLocaleString(
    lang === 'ar' ? 'ar-SA' : 'en-US',
    {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }
  );

  const hasAttachments = car.attachments && car.attachments.length > 0;

  return (
    <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 text-right">
      <div className="bg-slate-900 rounded-xl w-full max-w-md shadow-2xl border border-slate-800 overflow-hidden flex flex-col">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
              <FileText className="w-4 h-4 text-indigo-400" />
              <span>{lang === 'ar' ? 'تفاصيل الحجز' : 'Reservation Details'}</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">
              {car.make} {car.model} | {lang === 'ar' ? 'لوحة:' : 'Plate:'} {car.plateNumber}
            </p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Body content (Requirement 8: Shows ONLY representative name, reservation date & time, and download car attachments button) */}
        <div className="p-5 space-y-5 text-slate-200">
          
          {/* Car Image / Icon Container */}
          {hasImage ? (
            <div className="relative h-40 bg-slate-950 rounded-lg overflow-hidden border border-slate-800">
              <img src={car.mainImage} alt={car.model} className="w-full h-full object-cover" />
              <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
            </div>
          ) : (
            <div className="relative h-[135px] bg-slate-950/40 rounded-lg overflow-hidden border border-slate-800/80 flex items-center justify-center">
              <div className="flex flex-col items-center justify-center text-slate-600 gap-1.5">
                <span className="p-2.5 rounded-full bg-slate-900 border border-slate-800">
                  <svg className="w-8 h-8 text-indigo-500/80" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.09-1.423a2.43 2.43 0 00-2.316-2.285L18.75 9.75H5.25L3.6 14.922A2.43 2.43 0 001.284 17.2l-.09 1.422C1.155 19.246 1.663 19.75h1.125m15-4.5v-2.25M12 9.75v-1.5m0 0H8.25m3.75 0h3.75M9 6h6"></path>
                  </svg>
                </span>
                <span className="text-[10px] text-slate-500 font-bold">{lang === 'ar' ? 'لا توجد صورة مرفوعة' : 'No image uploaded'}</span>
              </div>
            </div>
          )}
          
          {/* Representative Name */}
          <div className="bg-slate-950/40 p-3.5 rounded-lg border border-slate-800/60 flex items-center gap-3">
            <div className="w-8 h-8 rounded bg-indigo-600/10 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/10">
              <User className="w-4 h-4" />
            </div>
            <div>
              <span className="text-[10px] text-slate-500 block font-bold">
                {lang === 'ar' ? 'اسم المندوب المسؤول' : 'Representative Name'}
              </span>
              <span className="text-xs font-bold text-white block mt-0.5">
                {reservation.createdByUserName}
              </span>
            </div>
          </div>

          {/* Reservation Date and Time */}
          <div className="bg-slate-950/40 p-3.5 rounded-lg border border-slate-800/60 flex items-center gap-3">
            <div className="w-8 h-8 rounded bg-emerald-600/10 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/10">
              <Calendar className="w-4 h-4" />
            </div>
            <div>
              <span className="text-[10px] text-slate-500 block font-bold">
                {lang === 'ar' ? 'تاريخ ووقت الحجز' : 'Reservation Date & Time'}
              </span>
              <span className="text-xs font-mono font-bold text-emerald-400 block mt-0.5">
                {formattedDate}
              </span>
            </div>
          </div>

        </div>

        {/* Footer Actions */}
        <div className="p-3 border-t border-slate-800 flex justify-end bg-slate-950">
          <button
            onClick={onClose}
            className="px-4 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded cursor-pointer transition-colors"
          >
            {lang === 'ar' ? 'إغلاق' : 'Close'}
          </button>
        </div>

      </div>
    </div>
  );
}
