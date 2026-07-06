/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

export type UserRole = 'admin' | 'representative';

export interface User {
  id: string;
  username: string;
  name: string;
  role: UserRole;
  branchId: string;
  createdAt: string;
  email?: string;
  phone?: string;
  avatar?: string;
}

export interface Branch {
  id: string;
  name: string;
  location: string;
}

export interface AttachmentVersion {
  id: string;
  version: number;
  url: string;
  size: string;
  createdAt: string;
}

export interface Attachment {
  id: string;
  name: string;
  type: string; // 'image' | 'pdf' | 'doc' | 'excel'
  url: string;  // Base64 or local uploads path
  size: string;
  category?: string; // e.g. 'exterior_image' | 'interior_image' | 'vin_image' | 'istimara_image' | 'id_image' | 'contract' | 'invoice' | 'other'
  createdAt: string;
  version: number;
  versions?: AttachmentVersion[]; // Version history list
}

export interface TechnicalSpecs {
  gulfSpecs: boolean;
  americanSpecs: boolean;
  europeanSpecs: boolean;
  fuelConsumption: string;
  navigationSystem: boolean;
  rearCamera: boolean;
  camera360: boolean;
  radar: boolean;
  frontSensors: boolean;
  rearSensors: boolean;
  cruiseControl: boolean;
  adaptiveCruise: boolean;
  laneAssist: boolean;
  blindSpot: boolean;
  appleCarPlay: boolean;
  androidAuto: boolean;
  sunroof: boolean;
  panorama: boolean;
  leatherSeats: boolean;
  heatedSeats: boolean;
  cooledSeats: boolean;
  seatMemory: boolean;
  pushButtonStart: boolean;
  remoteStart: boolean;
  ledLights: boolean;
  xenonLights: boolean;
  numberOfKeys: number;
  spareTire: boolean;
  catalog: boolean;
}

export interface SaleDetails {
  sellerName: string;
  buyerName: string;
  paymentMethod?: string;
  contractNumber?: string;
  invoiceNumber?: string;
  paymentStatus?: string; // 'paid' | 'pending' | 'partially_paid'
  paidAmount?: number;
  remainingAmount?: number;
  deliveryMethod?: string;
  deliveryDate?: string;
  deliveryNotes?: string;
}

export interface Car {
  id: string;
  make: string;
  model: string;
  trim: string; // Class / Category
  year: number; // Model Year
  color: string; // Exterior Color
  interiorColor: string;
  bodyType: string;
  doors: number;
  seats: number;
  fuelType: 'petrol' | 'diesel' | 'hybrid' | 'electric';
  transmission: 'manual' | 'automatic';
  engineCapacity: number; // CC
  cylinders: number;
  enginePower: number; // HP
  drive: string; // FWD / RWD / AWD
  odometer: number; // KM mileage
  vin: string;
  plateNumber: string;
  plateType: string;
  serialNumber: string;
  registrationNumber: string; // Istimara
  customsNumber: string;
  originCountry: string;
  assemblyCountry: string;
  vehicleCondition: string; // New / Used
  ownershipType: string;
  branchId: string;
  supplier: string;
  previousOwner: string;
  costPrice: number;
  price: number; // Cash Selling Price
  tax: number;
  discount: number;
  finalPrice: number;
  currency: string;
  entryDate: string;
  exitDate: string;
  purchaseDate: string;
  saleDate: string;
  warranty: string;
  warrantyDuration: number;
  notes: string;
  
  // Statuses
  status: 'available' | 'reserved' | 'sold' | 'not_for_sale' | 'out_of_stock';
  vinMatching?: 'matching' | 'mismatch' | string;
  leasingStatus?: 'leased' | 'not_leased' | string;
  repInCharge?: string;
  mainImage: string; // Main display image
  attachments: Attachment[];
  
  // Official Customs Document details
  cardFilePath?: string;
  cardFileName?: string;
  cardFileType?: string;
  cardFileDate?: string;
  
