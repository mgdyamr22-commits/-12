/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useMemo } from 'react';
import { 
  ArrowUpDown, 
  ChevronDown, 
  ChevronRight, 
  Eye, 
  EyeOff, 
  SlidersHorizontal, 
  Download, 
  Printer, 
  ListCollapse, 
  ChevronLeft, 
  Maximize2,
  FolderLock,
  ArrowLeftRight,
  ChevronRightSquare,
  FileSpreadsheet
} from 'lucide-react';
import { Car, Branch, Reservation } from '../types';
import { Language, getTranslation } from '../i18n/translations';
import { downloadExcelBrowser } from '../utils/excelHelper';

interface AdvancedVehiclesTableProps {
  cars: Car[];
  branches: Branch[];
  reservations?: Reservation[];
  lang: Language;
  onEdit: (car: Car) => void;
  onDelete: (carId: string) => void;
  onReserve: (car: Car) => void;
  onViewReservationDetail?: (reservation: Reservation) => void;
  logo?: string;
  companyName?: string;
  userRole?: 'admin' | 'representative';
}

type GroupByField = 'none' | 'make' | 'year' | 'branchId' | 'status' | 'transmission';

interface ColumnConfig {
  key: keyof Car | 'branchName' | 'specs_fuel';
  labelKey: any; // key of translations
  visible: boolean;
  width: number; // in pixels
}

