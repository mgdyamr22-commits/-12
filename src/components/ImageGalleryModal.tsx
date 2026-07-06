/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import { X, ChevronLeft, ChevronRight, Image as ImageIcon } from 'lucide-react';
import { Car } from '../types';

interface ImageGalleryModalProps {
  car: Car;
  lang: 'ar' | 'en';
  onClose: () => void;
  initialIndex?: number;
}

export default function ImageGalleryModal({ car, lang, onClose, initialIndex = 0 }: ImageGalleryModalProps) {
  // Extract all image URLs from mainImage and attachments
  const images = React.useMemo(() => {
    const list: string[] = [];
    if (car.mainImage) {
      list.push(car.mainImage);
    }
    
    if (car.attachments && Array.isArray(car.attachments)) {
      car.attachments.forEach(att => {
        const isImageMime = att.type && att.type.startsWith('image/');
        const isImageUrl = att.url && (
          att.url.startsWith('data:image/') ||
          /\.(jpg|jpeg|png|gif|webp|jfif)/i.test(att.url)
        );
        if ((isImageMime || isImageUrl) && att.url && !list.includes(att.url)) {
          list.push(att.url);
        }
      });
    }
    
    return list;
  }, [car]);

  const [currentIndex, setCurrentIndex] = React.useState(
    initialIndex >= 0 && initialIndex < images.length ? initialIndex : 0
  );

  const handleNext = () => {
    setCurrentIndex(prev => (prev + 1) % images.length);
  };

  const handlePrev = () => {
    setCurrentIndex(prev => (prev - 1 + images.length) % images.length);
  };

  // Keyboard navigation
  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'ArrowRight') {
        // In Arabic RTL, ArrowRight usually goes previous, but let's make ArrowRight/ArrowLeft intuitive
        if (lang === 'ar') {
          handlePrev();
        } else {
          handleNext();
        }
      } else if (e.key === 'ArrowLeft') {
        if (lang === 'ar') {
          handleNext();
        } else {
          handlePrev();
        }
      } else if (e.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [currentIndex, images, lang]);

  // Touch swipe support
  const touchStartX = React.useRef(0);
  const touchEndX = React.useRef(0);

  const handleTouchStart = (e: React.TouchEvent) => {
    touchStartX.current = e.targetTouches[0].clientX;
    touchEndX.current = e.targetTouches[0].clientX;
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    touchEndX.current = e.targetTouches[0].clientX;
  };

  const handleTouchEnd = () => {
    const diff = touchStartX.current - touchEndX.current;
    const swipeThreshold = 50;
    
    if (diff > swipeThreshold) {
      // Swiped Left
      if (lang === 'ar') {
        handlePrev();
      } else {
        handleNext();
      }
    } else if (diff < -swipeThreshold) {
      // Swiped Right
      if (lang === 'ar') {
        handleNext();
      } else {
        handlePrev();
      }
    }
  };

  // Mouse drag swipe support (for desktop)
  const dragStartX = React.useRef<number | null>(null);

  const handleMouseDown = (e: React.MouseEvent) => {
    dragStartX.current = e.clientX;
  };

  const handleMouseUp = (e: React.MouseEvent) => {
    if (dragStartX.current === null) return;
    const diff = dragStartX.current - e.clientX;
    const swipeThreshold = 50;

    if (diff > swipeThreshold) {
      if (lang === 'ar') {
        handlePrev();
      } else {
        handleNext();
      }
    } else if (diff < -swipeThreshold) {
      if (lang === 'ar') {
        handleNext();
      } else {
        handlePrev();
      }
    }
    dragStartX.current = null;
  };

  const handleMouseLeave = () => {
    dragStartX.current = null;
  };

  if (images.length === 0) return null;

  return (
    <div 
      className="fixed inset-0 bg-slate-950/95 backdrop-blur-md z-[9999] flex flex-col justify-between p-4 md:p-6"
      onClick={onClose}
    >
      {/* Top Bar controls */}
      <div 
        className="w-full max-w-7xl mx-auto flex items-center justify-between z-10"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-2 bg-slate-900/60 backdrop-blur-sm border border-slate-800/60 px-4 py-1.5 rounded-lg text-xs font-bold text-slate-300">
          <ImageIcon className="w-4 h-4 text-indigo-400 shrink-0" />
          <span>{car.make} {car.model} ({car.year})</span>
          <span className="opacity-40">|</span>
          <span className="font-mono">
            {currentIndex + 1} / {images.length}
          </span>
        </div>

        <button 
          onClick={onClose}
          className="p-2.5 rounded-full bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-white transition cursor-pointer"
          title={lang === 'ar' ? 'إغلاق المعرض' : 'Close Gallery'}
        >
          <X className="w-5 h-5" />
        </button>
      </div>

      {/* Main image container with arrows */}
      <div 
        className="flex-grow w-full max-w-7xl mx-auto flex items-center justify-between my-4 relative"
        onClick={(e) => e.stopPropagation()}
      >
        
        {/* Previous Button */}
        {images.length > 1 && (
          <button
            onClick={lang === 'ar' ? handleNext : handlePrev}
            className="absolute left-2 md:left-4 z-10 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 transition cursor-pointer shadow-lg hover:scale-105"
            title={lang === 'ar' ? 'الصورة التالية' : 'Previous Image'}
          >
            <ChevronLeft className="w-6 h-6" />
          </button>
        )}

        {/* Central Display Image */}
        <div 
          className="w-full h-[65vh] md:h-[75vh] flex items-center justify-center select-none cursor-grab active:cursor-grabbing relative overflow-hidden"
          onTouchStart={handleTouchStart}
          onTouchMove={handleTouchMove}
          onTouchEnd={handleTouchEnd}
          onMouseDown={handleMouseDown}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseLeave}
        >
          {/* Subtle instructions */}
          {images.length > 1 && (
            <div className="absolute top-2 px-3 py-1 rounded bg-slate-950/40 text-slate-400 text-[10px] pointer-events-none select-none">
              {lang === 'ar' 
                ? 'استخدم الأسهم، اسحب الصورة، أو اضغط للتنقل' 
                : 'Use arrows, drag image, or click to navigate'}
            </div>
          )}

          <img 
            src={images[currentIndex]} 
            alt={`${car.make} ${car.model} - ${currentIndex + 1}`} 
            className="max-w-full max-h-full object-contain rounded-lg shadow-2xl border border-slate-800/40 transition-all duration-300 pointer-events-none select-none"
            referrerPolicy="no-referrer"
          />
        </div>

        {/* Next Button */}
        {images.length > 1 && (
          <button
            onClick={lang === 'ar' ? handlePrev : handleNext}
            className="absolute right-2 md:right-4 z-10 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 transition cursor-pointer shadow-lg hover:scale-105"
            title={lang === 'ar' ? 'الصورة السابقة' : 'Next Image'}
          >
            <ChevronRight className="w-6 h-6" />
          </button>
        )}

      </div>

      {/* Thumbnails Tray at bottom */}
      {images.length > 1 && (
        <div 
          className="w-full max-w-7xl mx-auto flex justify-center items-center gap-2 overflow-x-auto py-2 z-10 px-4"
          onClick={(e) => e.stopPropagation()}
        >
          {images.map((img, idx) => (
            <button
              key={idx}
              onClick={() => setCurrentIndex(idx)}
              className={`w-14 h-10 md:w-16 md:h-12 rounded overflow-hidden shrink-0 border transition-all cursor-pointer ${
                idx === currentIndex 
                  ? 'border-indigo-500 scale-110 shadow-lg shadow-indigo-600/25 ring-1 ring-indigo-500/20' 
                  : 'border-slate-800 opacity-50 hover:opacity-100 hover:border-slate-700'
              }`}
            >
              <img 
                src={img} 
                alt="Thumbnail" 
                className="w-full h-full object-cover select-none pointer-events-none" 
                referrerPolicy="no-referrer"
              />
            </button>
          ))}
        </div>
      )}

    </div>
  );
}
