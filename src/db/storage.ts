/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import * as fs from 'fs';
import * as path from 'path';
import { generateSeedData } from './mockData';
import { Car, Branch, User, Reservation, AuditLog, Notification, SystemSettings, CustomerOrder, VisitorLog, BranchTransfer, ContactInquiry } from '../types';

interface DatabaseSchema {
  branches: Branch[];
  users: User[];
  settings: SystemSettings;
  cars: Car[];
  reservations: Reservation[];
  logs: AuditLog[];
  notifications: Notification[];
  customerOrders?: CustomerOrder[];
  visitorLogs?: VisitorLog[];
  transfers?: BranchTransfer[];
  contactInquiries?: ContactInquiry[];
}

const DATA_DIR = path.join(process.cwd(), 'data');
const DB_FILE = path.join(DATA_DIR, 'db.json');

// Ensure database file is initialized
function initDB(): DatabaseSchema {
  if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
  }

  if (!fs.existsSync(DB_FILE)) {
    const seed = generateSeedData();
    fs.writeFileSync(DB_FILE, JSON.stringify(seed, null, 2), 'utf-8');
    return seed;
  }

  try {
    const raw = fs.readFileSync(DB_FILE, 'utf-8');
    const parsed = JSON.parse(raw) as DatabaseSchema;
    
    // Self-healing database cleanup on load to merge any duplicates by VIN
    if (parsed.cars && Array.isArray(parsed.cars)) {
      const uniqueCarsByVin: Car[] = [];
      const seenVins = new Set<string>();
      let cleanedCount = 0;

      for (const car of parsed.cars) {
        if (!car.vin) {
          uniqueCarsByVin.push(car);
          continue;
        }
        const normalizedVin = car.vin.trim().toUpperCase();
        if (!seenVins.has(normalizedVin)) {
          seenVins.add(normalizedVin);
          uniqueCarsByVin.push(car);
        } else {
          cleanedCount++;
        }
      }

      if (cleanedCount > 0) {
        parsed.cars = uniqueCarsByVin;
        try {
          fs.writeFileSync(DB_FILE, JSON.stringify(parsed, null, 2), 'utf-8');
        } catch (writeErr) {
          // Logging failure to save cleaned database for monitoring
          console.error('[Self-Healing DB] Failed to save cleaned database', writeErr); // skipcq: JS-0002
        }
      }
    }
    
    if (!parsed.customerOrders) {
      parsed.customerOrders = [];
    }

    if (!parsed.visitorLogs) {
      parsed.visitorLogs = [];
    }

    if (!parsed.contactInquiries) {
      parsed.contactInquiries = [];
    }
    
    return parsed;
  } catch (err) {
    // Logging database read error for monitoring
    console.error('Error reading database file. Re-initializing...', err); // skipcq: JS-0002
    const seed = generateSeedData();
    fs.writeFileSync(DB_FILE, JSON.stringify(seed, null, 2), 'utf-8');
    return seed;
  }
}

// Global cached state
let dbState: DatabaseSchema = initDB();

function saveDB() {
  try {
    fs.writeFileSync(DB_FILE, JSON.stringify(dbState, null, 2), 'utf-8');
  } catch (err) {
    console.error('Failed to save database file', err);
  }
}

