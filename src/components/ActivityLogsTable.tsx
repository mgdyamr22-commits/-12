/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState } from 'react';
import { History, Search, Filter } from 'lucide-react';
import { AuditLog } from '../types';

interface ActivityLogsTableProps {
  logs: AuditLog[];
}

export default function ActivityLogsTable({ logs }: ActivityLogsTableProps) {
  const [searchQuery, setSearchQuery] = useState('');

  const filteredLogs = logs.filter(log => 
    log.userName.toLowerCase().includes(searchQuery.toLowerCase()) ||
    log.action.toLowerCase().includes(searchQuery.toLowerCase()) ||
    log.details.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-6">
      
      {/* Header Panel */}
      <div className="bg-slate-900 p-3.5 rounded-lg border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h3 className="font-bold text-sm text-white flex items-center gap-1.5">
            <History className="w-4 h-4 text-indigo-400" />
            <span>سجل العمليات والرقابة الأمنية (Audit Trail)</span>
          </h3>
          <p className="text-[10px] text-slate-500 mt-0.5">تتبع العمليات التي ينفذها المدراء والمناديب في النظام بشكل دقيق وتلقائي</p>
        </div>

        {/* Search filter input */}
        <div className="relative w-full md:w-80">
          <input
            type="text"
            placeholder="بحث في سجل العمليات..."
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
            className="w-full text-xs pr-9 pl-3.5 py-2 rounded border border-slate-800 bg-slate-950 text-slate-250 focus:outline-none focus:border-indigo-500"
          />
          <Search className="w-3.5 h-3.5 text-slate-500 absolute top-2.5 right-3" />
        </div>
      </div>

      {/* Audit Logs List Table */}
      <div className="bg-slate-900 rounded border border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-right text-xs">
            <thead className="bg-slate-950 text-slate-400 font-bold border-b border-slate-800">
              <tr>
                <th className="p-3 w-24 font-mono">كود العملية</th>
                <th className="p-3 w-44">الموظف المسؤول</th>
                <th className="p-3 w-40">العملية الإجرائية</th>
                <th className="p-3">تفاصيل الحدث الكاملة</th>
                <th className="p-3 w-44">طابع التوقيت</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800 text-slate-350">
              {filteredLogs.map((log) => (
                <tr key={log.id} className="hover:bg-slate-800/10 transition-colors">
                  <td className="p-3 font-mono text-[10px] text-slate-500 font-bold">#{log.id.split('-')[1] || log.id.slice(0, 5)}</td>
                  <td className="p-3">
                    <span className="font-bold text-white block">{log.userName}</span>
                    <span className="text-[9px] text-slate-500 font-mono">UID: {log.userId}</span>
                  </td>
                  <td className="p-3">
                    <span className="px-2 py-0.5 rounded text-[10px] bg-slate-950 text-indigo-400 font-bold border border-slate-800">
                      {log.action}
                    </span>
                  </td>
                  <td className="p-3 font-medium leading-relaxed text-slate-300">{log.details}</td>
                  <td className="p-3 font-mono text-[10px] text-slate-500">
                    {new Date(log.createdAt).toLocaleString('ar-SA')}
                  </td>
                </tr>
              ))}
              {filteredLogs.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-8 text-slate-500 font-bold text-xs">
                    لم يتم العثور على أي عمليات مطابقة لبحثك في الأرشيف.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}
