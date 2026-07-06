import ExcelJS from 'exceljs';

export interface ExcelColumn {
  key: string;
  header: string;
  width?: number;
}

/**
 * Creates and formats a workbook according to specification:
 * 1. Dark blue header with white text, bold, centered, auto filter, freeze pane.
 * 2. Row background based on car status:
 *    - Reserved (محجوزة): Yellow 55% background, Black text.
 *    - Not for Sale (غير معروضة للبيع): Red 55% background, White text.
 *    - Available (متاحة): Light blue 15% background, Black text.
 * 3. VIN Matching (مطابقة الهيكل) = "غير مطابق" or "mismatch":
 *    - Cell only: Red 85% background, White text, Bold.
 *    - Do not color entire row.
 */
export function buildFormattedWorkbook(
  columns: ExcelColumn[],
  data: any[],
  statusFieldName: string = 'status',
  vinMatchingFieldName: string = 'vinMatching',
  lang: string = 'ar'
): ExcelJS.Workbook {
  const workbook = new ExcelJS.Workbook();
  const worksheet = workbook.addWorksheet('المخزون والتقارير');

  // Configure Right-to-Left (RTL) view direction for Arabic layout, freeze the first row, and enable grid lines.
  worksheet.views = [
    {
      state: 'frozen',
      ySplit: 1,
      showGridLines: true,
      rightToLeft: lang === 'ar'
    }
  ];

  // Define Columns
  worksheet.columns = columns.map(col => ({
    header: col.header,
    key: col.key,
    width: col.width || 18
  }));

  // Style Header Row
  const headerRow = worksheet.getRow(1);
  headerRow.height = 32; // Comfortable height for header titles
  headerRow.eachCell((cell) => {
    cell.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: 'FF0F172A' } // Slate-900 matching the Almakhzoun Pro corporate aesthetic
    };
    cell.font = {
      name: 'Cairo',
      size: 11,
      bold: true,
      color: { argb: 'FFFFFFFF' } // Pure White text
    };
    cell.alignment = {
      horizontal: 'center',
      vertical: 'middle',
      wrapText: true
    };
    cell.border = {
      top: { style: 'thin', color: { argb: 'FF334155' } },
      bottom: { style: 'medium', color: { argb: 'FF475569' } },
      left: { style: 'thin', color: { argb: 'FF334155' } },
      right: { style: 'thin', color: { argb: 'FF334155' } }
    };
  });

  // Enable Auto Filter
  worksheet.autoFilter = {
    from: { row: 1, column: 1 },
    to: { row: 1, column: columns.length }
  };

  // Add Data Rows
  data.forEach((item) => {
    // 1. Check if this is a group header row (WYSIWYG layout)
    if (item.isGroupHeader) {
      const groupText = item.groupText || '';
      const groupCount = item.groupCount || 0;
      const groupWorth = item.groupWorth || 0;

      const displayText = lang === 'ar'
        ? `◀ الفئة: ${groupText} (${groupCount} سيارات) - القيمة التقديرية للفئة: ${groupWorth.toLocaleString('en-US')} ر.س`
        : `◀ Category: ${groupText} (${groupCount} Cars) - Category Worth: ${groupWorth.toLocaleString('en-US')} SAR`;

      const row = worksheet.addRow([displayText]);
      row.height = 28;

      // Merge cells across the entire row
      worksheet.mergeCells(row.number, 1, row.number, columns.length);

      // Style merged cell
      const groupCell = row.getCell(1);
      groupCell.fill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF1E1B4B' } // Deep indigo matching bg-indigo-950/20 in app
      };
      groupCell.font = {
        name: 'Cairo',
        size: 11,
        bold: true,
        color: { argb: 'FF818CF8' } // Light indigo matching text-indigo-400
      };
      groupCell.alignment = {
        horizontal: lang === 'ar' ? 'right' : 'left',
        vertical: 'middle'
      };
      
      // Border around the group row
      groupCell.border = {
        top: { style: 'thin', color: { argb: 'FF312E81' } },
        bottom: { style: 'thin', color: { argb: 'FF312E81' } },
        left: { style: 'thin', color: { argb: 'FF312E81' } },
        right: { style: 'thin', color: { argb: 'FF312E81' } }
      };
      return;
    }

    // 2. Regular Data Row
    const rowData: any = {};
    columns.forEach(col => {
      rowData[col.key] = item[col.key] !== undefined ? item[col.key] : '';
    });

    const row = worksheet.addRow(rowData);
    row.height = 26; // High quality spacing for readability and printing

    const rawStatus = item[statusFieldName] || '';
    const rawVinMatching = item[vinMatchingFieldName] || '';

    const isReserved = rawStatus === 'reserved' || rawStatus === 'محجوزة' || String(item.statusText).includes('محجوزة') || String(item.statusText).toLowerCase().includes('reserved');
    const isNotForSale = rawStatus === 'hidden' || rawStatus === 'not_for_sale' || rawStatus === 'غير معروضة للبيع' || rawStatus === 'غير معروضة' || String(item.statusText).includes('غير معروضة') || String(item.statusText).toLowerCase().includes('sale');
    const isSold = rawStatus === 'sold' || rawStatus === 'مباعة' || String(item.statusText).includes('مباعة') || String(item.statusText).toLowerCase().includes('sold');

    row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
      const colKey = columns[colNumber - 1].key;
      let cellValue = cell.value !== undefined && cell.value !== null ? String(cell.value).trim() : '';

      // Clean Slate-200 borders for clean grid design
      cell.border = {
        top: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        bottom: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        left: { style: 'thin', color: { argb: 'FFCBD5E1' } },
        right: { style: 'thin', color: { argb: 'FFCBD5E1' } }
      };

      // Perfect content alignment & wrap text setup
      cell.alignment = {
        horizontal: 'center',
        vertical: 'middle',
        wrapText: true
      };

      // Formatting numbers, phone numbers, and VINs correctly
      const isPrice = colKey.toLowerCase().includes('price') || colKey.toLowerCase().includes('amount') || colKey.toLowerCase().includes('cost') || colKey.toLowerCase().includes('worth');
      const isVinOrPhoneOrID = colKey === 'vin' || colKey === 'vinNumber' || colKey.toLowerCase().includes('phone') || colKey.toLowerCase().includes('mobile') || colKey.toLowerCase().includes('tel') || colKey.toLowerCase().includes('id') || colKey.toLowerCase().includes('heikal') || colKey.toLowerCase().includes('plate') || colKey.toLowerCase().includes('serial');
      const isDate = (colKey.toLowerCase().includes('date') || colKey.toLowerCase().includes('time') || colKey === 'createdAt') && colKey !== 'year';

      if (isPrice) {
        const numVal = parseFloat(cellValue.replace(/[^0-9.]/g, ''));
        if (!isNaN(numVal)) {
          cell.value = numVal;
          cell.numFmt = '#,##0'; // Elegant currency with thousands separators
        }
      } else if (isVinOrPhoneOrID) {
        cell.value = cellValue;
        cell.numFmt = '@'; // Force text format explicitly to prevent scientific notation and dropping leading zeros
      } else if (isDate && cellValue) {
        const dateVal = new Date(cellValue);
        if (!isNaN(dateVal.getTime())) {
          cell.value = dateVal.toISOString().split('T')[0];
          cell.numFmt = 'yyyy-mm-dd'; // Standard localized date style
        }
      }

      // Font and Fill based on conditional formatting
      if (isReserved) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFFFD700' } // Amber Yellow
        };
        cell.font = {
          name: 'Cairo',
          size: 10,
          bold: true,
          color: { argb: 'FF1E293B' } // Slate text
        };
      } else if (isNotForSale) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFFFC0CB' } // Rose Pink
        };
        cell.font = {
          name: 'Cairo',
          size: 10,
          bold: true,
          color: { argb: 'FF1E293B' }
        };
      } else if (isSold) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFE2E8F0' } // Disabled gray
        };
        cell.font = {
          name: 'Cairo',
          size: 10,
          bold: true,
          strike: true, // Strike-through text
          color: { argb: 'FF64748B' }
        };
      } else {
        // Normal available rows - light elegant ice-blue tint for premium presentation
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFF0F7FF' }
        };
        cell.font = {
          name: 'Cairo',
          size: 10,
          color: { argb: 'FF1E293B' }
        };
      }

      // Special highlight for VIN mismatch
      const isVinMatchCol = colKey === 'vinMatching' || colKey === 'vin_matching';
      const isMismatchVal = rawVinMatching === 'mismatch' || rawVinMatching === 'غير مطابق' || cellValue === 'غير مطابق' || cellValue === 'mismatch' || cellValue.includes('غير مطابق');

      if (isVinMatchCol && isMismatchVal) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFEF4444' } // High warning crimson red
        };
        cell.font = {
          name: 'Cairo',
          size: 10,
          bold: true,
          color: { argb: 'FFFFFFFF' }
        };
      }
    });
  });

  // Automatically calculate responsive auto-column width according to cell string lengths
  worksheet.columns.forEach((column) => {
    let maxLen = 0;
    column.eachCell!({ includeEmpty: true }, (cell) => {
      // Avoid measuring group header cells, which are merged across columns and cause extreme widths
      const row = cell.row;
      if (row && (row as any).values && (row as any).values[1] && typeof (row as any).values[1] === 'string' && (row as any).values[1].includes('◀')) {
        return;
      }
      const val = cell.value;
      if (val !== undefined && val !== null) {
        const strVal = String(val);
        if (strVal.length > maxLen) {
          maxLen = strVal.length;
        }
      }
    });
    // Set column width with custom padding padding
    column.width = Math.max(maxLen + 4, 15);
  });

  return workbook;
}

/**
 * Downloads the formatted Excel workbook in the browser environment
 */
export async function downloadExcelBrowser(
  fileName: string,
  columns: ExcelColumn[],
  data: any[],
  statusFieldName: string = 'status',
  vinMatchingFieldName: string = 'vinMatching',
  lang: string = 'ar'
) {
  const workbook = buildFormattedWorkbook(columns, data, statusFieldName, vinMatchingFieldName, lang);
  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', fileName.endsWith('.xlsx') ? fileName : `${fileName}.xlsx`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