export default function AdvancedVehiclesTable({
  cars,
  branches,
  reservations = [],
  lang,
  onEdit,
  onDelete,
  onReserve,
  onViewReservationDetail,
  logo,
  companyName,
  userRole = 'representative'
}: AdvancedVehiclesTableProps) {
  // Columns state
  const [columns, setColumns] = useState<ColumnConfig[]>([
    { key: 'make', labelKey: 'make', visible: true, width: 100 },
    { key: 'model', labelKey: 'model', visible: true, width: 110 },
    { key: 'year', labelKey: 'modelYear', visible: true, width: 80 },
    { key: 'trim', labelKey: 'trim', visible: true, width: 120 },
    { key: 'price', labelKey: 'price', visible: true, width: 110 },
    { key: 'odometer', labelKey: 'odometer', visible: true, width: 100 },
    { key: 'color', labelKey: 'color', visible: true, width: 100 },
    { key: 'vin', labelKey: 'vin', visible: true, width: 150 },
    { key: 'vinMatching', labelKey: 'vinMatching', visible: true, width: 120 },
    { key: 'plateNumber', labelKey: 'plateNumber', visible: true, width: 120 },
    { key: 'transmission', labelKey: 'transmission', visible: true, width: 100 },
    { key: 'fuelType', labelKey: 'fuelType', visible: true, width: 90 },
    { key: 'branchId', labelKey: 'branch', visible: true, width: 140 },
    { key: 'status', labelKey: 'reservationStatus', visible: true, width: 95 }
  ]);

  // Sort State
  const [sortKey, setSortKey] = useState<keyof Car | 'branchName' | null>(null);
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');

  // Grouping state
  const [groupBy, setGroupBy] = useState<GroupByField>('none');
  const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>({});

  // Column options overlay
  const [showColOptions, setShowColOptions] = useState(false);

  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(15);

  // Helper branch lookup map
  const branchMap = useMemo(() => {
    return new Map(branches.map(b => [b.id, b.name]));
  }, [branches]);

  // Handle Sort
  const handleSort = (key: keyof Car | 'branchName') => {
    if (sortKey === key) {
      if (sortOrder === 'asc') {
        setSortOrder('desc');
      } else {
        setSortKey(null); // Reset
      }
    } else {
      setSortKey(key);
      setSortOrder('asc');
    }
  };

  // Move Column (Reordering)
  const moveColumn = (index: number, direction: 'left' | 'right') => {
    const newCols = [...columns];
    const targetIndex = direction === 'left' ? index - 1 : index + 1;
    if (targetIndex >= 0 && targetIndex < newCols.length) {
      // Swap
      const temp = newCols[index];
      newCols[index] = newCols[targetIndex];
      newCols[targetIndex] = temp;
      setColumns(newCols);
    }
  };

  // Resize Column
  const resizeColumn = (index: number, change: number) => {
    setColumns(prev => {
      const copy = [...prev];
      copy[index] = {
        ...copy[index],
        width: Math.max(50, copy[index].width + change)
      };
      return copy;
    });
  };

  // Toggle Visibility
  const toggleVisibility = (index: number) => {
    setColumns(prev => {
      const copy = [...prev];
      copy[index] = { ...copy[index], visible: !copy[index].visible };
      return copy;
    });
  };

  // Process sorting & filters on the subset
  const processedCars = useMemo(() => {
    let list = [...cars];

    // Sort
    if (sortKey) {
      list.sort((a, b) => {
        let valA: any = a[sortKey as keyof Car] ?? '';
        let valB: any = b[sortKey as keyof Car] ?? '';

        if (sortKey === 'branchId') {
          valA = branchMap.get(a.branchId) || '';
          valB = branchMap.get(b.branchId) || '';
        }

        if (typeof valA === 'number' && typeof valB === 'number') {
          return sortOrder === 'asc' ? valA - valB : valB - valA;
        }

        return sortOrder === 'asc' 
          ? String(valA).localeCompare(String(valB), lang === 'ar' ? 'ar' : 'en')
          : String(valB).localeCompare(String(valA), lang === 'ar' ? 'ar' : 'en');
      });
    }

    return list;
  }, [cars, sortKey, sortOrder, branchMap, lang]);

  // Grouping processor
  const groupedData = useMemo(() => {
    if (groupBy === 'none') {
      return { none: processedCars };
    }

    const groups: Record<string, Car[]> = {};
    processedCars.forEach(car => {
      let key = 'Other';
      if (groupBy === 'make') key = car.make;
      else if (groupBy === 'year') key = car.year.toString();
      else if (groupBy === 'transmission') key = car.transmission === 'automatic' ? 'Automatic' : 'Manual';
      else if (groupBy === 'status') key = car.status === 'reserved' ? 'Reserved' : 'Available';
      else if (groupBy === 'branchId') key = branchMap.get(car.branchId) || car.branchId;

      if (!groups[key]) {
        groups[key] = [];
      }
      groups[key].push(car);
    });

    return groups;
  }, [processedCars, groupBy, branchMap]);

  // Toggle group collapse
  const toggleGroup = (groupKey: string) => {
    setExpandedGroups(prev => ({
      ...prev,
      [groupKey]: !prev[groupKey]
    }));
  };

  // Export to Excel
  const handleExportExcel = async () => {
    const visibleCols = columns.filter(c => c.visible);
    
    const excelCols = visibleCols.map(c => ({
      key: c.key,
      header: getTranslation(lang, c.labelKey),
      width: c.key === 'vin' ? 22 : (c.key === 'branchId' ? 18 : 15)
    }));

    const mapCarToExportRow = (car: Car) => {
      const mappedRow: any = { ...car };
      // Resolve branchId to branch name
      if (car.branchId) {
        mappedRow.branchId = branchMap.get(car.branchId) || car.branchId;
      }
      
      // Determine status strings for Arabic/English
      let statusText = '';
      if (car.status === 'available') {
        statusText = lang === 'ar' ? 'متاحة للبيع' : 'Available';
      } else if (car.status === 'reserved') {
        statusText = lang === 'ar' ? 'محجوزة' : 'Reserved';
      } else if (car.status === 'not_for_sale') {
        statusText = lang === 'ar' ? 'غير معروضة للبيع' : 'Not for Sale';
      } else if (car.status === 'sold') {
        statusText = lang === 'ar' ? 'مباعة' : 'Sold';
      }
      mappedRow.status = statusText;
      mappedRow.statusText = statusText;

      // Map vinMatching to Arabic/English text
      if (car.vinMatching === 'mismatch') {
        mappedRow.vinMatching = lang === 'ar' ? 'غير مطابق' : 'Mismatch';
      } else {
        mappedRow.vinMatching = lang === 'ar' ? 'مطابق' : 'Matching';
      }

      // Map transmission and fuel type for nicer display in Excel
      if (car.transmission === 'automatic') {
        mappedRow.transmission = lang === 'ar' ? 'أوتوماتيك' : 'Automatic';
      } else if (car.transmission === 'manual') {
        mappedRow.transmission = lang === 'ar' ? 'عادي (يدوي)' : 'Manual';
      }

      if (car.fuelType === 'petrol') {
        mappedRow.fuelType = lang === 'ar' ? 'بنزين' : 'Petrol';
      } else if (car.fuelType === 'diesel') {
        mappedRow.fuelType = lang === 'ar' ? 'ديزل' : 'Diesel';
      } else if (car.fuelType === 'hybrid') {
        mappedRow.fuelType = lang === 'ar' ? 'هجين' : 'Hybrid';
      } else if (car.fuelType === 'electric') {
        mappedRow.fuelType = lang === 'ar' ? 'كهربائي' : 'Electric';
      }

      return mappedRow;
    };

    const dataToExport: any[] = [];

    if (groupBy !== 'none') {
      // Grouped view exporting
      Object.keys(groupedData).forEach((groupKey) => {
        const groupRows = groupedData[groupKey];
        const isCollapsed = expandedGroups[groupKey] === true;
        const totalGroupCost = groupRows.reduce((sum, r) => sum + r.price, 0);

        // Add special group header row
        dataToExport.push({
          isGroupHeader: true,
          groupText: groupKey,
          groupCount: groupRows.length,
          groupWorth: totalGroupCost
        });

        // Add group items only if the group is expanded (WYSIWYG)
        if (!isCollapsed) {
          groupRows.forEach(car => {
            dataToExport.push(mapCarToExportRow(car));
          });
        }
      });
    } else {
      // Standard list view exporting
      processedCars.forEach(car => {
        dataToExport.push(mapCarToExportRow(car));
      });
    }

    const fileDate = new Date().toISOString().split('T')[0];
    await downloadExcelBrowser(
      `Almakhzoun_Inventory_${fileDate}.xlsx`,
      excelCols,
      dataToExport,
      'status',
      'vinMatching',
      lang
    );
  };

  // Print Table (PDF Export WYSIWYG)
  const handlePrintTable = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const visibleCols = columns.filter(c => c.visible);

    // Apply header widths and titles exactly as displayed
    const headersHTML = visibleCols.map(c => `<th style="width: ${c.width}px">${getTranslation(lang, c.labelKey)}</th>`).join('');

    const buildRowHTML = (car: Car, cols: typeof columns) => {
      let rowClass = 'row-available';
      if (car.status === 'reserved') {
        rowClass = 'row-reserved';
      } else if (car.status === 'not_for_sale') {
        rowClass = 'row-not-for-sale';
      } else if (car.status === 'sold') {
        rowClass = 'row-sold';
      }

      const cells = cols.map(c => {
        let val: any = car[c.key as keyof Car];
        if (c.key === 'branchId') {
          val = branchMap.get(car.branchId) || car.branchId;
        } else if (c.key === 'status') {
          if (car.status === 'available') {
            val = lang === 'ar' ? 'متاحة للبيع' : 'Available';
          } else if (car.status === 'reserved') {
            val = lang === 'ar' ? 'محجوزة' : 'Reserved';
          } else if (car.status === 'not_for_sale') {
            val = lang === 'ar' ? 'غير معروضة للبيع' : 'Not for Sale';
          } else if (car.status === 'sold') {
            val = lang === 'ar' ? 'مباعة' : 'Sold';
          }
        } else if (c.key === 'transmission') {
          val = car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual');
        } else if (c.key === 'fuelType') {
          val = getTranslation(lang, car.fuelType as any);
        } else if (c.key === 'price') {
          val = `${car.price.toLocaleString('en-US')} ر.س`;
        } else if (c.key === 'odometer') {
          val = `${car.odometer.toLocaleString('en-US')} KM`;
        } else if (c.key === 'vinMatching') {
          val = car.vinMatching === 'mismatch' ? (lang === 'ar' ? 'غير مطابق' : 'Mismatch') : (lang === 'ar' ? 'مطابق' : 'Matching');
        }

        let cellClass = '';
        if (c.key === 'vinMatching' && car.vinMatching === 'mismatch') {
          cellClass = 'cell-mismatch';
        }

        return `<td class="${cellClass}">${val ?? ''}</td>`;
      }).join('');

      return `<tr class="${rowClass}">${cells}</tr>`;
    };

    let rowsHTML = '';

    if (groupBy !== 'none') {
      // Grouped rendering matching screen visibility
      Object.keys(groupedData).forEach((groupKey) => {
        const groupRows = groupedData[groupKey];
        const isCollapsed = expandedGroups[groupKey] === true;
        const totalGroupCost = groupRows.reduce((sum, r) => sum + r.price, 0);

        rowsHTML += `
          <tr class="group-header">
            <td colspan="${visibleCols.length}">
              ◀ ${lang === 'ar' ? 'الفئة:' : 'Category:'} ${groupKey} (${groupRows.length} ${lang === 'ar' ? 'سيارات' : 'Cars'}) - ${lang === 'ar' ? 'القيمة التقديرية للفئة:' : 'Category Worth:'} ${totalGroupCost.toLocaleString('en-US')} ر.س
            </td>
          </tr>
        `;

        if (!isCollapsed) {
          groupRows.forEach(car => {
            rowsHTML += buildRowHTML(car, visibleCols);
          });
        }
      });
    } else {
      // Standard continuous rendering
      processedCars.forEach(car => {
        rowsHTML += buildRowHTML(car, visibleCols);
      });
    }

    printWindow.document.write(`
      <html lang="${lang}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}">
      <head>
        <title>Inventory Report</title>
        <style>
          @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap');
          
          body {
            font-family: 'Cairo', 'Inter', system-ui, -apple-system, sans-serif;
            padding: 20px;
            color: #1e293b;
            background-color: #ffffff;
            direction: ${lang === 'ar' ? 'rtl' : 'ltr'};
          }
          
          h2 {
            text-align: center;
            color: #0f172a;
            margin-bottom: 5px;
            font-size: 20px;
            font-weight: 800;
          }
          
          .sub-header {
            text-align: center;
            font-size: 11px;
            color: #475569;
            margin-bottom: 20px;
            font-weight: 600;
          }
          
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
          }
          
          th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: right;
            vertical-align: middle;
          }
          
          th {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 10px;
            border-color: #334155;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          /* Group Header Rows styling */
          tr.group-header {
            background-color: #1e1b4b !important;
            color: #818cf8 !important;
            font-weight: 800;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          tr.group-header td {
            border: 1px solid #312e81;
            padding: 10px 12px;
            text-align: right;
          }
          
          /* Conditional Row Styling */
          tr.row-reserved {
            background-color: #FFD700 !important;
            color: #000000 !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          tr.row-reserved td {
            color: #000000 !important;
            border-color: #cbd5e1;
          }
          
          tr.row-not-for-sale {
            background-color: #FFC0CB !important;
            color: #000000 !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          tr.row-not-for-sale td {
            color: #000000 !important;
            border-color: #cbd5e1;
          }
          
          tr.row-sold {
            background-color: #D3D3D3 !important;
            color: #555555 !important;
            font-weight: bold;
            text-decoration: line-through; /* Strike-through as requested */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          tr.row-sold td {
            color: #555555 !important;
            text-decoration: line-through;
            border-color: #cbd5e1;
          }
          
          tr.row-available {
            background-color: #E6F2FF !important;
            color: #000000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          tr.row-available td {
            color: #000000 !important;
            border-color: #cbd5e1;
          }
          
          td.cell-mismatch {
            background-color: #990000 !important;
            color: #ffffff !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          
          .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            font-weight: 600;
          }
          
          @media print {
            body {
              padding: 0;
            }
            @page {
              margin: 1.5cm;
            }
          }
        </style>
      </head>
      <body>
        ${logo ? `<div style="text-align: center; margin-bottom: 12px;"><img src="${logo}" style="max-height: 70px; width: auto; object-fit: contain;" /></div>` : ''}
        <h2>${companyName || getTranslation(lang, 'companyName')}</h2>
        <div class="sub-header">
          ${getTranslation(lang, 'totalCars')}: ${processedCars.length} | Date: ${new Date().toLocaleDateString()}
        </div>
        <table>
          <thead>
            <tr>${headersHTML}</tr>
          </thead>
          <tbody>
            ${rowsHTML}
          </tbody>
        </table>
        <div class="footer">
          Generated automatically via Almakhzoun Pro ERP System. Confidential and Proprietary.
        </div>
        <script>window.print();</script>
      </body>
      </html>
    `);
    printWindow.document.close();
  };

  // Pagination calculation
  const totalItems = processedCars.length;
  const totalPages = Math.ceil(totalItems / pageSize);
  const paginatedRows = useMemo(() => {
    if (groupBy !== 'none') {
      return []; // Pagination bypassed for grouping to show all grouped items clearly
    }
    const start = (currentPage - 1) * pageSize;
    return processedCars.slice(start, start + pageSize);
  }, [processedCars, currentPage, pageSize, groupBy]);

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-lg p-3 text-slate-200 shadow-xl flex flex-col gap-3 h-full select-none">
      
      {/* Table Toolbar controls */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-3 bg-slate-900">
        <div className="flex items-center gap-2.5">
          {/* Grouping dropdown */}
          <div className="flex items-center gap-1">
            <span className="text-[10px] text-slate-500">{getTranslation(lang, 'grouping')}:</span>
            <select
              value={groupBy}
              onChange={e => {
                setGroupBy(e.target.value as GroupByField);
                setExpandedGroups({});
              }}
              className="text-[11px] font-bold bg-slate-950 border border-slate-800 rounded px-2 py-1 text-indigo-400 focus:outline-none focus:border-indigo-600 cursor-pointer"
            >
              <option value="none">{lang === 'ar' ? 'بدون تجميع' : 'None'}</option>
              <option value="make">{getTranslation(lang, 'make')}</option>
              <option value="year">{getTranslation(lang, 'modelYear')}</option>
              <option value="branchId">{getTranslation(lang, 'branch')}</option>
              <option value="status">{getTranslation(lang, 'reservationStatus')}</option>
              <option value="transmission">{getTranslation(lang, 'transmission')}</option>
            </select>
          </div>

          {/* Visibility toggle button */}
          <div className="relative">
            <button
              onClick={() => setShowColOptions(!showColOptions)}
              className="flex items-center gap-1 text-[11px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-2 py-1 rounded border border-slate-750 transition cursor-pointer"
            >
              <SlidersHorizontal className="w-3 h-3 text-indigo-400" />
              <span>{getTranslation(lang, 'columnVisibility')}</span>
            </button>

            {/* Custom checkboxes dropdown for Column Visibility */}
            {showColOptions && (
              <div className="absolute right-0 top-full mt-1.5 z-40 w-48 bg-slate-950 border border-slate-800 rounded-lg shadow-2xl p-2.5 space-y-1.5 max-h-60 overflow-y-auto">
                <div className="text-[10px] text-slate-500 font-extrabold pb-1 border-b border-slate-800 mb-1">
                  {lang === 'ar' ? 'تحديد الأعمدة الظاهرة' : 'Toggle Column Fields'}
                </div>
                {columns.map((col, idx) => (
                  <label key={col.key} className="flex items-center gap-2 text-[10px] font-bold text-slate-300 cursor-pointer hover:text-white">
                    <input
                      type="checkbox"
                      checked={col.visible}
                      onChange={() => toggleVisibility(idx)}
                      className="rounded text-indigo-600 border-slate-800 focus:ring-0 bg-slate-900"
                    />
                    <span>{getTranslation(lang, col.labelKey)}</span>
                  </label>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Action Export Buttons */}
        <div className="flex items-center gap-1.5">
          <button
            onClick={handleExportExcel}
            className="flex items-center gap-1 text-[11px] bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 font-extrabold px-2 py-1 rounded border border-emerald-500/20 transition cursor-pointer"
          >
            <FileSpreadsheet className="w-3.5 h-3.5" />
            <span>{getTranslation(lang, 'exportExcel')}</span>
          </button>
          
          <button
            onClick={handlePrintTable}
            className="flex items-center gap-1 text-[11px] bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 font-extrabold px-2 py-1 rounded border border-indigo-500/20 transition cursor-pointer"
          >
            <Printer className="w-3.5 h-3.5" />
            <span>{getTranslation(lang, 'printTable')}</span>
          </button>
        </div>
      </div>

      {/* Primary Table container supporting Virtual / Infinite scrolling layout */}
      <div className="flex-1 overflow-x-auto min-h-[400px] border border-slate-800 rounded-lg bg-slate-950 relative custom-scrollbar">
        <table className="w-full text-right border-collapse text-xs">
          <thead>
            <tr className="bg-slate-900/80 border-b border-slate-800 text-[10px] text-slate-400 font-extrabold sticky top-0 z-10 backdrop-blur-sm">
              
              {/* Columns Header Mapping */}
              {columns.filter(col => col.visible).map((col, idx) => (
                <th 
                  key={col.key} 
                  style={{ width: col.width }}
                  className="p-2 border-l border-slate-800 font-bold select-none text-slate-300"
                >
                  <div className="flex items-center justify-between gap-1">
                    {/* Header sorting text */}
                    <button 
                      onClick={() => handleSort(col.key)}
                      className="hover:text-indigo-400 transition flex items-center gap-1 font-extrabold text-right focus:outline-none cursor-pointer"
                    >
                      <span>{getTranslation(lang, col.labelKey)}</span>
                      <ArrowUpDown className="w-2.5 h-2.5 text-indigo-500 shrink-0" />
                    </button>

                    {/* Resize & Reorder Controls */}
                    <div className="flex items-center gap-0.5 opacity-40 hover:opacity-100 transition shrink-0">
                      {/* Left move */}
                      {idx > 0 && (
                        <button 
                          onClick={() => moveColumn(idx, 'left')} 
                          className="hover:text-white text-[8px] p-0.5 focus:outline-none cursor-pointer"
                          title="Move Left"
                        >
                          ◀
                        </button>
                      )}
                      
                      {/* Resize controllers */}
                      <button 
                        onClick={() => resizeColumn(idx, -15)} 
                        className="hover:text-red-400 font-mono text-[8px] px-0.5 focus:outline-none cursor-pointer"
                        title="Narrower"
                      >
                        -
                      </button>
                      <button 
                        onClick={() => resizeColumn(idx, 15)} 
                        className="hover:text-emerald-400 font-mono text-[8px] px-0.5 focus:outline-none cursor-pointer"
                        title="Wider"
                      >
                        +
                      </button>

                      {/* Right move */}
                      {idx < columns.filter(c => c.visible).length - 1 && (
                        <button 
                          onClick={() => moveColumn(idx, 'right')} 
                          className="hover:text-white text-[8px] p-0.5 focus:outline-none cursor-pointer"
                          title="Move Right"
                        >
                          ▶
                        </button>
                      )}
                    </div>
                  </div>
                </th>
              ))}
              
              {/* Actions sticky right heading */}
              <th className="p-2 text-center font-bold text-slate-300 w-32">
                {lang === 'ar' ? 'إجراءات تشغيلية' : 'Actions Operations'}
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-slate-800/60 font-medium">
            
            {/* 1. Grouped Mode Rendering */}
            {groupBy !== 'none' ? (
              Object.keys(groupedData).map((groupKey) => {
                const groupRows = groupedData[groupKey];
                const isCollapsed = expandedGroups[groupKey] === true;
                const totalGroupCost = groupRows.reduce((sum, r) => sum + r.price, 0);

                return (
                  <React.Fragment key={groupKey}>
                    {/* Header row for Group */}
                    <tr className="bg-indigo-950/20 hover:bg-indigo-950/35 border-b border-slate-800">
                      <td 
                        colSpan={columns.filter(c => c.visible).length + 1}
                        className="p-2 text-indigo-400 font-extrabold text-[11px]"
                      >
                        <div className="flex items-center justify-between">
                          <button
                            type="button"
                            onClick={() => toggleGroup(groupKey)}
                            className="flex items-center gap-1.5 focus:outline-none cursor-pointer text-left"
                          >
                            {isCollapsed ? <ChevronRight className="w-4 h-4 text-indigo-500 shrink-0" /> : <ChevronDown className="w-4 h-4 text-indigo-500 shrink-0" />}
                            <span>{groupKey}</span>
                            <span className="bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 px-1.5 py-0.5 rounded text-[9px] font-bold">
                              {groupRows.length} {lang === 'ar' ? 'سيارات' : 'Cars'}
                            </span>
                          </button>
                          
                          <span className="text-[10px] text-slate-500 font-normal">
                            {lang === 'ar' ? 'القيمة التقديرية للفئة: ' : 'Category Worth: '} 
                            <strong className="text-indigo-400 font-sans">{totalGroupCost.toLocaleString('en-US')} ر.س</strong>
                          </span>
                        </div>
                      </td>
                    </tr>

                    {/* Expandable rows */}
                    {!isCollapsed && groupRows.map((car) => (
                      <tr 
                        key={car.id} 
                        className="hover:bg-slate-900/50 transition-colors text-[11px]"
                      >
                        {columns.filter(c => c.visible).map((col) => {
                          let displayValue: any = car[col.key as keyof Car];
                          if (col.key === 'branchId') {
                            displayValue = branchMap.get(car.branchId) || car.branchId;
                          } else if (col.key === 'status') {
                            displayValue = car.status === 'reserved' 
                              ? <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500/10 text-rose-450 text-rose-400 border border-rose-500/10">● {getTranslation(lang, 'reserved')}</span>
                              : <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-450 text-emerald-400 border border-emerald-500/10">● {getTranslation(lang, 'available')}</span>;
                          } else if (col.key === 'transmission') {
                            displayValue = car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual');
                          } else if (col.key === 'fuelType') {
                            displayValue = getTranslation(lang, car.fuelType as any);
                          } else if (col.key === 'price') {
                            displayValue = <span className="font-sans font-bold text-indigo-300">{car.price.toLocaleString('en-US')} ر.س</span>;
                          } else if (col.key === 'odometer') {
                            displayValue = <span className="font-sans text-slate-300">{car.odometer.toLocaleString('en-US')} KM</span>;
                          }

                          return (
                            <td key={col.key} className="p-2 border-l border-slate-800 text-slate-300 max-w-xs truncate">
                              {displayValue}
                            </td>
                          );
                        })}

                        {/* Actions cell */}
                        <td className="p-1.5 text-center">
                          <div className="flex items-center justify-center gap-1">
                            {userRole === 'admin' && (
                              <button
                                onClick={() => onEdit(car)}
                                className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-800 text-slate-300 hover:bg-slate-700 cursor-pointer"
                              >
                                {lang === 'ar' ? 'تعديل' : 'Edit'}
                              </button>
                            )}
                            {car.status === 'reserved' ? (
                              <button
                                onClick={() => {
                                  const resv = reservations.find(r => r.carId === car.id);
                                  if (resv && onViewReservationDetail) {
                                    onViewReservationDetail(resv);
                                  }
                                }}
                                className="px-1.5 py-0.5 rounded text-[9px] font-bold cursor-pointer bg-amber-600/15 text-amber-400 hover:bg-amber-600 hover:text-white"
                              >
                                {lang === 'ar' ? 'التفاصيل' : 'Details'}
                              </button>
                            ) : (
                              <button
                                onClick={() => onReserve(car)}
                                disabled={car.status === 'sold'}
                                className={`px-1.5 py-0.5 rounded text-[9px] font-bold cursor-pointer ${car.status === 'sold' ? 'bg-slate-950 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700'}`}
                              >
                                {car.status === 'sold' ? (lang === 'ar' ? 'مباعة' : 'Sold') : (lang === 'ar' ? 'حجز' : 'Book')}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </React.Fragment>
                );
              })
            ) : (
              
              // 2. Paginated Standard Mode
              paginatedRows.map((car) => (
                <tr 
                  key={car.id} 
                  className="hover:bg-slate-900/50 transition-colors text-[11px]"
                >
                  {columns.filter(c => c.visible).map((col) => {
                    let displayValue: any = car[col.key as keyof Car];
                    if (col.key === 'branchId') {
                      displayValue = branchMap.get(car.branchId) || car.branchId;
                    } else if (col.key === 'status') {
                      displayValue = car.status === 'reserved' 
                        ? <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500/10 text-rose-450 text-rose-400 border border-rose-500/10">● {getTranslation(lang, 'reserved')}</span>
                        : <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-450 text-emerald-400 border border-emerald-500/10">● {getTranslation(lang, 'available')}</span>;
                    } else if (col.key === 'transmission') {
                      displayValue = car.transmission === 'automatic' ? getTranslation(lang, 'automatic') : getTranslation(lang, 'manual');
                    } else if (col.key === 'fuelType') {
                      displayValue = getTranslation(lang, car.fuelType as any);
                    } else if (col.key === 'price') {
                      displayValue = <span className="font-sans font-bold text-indigo-300">{car.price.toLocaleString('en-US')} ر.س</span>;
                    } else if (col.key === 'odometer') {
                      displayValue = <span className="font-sans text-slate-300">{car.odometer.toLocaleString('en-US')} KM</span>;
                    }

                    return (
                      <td key={col.key} className="p-2 border-l border-slate-800 text-slate-300 max-w-xs truncate">
                        {displayValue}
                      </td>
                    );
                  })}

                  {/* Actions cell */}
                  <td className="p-1.5 text-center">
                    <div className="flex items-center justify-center gap-1">
                      {userRole === 'admin' && (
                        <button
                          onClick={() => onEdit(car)}
                          className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-800 text-slate-300 hover:bg-slate-700 cursor-pointer"
                        >
                          {lang === 'ar' ? 'تعديل' : 'Edit'}
                        </button>
                      )}
                      {car.status === 'reserved' ? (
                        <button
                          onClick={() => {
                            const resv = reservations.find(r => r.carId === car.id);
                            if (resv && onViewReservationDetail) {
                              onViewReservationDetail(resv);
                            }
                          }}
                          className="px-1.5 py-0.5 rounded text-[9px] font-bold cursor-pointer bg-amber-600/15 text-amber-400 hover:bg-amber-600 hover:text-white"
                        >
                          {lang === 'ar' ? 'التفاصيل' : 'Details'}
                        </button>
                      ) : (
                        <button
                          onClick={() => onReserve(car)}
                          disabled={car.status === 'sold'}
                          className={`px-1.5 py-0.5 rounded text-[9px] font-bold cursor-pointer ${car.status === 'sold' ? 'bg-slate-950 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700'}`}
                        >
                          {car.status === 'sold' ? (lang === 'ar' ? 'مباعة' : 'Sold') : (lang === 'ar' ? 'حجز' : 'Book')}
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            )}

            {processedCars.length === 0 && (
              <tr>
                <td 
                  colSpan={columns.filter(c => c.visible).length + 1}
                  className="p-8 text-center text-slate-500 font-bold"
                >
                  {getTranslation(lang, 'noCarsFound')}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Bar - only visible when Grouping is OFF */}
      {groupBy === 'none' && totalItems > 0 && (
        <div className="flex items-center justify-between border-t border-slate-800 pt-3 bg-slate-900 text-[11px] font-bold text-slate-400">
          <div className="flex items-center gap-2">
            <span>{lang === 'ar' ? 'صفوف لكل صفحة:' : 'Rows per page:'}</span>
            <select
              value={pageSize}
              onChange={e => {
                setPageSize(parseInt(e.target.value));
                setCurrentPage(1);
              }}
              className="bg-slate-950 border border-slate-800 rounded px-1.5 py-0.5 text-slate-200 cursor-pointer focus:outline-none"
            >
              <option value={10}>10</option>
              <option value={15}>15</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
            </select>
          </div>

          <div className="flex items-center gap-1 font-mono">
            <span>{getTranslation(lang, 'pagination')} {currentPage} / {totalPages || 1}</span>
            <span className="text-slate-600">|</span>
            <span>{totalItems} {lang === 'ar' ? 'سيارات إجمالية' : 'total items'}</span>
          </div>

          <div className="flex items-center gap-1">
            <button
              onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
              disabled={currentPage === 1}
              className="px-2 py-1 rounded bg-slate-800 border border-slate-750 text-slate-300 hover:bg-slate-700 hover:text-white disabled:bg-slate-950 disabled:text-slate-600 disabled:border-slate-900 transition flex items-center justify-center cursor-pointer"
            >
              <ChevronLeft className="w-3.5 h-3.5" />
            </button>
            <button
              onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
              disabled={currentPage === totalPages}
              className="px-2 py-1 rounded bg-slate-800 border border-slate-750 text-slate-300 hover:bg-slate-700 hover:text-white disabled:bg-slate-950 disabled:text-slate-600 disabled:border-slate-900 transition flex items-center justify-center cursor-pointer"
            >
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