export const db = {
  reloadDB: () => {
    dbState = initDB();
  },
  saveDB: () => {
    saveDB();
  },
  // Cars
  getCars: (): Car[] => dbState.cars,
  getCarById: (id: string): Car | undefined => dbState.cars.find(c => c.id === id),
  saveCar: (car: Car) => {
    const idx = dbState.cars.findIndex(c => c.id === car.id);
    if (idx >= 0) {
      dbState.cars[idx] = car;
    } else {
      dbState.cars.unshift(car); // Add to beginning (newest first)
    }
    saveDB();
  },
  deleteCar: (id: string): boolean => {
    const lenBefore = dbState.cars.length;
    dbState.cars = dbState.cars.filter(c => c.id !== id);
    // Also cleanup reservations for this car
    dbState.reservations = dbState.reservations.filter(r => r.carId !== id);
    saveDB();
    return dbState.cars.length < lenBefore;
  },

  // Reservations
  getReservations: (): Reservation[] => dbState.reservations,
  getReservationById: (id: string): Reservation | undefined => dbState.reservations.find(r => r.id === id),
  saveReservation: (res: Reservation) => {
    const idx = dbState.reservations.findIndex(r => r.id === res.id);
    if (idx >= 0) {
      dbState.reservations[idx] = res;
    } else {
      dbState.reservations.unshift(res); // Add to beginning (newest first)
    }
    // Update car status and booking representative
    const car = dbState.cars.find(c => c.id === res.carId);
    if (car) {
      if (res.sale) {
        car.status = 'sold';
        car.sale = res.sale;
      } else {
        car.status = 'reserved';
      }
      car.repInCharge = res.createdByUserName;
    }
    saveDB();
  },
  deleteReservation: (id: string): boolean => {
    const res = dbState.reservations.find(r => r.id === id);
    if (!res) return false;
    
    // Set car back to available and clear booking representative
    const car = dbState.cars.find(c => c.id === res.carId);
    if (car) {
      car.status = 'available';
      car.repInCharge = '';
    }

    dbState.reservations = dbState.reservations.filter(r => r.id !== id);
    saveDB();
    return true;
  },

  // Users
  getUsers: (): User[] => dbState.users,
  getUserById: (id: string): User | undefined => dbState.users.find(u => u.id === id),
  getUserByUsername: (username: string): User | undefined => 
    dbState.users.find(u => u.username.toLowerCase() === username.toLowerCase()),
  saveUser: (user: User) => {
    const idx = dbState.users.findIndex(u => u.id === user.id);
    if (idx >= 0) {
      dbState.users[idx] = user;
    } else {
      dbState.users.push(user);
    }
    saveDB();
  },
  deleteUser: (id: string): boolean => {
    if (id === 'u1') return false; // Prevent deleting main admin
    const lenBefore = dbState.users.length;
    dbState.users = dbState.users.filter(u => u.id !== id);
    saveDB();
    return dbState.users.length < lenBefore;
  },

  // Branches
  getBranches: (): Branch[] => dbState.branches,
  saveBranch: (branch: Branch) => {
    const idx = dbState.branches.findIndex(b => b.id === branch.id);
    if (idx >= 0) {
      dbState.branches[idx] = branch;
    } else {
      dbState.branches.push(branch);
    }
    saveDB();
  },
  deleteBranch: (id: string): boolean => {
    const lenBefore = dbState.branches.length;
    dbState.branches = dbState.branches.filter(b => b.id !== id);
    saveDB();
    return dbState.branches.length < lenBefore;
  },

  // Logs
  getLogs: (): AuditLog[] => dbState.logs,
  addLog: (userId: string, userName: string, action: string, details: string) => {
    const newLog: AuditLog = {
      id: `log-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      userId,
      userName,
      action,
      details,
      createdAt: new Date().toISOString()
    };
    dbState.logs.unshift(newLog); // Newest first
    if (dbState.logs.length > 500) {
      dbState.logs = dbState.logs.slice(0, 500); // Caps logs at 500 records
    }
    saveDB();
    return newLog;
  },
  clearOldLogs: () => {
    dbState.logs = dbState.logs.slice(0, 100);
    saveDB();
  },

  // Notifications
  getNotifications: (): Notification[] => dbState.notifications,
  addNotification: (title: string, message: string, type?: string, userName?: string, branchName?: string, carId?: string, userId?: string) => {
    const newNotif: Notification = {
      id: `nt-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      title,
      message,
      isRead: false,
      createdAt: new Date().toISOString(),
      type,
      userName,
      branchName,
      carId,
      userId,
      readBy: []
    };
    dbState.notifications.unshift(newNotif);
    if (dbState.notifications.length > 100) {
      dbState.notifications = dbState.notifications.slice(0, 100);
    }
    saveDB();
    return newNotif;
  },
  markNotificationRead: (id: string, userId?: string) => {
    const notif = dbState.notifications.find(n => n.id === id);
    if (notif) {
      if (notif.userId) {
        notif.isRead = true;
      } else if (userId) {
        if (!notif.readBy) notif.readBy = [];
        if (!notif.readBy.includes(userId)) {
          notif.readBy.push(userId);
        }
      } else {
        notif.isRead = true;
      }
      saveDB();
    }
  },
  clearNotifications: (userId?: string) => {
    if (userId) {
      // Remove personal notifications of this user
      dbState.notifications = dbState.notifications.filter(n => n.userId !== userId);
      // For public notifications, add this user to their readBy list
      dbState.notifications.forEach(n => {
        if (!n.userId) {
          if (!n.readBy) n.readBy = [];
          if (!n.readBy.includes(userId)) {
            n.readBy.push(userId);
          }
        }
      });
    } else {
      dbState.notifications = [];
    }
    saveDB();
  },

  // Settings
  getSettings: (): SystemSettings => dbState.settings,
  saveSettings: (settings: SystemSettings) => {
    dbState.settings = settings;
    saveDB();
  },

  // Database Backup / Restore Emulation
  backupDB: (): string => {
    return JSON.stringify(dbState, null, 2);
  },
  restoreDB: (jsonStr: string): boolean => {
    try {
      const parsed = JSON.parse(jsonStr);
      if (parsed.cars && parsed.users && parsed.branches && parsed.reservations) {
        dbState = parsed;
        saveDB();
        return true;
      }
      return false;
    } catch {
      return false;
    }
  },

  // Customer Orders (صندوق الطلبات)
  getCustomerOrders: (): CustomerOrder[] => {
    if (!dbState.customerOrders) {
      dbState.customerOrders = [];
    }
    return dbState.customerOrders;
  },
  saveCustomerOrder: (order: CustomerOrder) => {
    if (!dbState.customerOrders) {
      dbState.customerOrders = [];
    }
    const idx = dbState.customerOrders.findIndex(o => o.id === order.id);
    if (idx >= 0) {
      dbState.customerOrders[idx] = order;
    } else {
      dbState.customerOrders.unshift(order); // Newest first
    }
    saveDB();
  },
  deleteCustomerOrder: (id: string): boolean => {
    if (!dbState.customerOrders) {
      dbState.customerOrders = [];
      return false;
    }
    const lenBefore = dbState.customerOrders.length;
    dbState.customerOrders = dbState.customerOrders.filter(o => o.id !== id);
    saveDB();
    return dbState.customerOrders.length < lenBefore;
  },

  // Visitor Logs & Traffic Analytics
  getVisitorLogs: (): VisitorLog[] => {
    if (!dbState.visitorLogs) {
      dbState.visitorLogs = [];
    }
    return dbState.visitorLogs;
  },
  addVisitorLog: (log: Omit<VisitorLog, 'id' | 'createdAt'>) => {
    if (!dbState.visitorLogs) {
      dbState.visitorLogs = [];
    }
    const newLog: VisitorLog = {
      ...log,
      id: `vl-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
      createdAt: new Date().toISOString()
    };
    dbState.visitorLogs.unshift(newLog);
    // Limit to last 1000 logs to prevent infinite file growth
    if (dbState.visitorLogs.length > 1000) {
      dbState.visitorLogs = dbState.visitorLogs.slice(0, 1000);
    }
    saveDB();
    return newLog;
  },

  // Branch Transfers (تحويلات الفروع)
  getTransfers: (): BranchTransfer[] => {
    if (!dbState.transfers) {
      dbState.transfers = [];
    }
    return dbState.transfers;
  },
  saveTransfer: (transfer: BranchTransfer) => {
    if (!dbState.transfers) {
      dbState.transfers = [];
    }
    const idx = dbState.transfers.findIndex(t => t.id === transfer.id);
    if (idx >= 0) {
      dbState.transfers[idx] = transfer;
    } else {
      dbState.transfers.unshift(transfer);
    }
    saveDB();
  },

  // Contact Inquiries (اتصل بنا)
  getContactInquiries: (): ContactInquiry[] => {
    if (!dbState.contactInquiries) {
      dbState.contactInquiries = [];
    }
    return dbState.contactInquiries;
  },
  saveContactInquiry: (inquiry: ContactInquiry) => {
    if (!dbState.contactInquiries) {
      dbState.contactInquiries = [];
    }
    const idx = dbState.contactInquiries.findIndex(i => i.id === inquiry.id);
    if (idx >= 0) {
      dbState.contactInquiries[idx] = inquiry;
    } else {
      dbState.contactInquiries.unshift(inquiry);
    }
    saveDB();
  },
  deleteContactInquiry: (id: string): boolean => {
    if (!dbState.contactInquiries) {
      dbState.contactInquiries = [];
      return false;
    }
    const lenBefore = dbState.contactInquiries.length;
    dbState.contactInquiries = dbState.contactInquiries.filter(i => i.id !== id);
    saveDB();
    return dbState.contactInquiries.length < lenBefore;
  }
};
