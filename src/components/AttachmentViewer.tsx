/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { X, FileText, Download, Eye, Lock, ShieldAlert } from 'lucide-react';
import { Car, Attachment, User, Reservation } from '../types';
import { Language, getTranslation } from '../i18n/translations';

interface AttachmentViewerProps {
  car: Car;
  currentUser: User;
  token: string;
  lang: Language;
  reservation?: Reservation;
  onClose: () => void;
}

export default function AttachmentViewer({ car, currentUser, token, lang, reservation, onClose }: AttachmentViewerProps) {
  const isAvailable = car.status === 'available';
  const isAdmin = currentUser.role === 'admin';
  const isOwner = (reservation && (reservation.createdByUserId === currentUser.id)) || 
                  (car.repInCharge && car.repInCharge === currentUser.name);

  // Restricted if not admin and (the car is available OR current representative doesn't own the active booking)
  const isRestricted = !isAdmin && (isAvailable || !isOwner);

  // Allow authorized users (admins and booking owners) to see all attachments including the customs card
  const baseAttachments = car.attachments || [];
  const attachmentsToDisplay = [...baseAttachments];

  if (car.cardFilePath) {
    const alreadyExists = attachmentsToDisplay.some(
      att => att.url === car.cardFilePath || att.id === 'customs-card' || att.id === 'att-card-sync'
    );
    if (!alreadyExists) {
      attachmentsToDisplay.unshift({
        id: 'customs-card',
        name: car.cardFileName || (lang === 'ar' ? 'البطاقة الجمركية الرسمية' : 'Official Customs Card'),
        url: car.cardFilePath,
        type: car.cardFileType || 'pdf',
        size: (car as any).cardFileSize || '1.2 MB',
        createdAt: car.cardFileDate || car.createdAt,
        category: 'customs_card'
      } as any);
    }
  }

  return (
    <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4 text-right">
      <div className="bg-slate-900 rounded-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl border border-slate-800">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
              <FileText className="w-4 h-4 text-indigo-400" />
              <span>{lang === 'ar' ? 'مرفقات ومستندات المركبة الرسمية' : 'Official Vehicle Documents'}</span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2">
              <span>{car.make} {car.model}</span>
              <span>•</span>
              <span>{lang === 'ar' ? 'لوحة:' : 'Plate:'} {car.plateNumber}</span>
              {(reservation?.createdByUserName || car.repInCharge) && (
                <>
                  <span>•</span>
                  <span className="text-indigo-400 font-bold">
                    {lang === 'ar' ? `المندوب الحاجز: ${reservation?.createdByUserName || car.repInCharge}` : `Booking Rep: ${reservation?.createdByUserName || car.repInCharge}`}
                  </span>
                </>
              )}
            </p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-6 overflow-y-auto space-y-4 flex-1">
          {isRestricted ? (
            /* Restricted / Locked Screen for Representatives */
            <div className="text-center py-8 px-4 space-y-4">
              <div className="w-14 h-14 rounded-full bg-rose-500/10 text-rose-400 flex items-center justify-center mx-auto border border-rose-500/20">
                <Lock className="w-6 h-6 animate-pulse" />
              </div>
              <div className="space-y-2 max-w-md mx-auto">
                <h4 className="font-extrabold text-sm text-white">
                  {isAvailable 
                    ? (lang === 'ar' ? 'يرجى حجز السيارة أولاً للوصول للمرفقات' : 'Please hold/book the vehicle first')
                    : (lang === 'ar' ? 'صلاحيات الوصول غير كافية' : 'Insufficient access rights')
                  }
                </h4>
                <p className="text-xs text-slate-400 leading-relaxed font-medium">
                  {isAvailable ? (
                    lang === 'ar' 
                      ? 'لأسباب أمنية وتماشياً مع سياسة الخصوصية وحماية أصول المعرض، لا يُسمح للمناديب باستعراض أو تحميل بطاقة الاستيراد، تقارير الفحص الفني، أو بطاقات الجمارك للسيارات المتاحة إلا بعد إتمام عملية حجز السيارة باسم العميل.' 
                      : 'For security reasons, representatives are not permitted to view or download import logs, technical inspections, or customs files for available cars until a reservation is successfully completed.'
                  ) : (
                    lang === 'ar'
                      ? `هذه السيارة محجوزة حالياً بواسطة المندوب: ${reservation?.createdByUserName || car.repInCharge || 'مندوب آخر'}. المرفقات الرسمية متاحة للمدير وللموظف المسؤول عن الحجز فقط.`
                      : `This vehicle is currently reserved by representative: ${reservation?.createdByUserName || car.repInCharge || 'another representative'}. Official documents are restricted to the administrator and the representative in charge.`
                  )}
                </p>
              </div>

              <div className="pt-2">
                <button
                  onClick={onClose}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 rounded transition cursor-pointer"
                >
                  {lang === 'ar' ? 'إغلاق النافذة' : 'Close Window'}
                </button>
              </div>
            </div>
          ) : attachmentsToDisplay.length === 0 ? (
            /* Empty State */
            <div className="text-center py-10 space-y-3">
              <FileText className="w-10 h-10 text-slate-600 mx-auto" />
              <p className="text-xs text-slate-400 font-bold">
                {lang === 'ar' ? 'لا يوجد مستندات أو مرفقات مسجلة لهذه السيارة حالياً.' : 'No documents recorded for this vehicle.'}
              </p>
              {isAdmin && (
                <p className="text-[9px] text-slate-500">
                  {lang === 'ar' ? 'يمكن للمدير رفع بطاقات الاستيراد الجمركي وتفارير الفحص من خلال تعديل بيانات السيارة.' : 'Admin can upload technical specs, inspection reports, and customs files via Edit Car.'}
                </p>
              )}
            </div>
          ) : (
            /* Authorized Attachment List */
            <div className="divide-y divide-slate-800">
              {attachmentsToDisplay.map((att) => {
                const secureUrl = `/api/attachments/secure/${car.id}/${att.id}?token=${token}`;
                const secureDownloadUrl = `${secureUrl}&download=true`;
                const isImage = att.type === 'image' || att.url.endsWith('.png') || att.url.endsWith('.jpg') || att.url.endsWith('.jpeg') || att.url.startsWith('data:image/');

                return (
                  <div key={att.id} className="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded bg-indigo-600/10 text-indigo-400 flex items-center justify-center shrink-0">
                        <FileText className="w-4 h-4" />
                      </div>
                      <div>
                        <h4 className="font-bold text-xs text-slate-200">{att.name}</h4>
                        <span className="text-[9px] text-slate-500 font-sans block mt-0.5">
                          {att.size} | {lang === 'ar' ? 'تاريخ الرفع:' : 'Uploaded:'} {new Date(att.createdAt).toLocaleDateString(lang === 'ar' ? 'ar-SA' : 'en-US')}
                        </span>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      {/* Download Link */}
                      <a
                        href={secureDownloadUrl}
                        className="px-3 py-1.5 rounded bg-slate-950 hover:bg-slate-850 border border-slate-800 text-xs font-bold text-slate-300 flex items-center gap-1 transition cursor-pointer"
                      >
                        <Download className="w-3.5 h-3.5" />
                        <span>{lang === 'ar' ? 'تحميل المرفق' : 'Download File'}</span>
                      </a>

                      {/* View Preview Button */}
                      <a
                        href={secureUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-1 transition cursor-pointer shadow shadow-indigo-600/10"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>{lang === 'ar' ? 'معاينة المستند' : 'Preview Document'}</span>
                      </a>
                    </div>

                    {/* Preview embedded image if supported */}
                    {isImage && (
                      <div className="w-full mt-2 rounded border border-slate-800 bg-slate-950 p-1.5 max-h-48 md:hidden">
                        <img src={secureUrl} alt={att.name} className="max-h-44 object-contain mx-auto" referrerPolicy="no-referrer" />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