  // Specifications block
  specs: TechnicalSpecs;
  
  // Sale details block if sold
  sale?: SaleDetails;

  // DB Metadata & Traceability Audit Trait (Prisma Soft Delete standards)
  createdBy?: string;
  updatedBy?: string;
  deletedBy?: string;
  createdAt: string;
  updatedAt?: string;
  deletedAt?: string;
  isDeleted?: boolean; // soft delete flag
}

export interface Reservation {
  id: string;
  carId: string;
  customerName: string;
  customerPhone: string;
  nationalId: string;
  nationality: string;
  whatsApp: string;
  email: string;
  customerAddress: string;
  repInCharge: string;
  duration: number; // in days
  reason: string;
  notes?: string;
  reservationDate: string;
  reservationEndDate: string;
  reservationStatus: 'active' | 'completed' | 'cancelled';
  createdByUserId: string;
  createdByUserName: string;
  createdAt: string;
  sale?: SaleDetails;
}

export interface AuditLog {
  id: string;
  userId: string;
  userName: string;
  action: string;
  details: string;
  createdAt: string;
}

export interface Notification {
  id: string;
  title: string;
  message: string;
  isRead: boolean;
  createdAt: string;
  type?: string;
  userName?: string;
  branchName?: string;
  carId?: string;
  userId?: string;
  readBy?: string[];
}

export interface SEOSetting {
  title: string;
  description: string;
}

export interface SEOSettingsMap {
  [key: string]: SEOSetting;
}

export interface VisitorLog {
  id: string;
  ip: string;
  userAgent: string;
  browser: string;
  os: string;
  device: 'desktop' | 'mobile' | 'tablet';
  path: string;
  referrer: string;
  action?: string;
  country?: string;
  createdAt: string;
}

export interface SystemSettings {
  companyName: string;
  phone: string;
  email: string;
  currency: string;
  address: string;
  systemStatus: 'active' | 'maintenance';
  logo?: string;
  
  // Landing Page Settings
  companyDescription?: string;
  vision?: string;
  mission?: string;
  goals?: string;
  website?: string;
  socialTwitter?: string;
  socialFacebook?: string;
  socialInstagram?: string;
  socialLinkedin?: string;

  // New Theme Accent & SEO settings
  themeAccent?: string; // hex or color keyword (e.g., '#4f46e5' or 'transparent')
  themeOpacity?: number; // 0 to 100
  seo?: SEOSettingsMap;

  // Banner Welcoming Background Styling Controls
  bannerBgImage?: string;       // Custom banner background image URL/Base64
  bannerBgHeight?: string;      // Height, e.g. "450px" or "500px"
  bannerBgWidth?: string;       // Width, e.g. "100%" or "max-w-7xl"
  bannerTitleColor?: string;    // Title color, e.g. "#ffffff"
  bannerSubtitleColor?: string; // Subtitle color, e.g. "#94a3b8"
  bannerTextBgEnable?: boolean; // Enable or disable text background container
  bannerTextBgOpacity?: number; // Text background opacity percentage (e.g., 0-100)
}

export interface DashboardStats {
  totalCars: number;
  availableCars: number;
  reservedCars: number;
  totalUsers: number;
  totalReservations: number;
  totalBranches: number;
  revenueEst: number;
}

export interface CustomerOrder {
  id: string;
  carId: string;
  customerName: string;
  customerPhone: string;
  notes?: string;
  status: 'new' | 'in_progress' | 'completed' | 'cancelled';
  createdAt: string;
}

export interface BranchTransfer {
  id: string;
  carId: string;
  fromBranchId: string;
  toBranchId: string;
  transferDate: string;
  notes?: string;
  letterNumber?: string;
  createdByUserId?: string;
  createdByUserName?: string;
}

export interface ContactInquiry {
  id: string;
  name: string;
  email: string;
  phone: string;
  subject: string;
  message: string;
  status: 'new' | 'read' | 'replied' | 'completed';
  createdAt: string;
}

