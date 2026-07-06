/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Car, Branch, User, Reservation, AuditLog, Notification, SystemSettings } from '../types';

export const mockBranches: Branch[] = [
  { id: 'b1', name: 'فرع الرياض الرئيسي', location: 'الرياض، الطريق الدائري الشمالي' },
  { id: 'b2', name: 'فرع جدة - طريق الملك', location: 'جدة، حي الخالدية' },
  { id: 'b3', name: 'فرع الدمام - حي الشاطئ', location: 'الدمام، طريق الخليج' }
];

export const mockUsers: User[] = [
  { id: 'u1', username: 'admin', name: 'أحمد القحطاني', role: 'admin', branchId: 'b1', createdAt: '2026-01-01T10:00:00Z', email: 'ahmed@elite-cars.com', phone: '0551234567' },
  { id: 'u2', username: 'agent', name: 'خالد العتيبي', role: 'representative', branchId: 'b1', createdAt: '2026-01-05T12:00:00Z', email: 'khaled@elite-cars.com', phone: '0552234567' },
  { id: 'u3', username: 'agent2', name: 'سارة الدوسري', role: 'representative', branchId: 'b2', createdAt: '2026-02-10T11:30:00Z', email: 'sara@elite-cars.com', phone: '0553234567' }
];

export const defaultSettings: SystemSettings = {
  companyName: 'مؤسسة النخبة لإدارة واستيراد السيارات',
  phone: '+966 50 123 4567',
  email: 'info@elite-cars.com',
  currency: 'ر.س',
  address: 'المملكة العربية السعودية، الرياض، حي الياسمين',
  systemStatus: 'active',
  logo: '',
  companyDescription: 'مؤسسة النخبة هي شركة رائدة في مجال استيراد وتجارة السيارات الفاخرة والحديثة في المملكة العربية السعودية، نسعى لتقديم أفضل الخيارات وبجودة عالية.',
  vision: 'أن نكون الخيار الأول والوجهة الموثوقة لعملاء السيارات الفاخرة في السوق الخليجي من خلال التميز والابتكار.',
  mission: 'تقديم تجربة شراء فريدة لعملائنا من خلال توفير تشكيلة واسعة من السيارات المتميزة مع تقديم أرقى مستويات الخدمة والدعم.',
  goals: 'توسيع شبكة فروعنا لتشمل كافة مناطق المملكة، عقد شراكات استراتيجية مع كبار المصنعين، وتقديم خدمات ما بعد البيع استثنائية.',
  website: 'www.elite-cars.com',
  socialTwitter: 'https://twitter.com/elite_cars',
  socialFacebook: 'https://facebook.com/elite_cars',
  socialInstagram: 'https://instagram.com/elite_cars',
  socialLinkedin: 'https://linkedin.com/company/elite_cars',
  
  // Theme styling defaults
  themeAccent: '#4f46e5',
  themeOpacity: 80,

  // Banner Welcoming Background Styling Controls Defaults
  bannerBgImage: 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&q=80&w=1200',
  bannerBgHeight: '520px',
  bannerBgWidth: '100%',
  bannerTitleColor: '#ffffff',
  bannerSubtitleColor: '#e2e8f0',
  bannerTextBgEnable: true,
  bannerTextBgOpacity: 65,

  // Default SEO settings
  seo: {
    landing: { title: 'الرئيسية | مؤسسة النخبة للسيارات', description: 'الصفحة التعريفية الرسمية لمؤسسة النخبة لاستيراد وتجارة أحدث ومختلف فئات السيارات الفاخرة.' },
    dashboard: { title: 'لوحة القيادة والمؤشرات | النخبة برو', description: 'نظام مراقبة الأداء اللوجستي، المبيعات الإجمالية، إحصائيات المعرض، وتحليل سعة الفروع.' },
    inventory: { title: 'إدارة مخزن وصالة السيارات | النخبة برو', description: 'البوابة الجمركية الموحدة للبحث وعرض وتعديل وإيداع السيارات والبطاقات الجمركية.' },
    sales: { title: 'عقود المبيعات والأرشيف الجنائي | النخبة برو', description: 'منصة تسوية المبيعات والعقود الإلكترونية المحمية ببروتوكولات الأمان الفدرالية.' },
    users: { title: 'حوكمة صلاحيات الموظفين | النخبة برو', description: 'لوحة إدارة شؤون مناديب المبيعات والمديرين، وتوزيع الصلاحيات الإدارية والجغرافية.' },
    branches: { title: 'إدارة الفروع والمعارض | النخبة برو', description: 'إضافة ومراقبة وتحديث معارض الشركة الجغرافية ومستويات الطاقة الاستيعابية.' },
    settings: { title: 'تهيئة إعدادات النظام المتقدمة | النخبة برو', description: 'التحكم بالهوية التجارية للشركة، شعار المطبوعات، النسخ الاحتياطي، وحركات الزوار.' },
    'customer-orders': { title: 'صندوق طلبات العملاء المباشرة | النخبة برو', description: 'استعراض والرد على طلبات الشراء الواردة مباشرة من الزوار وصالة العرض الحية.' },
    logs: { title: 'سجل عمليات وتدقيق النظام | النخبة برو', description: 'لوحة التحقق من المعاملات التاريخية وعمليات الموظفين، مدعمة ببصمة التحقق والتشفير.' }
  }
};

const carImages = {
  toyota: 'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?auto=format&fit=crop&q=80&w=600',
  hyundai: 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?auto=format&fit=crop&q=80&w=600',
  nissan: 'https://images.unsplash.com/photo-1590362891991-f776e747a588?auto=format&fit=crop&q=80&w=600',
  ford: 'https://images.unsplash.com/photo-1551816258-f1f5e97c1b4f?auto=format&fit=crop&q=80&w=600',
  lexus: 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600',
  mercedes: 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?auto=format&fit=crop&q=80&w=600',
  bmw: 'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&q=80&w=600',
  audi: 'https://images.unsplash.com/photo-1603584173870-7f23fdae1b7a?auto=format&fit=crop&q=80&w=600',
  chevrolet: 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&q=80&w=600',
  kia: 'https://images.unsplash.com/photo-1632245889029-e406faaa34cd?auto=format&fit=crop&q=80&w=600'
};

const makes = [
  { name: 'تويوتا', models: ['كامري', 'لاند كروزر', 'أفالون', 'راف فور', 'كورولا'], img: carImages.toyota, origin: 'اليابان', assembly: 'اليابان' },
  { name: 'هيونداي', models: ['سوناتا', 'إلنترا', 'توسان', 'أزيرا', 'سانتا في'], img: carImages.hyundai, origin: 'كوريا الجنوبية', assembly: 'كوريا الجنوبية' },
  { name: 'نيسان', models: ['باترول', 'ألتيما', 'ماكسيما', 'اكس تريل', 'صني'], img: carImages.nissan, origin: 'اليابان', assembly: 'اليابان' },
  { name: 'فورد', models: ['تورس', 'اكسبلورر', 'موستانج', 'إيدج', 'اف-150'], img: carImages.ford, origin: 'أمريكا', assembly: 'أمريكا' },
  { name: 'لكزس', models: ['ES350', 'LX600', 'RX350', 'LS500', 'IS300'], img: carImages.lexus, origin: 'اليابان', assembly: 'اليابان' },
  { name: 'مرسيدس', models: ['E300', 'S500', 'C200', 'G63', 'GLE450'], img: carImages.mercedes, origin: 'ألمانيا', assembly: 'ألمانيا' },
  { name: 'بي ام دبليو', models: ['520i', '740li', 'X5', '320i', 'M4'], img: carImages.bmw, origin: 'ألمانيا', assembly: 'ألمانيا' },
  { name: 'أودي', models: ['A6', 'A8', 'Q7', 'A4', 'Q5'], img: carImages.audi, origin: 'ألمانيا', assembly: 'ألمانيا' },
  { name: 'شيفروليه', models: ['تاهو', 'ماليبو', 'ترافرس', 'سيلفرادو', 'كامارو'], img: carImages.chevrolet, origin: 'أمريكا', assembly: 'أمريكا' },
  { name: 'كيا', models: ['كادينزا', 'سبورتج', 'سورينتو', 'سيراتو', 'K5'], img: carImages.kia, origin: 'كوريا الجنوبية', assembly: 'كوريا الجنوبية' }
];

const colors = ['أبيض لؤلؤي', 'أسود ملكي', 'فضي معدني', 'رمادي إسمنتي', 'أزرق كحلي', 'أحمر ناري'];
const interiorColors = ['بيج هلوز', 'جلد أسود مخرز', 'بني ملكي', 'جملي فاخر', 'أحمر سبورت'];
const bodyTypes = ['سيدان', 'SUV عائلية', 'كوبيه رياضية', 'بيك أب'];
const suppliers = ['عبد اللطيف جميل للسيارات', 'الوعلان للتجارة', 'شركة محمد يوسف ناغي', 'توكيلات الجزيرة للسيارات', 'الجميح للسيارات'];

export function generateSeedData() {
  const cars: Car[] = [];
  let idCounter = 1;

  for (let m = 0; m < makes.length; m++) {
    const makeData = makes[m];
    for (let mod = 0; mod < makeData.models.length; mod++) {
      const modelName = makeData.models[mod];
      const carId = `car-${idCounter}`;
      
      const year = 2020 + (idCounter % 7); // Years between 2020 and 2026
      const color = colors[idCounter % colors.length];
      const intColor = interiorColors[idCounter % interiorColors.length];
      const bodyType = bodyTypes[idCounter % bodyTypes.length];
      const transmission = idCounter % 6 === 0 ? 'manual' : 'automatic';
      const fuelType = idCounter % 11 === 0 ? 'electric' : idCounter % 8 === 0 ? 'hybrid' : idCounter % 5 === 0 ? 'diesel' : 'petrol';
      
      let price = 65000;
      if (makeData.name === 'مرسيدس' || makeData.name === 'بي ام دبليو' || makeData.name === 'لكزس') {
        price = 180000 + (idCounter * 4500);
      } else if (makeData.name === 'أودي') {
        price = 140000 + (idCounter * 3000);
      } else {
        price = 75000 + (idCounter * 1200);
      }

      const costPrice = Math.round(price * 0.85);
      const tax = Math.round(price * 0.15);
      const discount = idCounter % 7 === 0 ? 5000 : 0;
      const finalPrice = price + tax - discount;

      const branchIndex = idCounter % mockBranches.length;
      const branchId = mockBranches[branchIndex].id;
      
      const vin = `9AHNK48EXLR${100000 + idCounter}`;
      const plateChar = ['أ', 'ب', 'ح', 'د', 'ر', 'س', 'ط', 'ع', 'ق', 'م', 'ن', 'هـ', 'و', 'ي'];
      const plateChar1 = plateChar[idCounter % plateChar.length];
      const plateChar2 = plateChar[(idCounter + 2) % plateChar.length];
      const plateChar3 = plateChar[(idCounter + 5) % plateChar.length];
      const plateNum = `${1000 + (idCounter * 17)} ${plateChar1} ${plateChar2} ${plateChar3}`;

      // Set 20 cars as reserved initially
      const isReserved = idCounter <= 20;

      cars.push({
        id: carId,
        make: makeData.name,
        model: modelName,
        trim: idCounter % 3 === 0 ? 'فل كامل (Full Option)' : idCounter % 3 === 1 ? 'نصف فل (Mid Option)' : 'ستاندرد (Standard)',
        year,
        color,
        interiorColor: intColor,
        bodyType,
        doors: bodyType === 'كوبيه رياضية' ? 2 : 4,
        seats: bodyType === 'SUV عائلية' ? 7 : 5,
        fuelType,
        transmission,
        engineCapacity: year >= 2024 ? 2000 : 2500,
        cylinders: makeData.name === 'مرسيدس' || makeData.name === 'بي ام دبليو' ? 6 : 4,
        enginePower: makeData.name === 'مرسيدس' ? 320 : 188,
        drive: bodyType === 'SUV عائلية' || bodyType === 'بيك أب' ? 'دفع رباعي 4WD' : 'دفع أمامي FWD',
        odometer: idCounter % 4 === 0 ? 0 : 2500 * idCounter,
        vin,
        plateNumber: plateNum,
        plateType: 'خصوصي - ملاكي',
        serialNumber: `SN-${55000000 + idCounter}`,
        registrationNumber: `REG-${880000 + idCounter}`,
        customsNumber: `CUST-${990000 + idCounter}`,
        originCountry: makeData.origin,
        assemblyCountry: makeData.assembly,
        vehicleCondition: idCounter % 4 === 0 ? 'جديد (أصفار)' : 'مستعمل مميز (مضمون)',
        ownershipType: 'بطاقة جمركية للاستيراد',
        branchId,
        supplier: suppliers[idCounter % suppliers.length],
        previousOwner: idCounter % 4 === 0 ? 'مستورد جديد' : 'شركة عبد الله الراجحي المحدودة',
        costPrice,
        price,
        tax,
        discount,
        finalPrice,
        currency: 'ر.س',
        entryDate: new Date(Date.now() - 1000 * 60 * 60 * 24 * (60 - idCounter)).toISOString(),
        exitDate: isReserved ? new Date(Date.now() + 1000 * 60 * 60 * 24 * 5).toISOString() : '',
        purchaseDate: new Date(Date.now() - 1000 * 60 * 60 * 24 * (90 - idCounter)).toISOString(),
        saleDate: isReserved ? new Date().toISOString() : '',
        warranty: 'ضمان الوكيل المعتمد الممتد',
        warrantyDuration: 5,
        notes: 'السيارة بحالة ممتازة وخالية من أي عيوب أو رش تجميلي، فحص الـ 100 نقطة سليم.',
        status: isReserved ? 'reserved' : 'available',
        mainImage: makeData.img,
        attachments: [
          {
            id: `att-${carId}-1`,
            name: 'شهادة البطاقة الجمركية.pdf',
            type: 'pdf',
            category: 'customs_document',
            url: 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            size: '245 KB',
            createdAt: new Date(Date.now() - 1000 * 60 * 60 * 24 * 10).toISOString(),
            version: 1,
            versions: []
          },
          {
            id: `att-${carId}-2`,
            name: 'تقرير الفحص الفني الدوري.pdf',
            type: 'pdf',
            category: 'inspection_report',
            url: 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            size: '512 KB',
            createdAt: new Date(Date.now() - 1000 * 60 * 60 * 24 * 10).toISOString(),
            version: 1,
            versions: []
          }
        ],
        specs: {
          gulfSpecs: true,
          americanSpecs: false,
          europeanSpecs: false,
          fuelConsumption: '14.8 كم/لتر',
          navigationSystem: idCounter % 2 === 0,
          rearCamera: true,
          camera360: idCounter % 3 === 0,
          radar: idCounter % 3 === 0,
          frontSensors: true,
          rearSensors: true,
          cruiseControl: true,
          adaptiveCruise: idCounter % 3 === 0,
          laneAssist: idCounter % 4 === 0,
          blindSpot: idCounter % 3 === 0,
          appleCarPlay: true,
          androidAuto: true,
          sunroof: idCounter % 2 === 0,
          panorama: idCounter % 3 === 0,
          leatherSeats: idCounter % 2 === 0,
          heatedSeats: idCounter % 3 === 0,
          cooledSeats: idCounter % 3 === 0,
          seatMemory: idCounter % 3 === 0,
          pushButtonStart: true,
          remoteStart: idCounter % 2 === 0,
          ledLights: true,
          xenonLights: false,
          numberOfKeys: 2,
          spareTire: true,
          catalog: true
        },
        createdBy: 'u1',
        createdAt: new Date(Date.now() - 1000 * 60 * 60 * 24 * (50 - idCounter)).toISOString(),
        isDeleted: false
      });

      idCounter++;
    }
  }

  const reservations: Reservation[] = [];
  const customers = [
    { name: 'محمد عبد الله العتيبي', phone: '0551122334', idCard: '1022334455', nat: 'سعودي' },
    { name: 'سلطان سليمان المطيري', phone: '0562233445', idCard: '1033445566', nat: 'سعودي' },
    { name: 'عبد الرحمن صالح الحربي', phone: '0543344556', idCard: '1044556677', nat: 'سعودي' },
    { name: 'فيصل محمد الشهراني', phone: '0504455667', idCard: '1055667788', nat: 'سعودي' },
    { name: 'سليمان خالد العيسى', phone: '0555566778', idCard: '1066778899', nat: 'سعودي' },
    { name: 'ماجد عبد العزيز السديري', phone: '0536677889', idCard: '1077889900', nat: 'سعودي' },
    { name: 'تركي ناصر الرويلي', phone: '0547788990', idCard: '1088990011', nat: 'سعودي' },
    { name: 'يزيد عبد المجيد السبيعي', phone: '0558899001', idCard: '1099001122', nat: 'سعودي' },
    { name: 'سلمان فهد الزهراني', phone: '0569900112', idCard: '1100112233', nat: 'سعودي' },
    { name: 'نواف عبيد الشمري', phone: '0501122445', idCard: '2011223344', nat: 'كويتي' },
    { name: 'مشعل حمد البقمي', phone: '0542233556', idCard: '1122334411', nat: 'سعودي' },
    { name: 'عبد الله صالح العريفي', phone: '0553344667', idCard: '1133445522', nat: 'سعودي' },
    { name: 'بندر فهد العويد', phone: '0564455778', idCard: '1144556633', nat: 'سعودي' },
    { name: 'بدر رائد الدوسري', phone: '0535566889', idCard: '1155667744', nat: 'سعودي' },
    { name: 'طلال فيصل السيف', phone: '0506677990', idCard: '1166778855', nat: 'سعودي' },
    { name: 'صالح محمد آل الشيخ', phone: '0547788001', idCard: '1177889966', nat: 'سعودي' },
    { name: 'حميد صالح السعدون', phone: '0558899112', idCard: '2188990011', nat: 'بحريني' },
    { name: 'خالد وليد الفايز', phone: '0569900223', idCard: '1199001188', nat: 'سعودي' },
    { name: 'سعود فهد بن سعيد', phone: '0501122335', idCard: '1200112299', nat: 'سعودي' },
    { name: 'عبد العزيز حميد القحطاني', phone: '0532233446', idCard: '1211223310', nat: 'سعودي' }
  ];

  const reasons = [
    'طلب شراء شخصي كاش',
    'طلب شراء عن طريق بنك الراجحي تمويل تأجيري',
    'طلب شراء مؤسسة تجارية',
    'حجز مؤقت لحين استكمال الأوراق والمستندات',
    'تمويل عن طريق البنك الأهلي السعودي'
  ];

  for (let i = 0; i < 20; i++) {
    const carId = `car-${i + 1}`;
    const customer = customers[i];
    const reason = reasons[i % reasons.length];
    const duration = 3 + (i % 5); // 3 to 7 days
    const agent = mockUsers[1 + (i % 2)]; // خالد العتيبي or سارة الدوسري

    reservations.push({
      id: `res-${i + 1}`,
      carId,
      customerName: customer.name,
      customerPhone: customer.phone,
      nationalId: customer.idCard,
      nationality: customer.nat,
      whatsApp: customer.phone,
      email: `${customer.phone}@elite-cars.com`,
      customerAddress: 'الرياض، المملكة العربية السعودية',
      repInCharge: agent.name,
      duration,
      reason,
      notes: i % 2 === 0 ? 'العميل لديه موافقة مبدئية وبانتظار تأكيد الدفعة الأولى.' : undefined,
      reservationDate: new Date(Date.now() - 1000 * 60 * 60 * (24 * (10 - i) + 4)).toISOString(),
      reservationEndDate: new Date(Date.now() + 1000 * 60 * 60 * 24 * duration).toISOString(),
      reservationStatus: 'active',
      createdByUserId: agent.id,
      createdByUserName: agent.name,
      createdAt: new Date(Date.now() - 1000 * 60 * 60 * (24 * (10 - i) + 4)).toISOString()
    });

    // Populate a mock sale details block for these reserved cars so it demonstrates sales module
    const targetCar = cars[i];
    if (targetCar) {
      targetCar.sale = {
        sellerName: agent.name,
        buyerName: customer.name,
        paymentMethod: i % 2 === 0 ? 'تمويل بنكي' : 'نقداً (كاش)',
        contractNumber: `CONT-4000${i + 1}`,
        invoiceNumber: `INV-2026-${1000 + i}`,
        paymentStatus: i % 3 === 0 ? 'paid' : 'partially_paid',
        paidAmount: Math.round(targetCar.finalPrice * (i % 3 === 0 ? 1 : 0.4)),
        remainingAmount: Math.round(targetCar.finalPrice * (i % 3 === 0 ? 0 : 0.6)),
        deliveryMethod: 'شحن بسطحة لعنوان العميل',
        deliveryDate: new Date(Date.now() + 1000 * 60 * 60 * 24 * 3).toISOString(),
        deliveryNotes: 'العميل يرجو تلميع السيارة تلميع ساطع وغسيل بخرسانة النانو قبل التسليم.'
      };
    }
  }

  const logs: AuditLog[] = [
    { id: 'log-1', userId: 'u1', userName: 'أحمد القحطاني', action: 'تهيئة النظام', details: 'تم تهيئة النظام وتشغيل قاعدة البيانات بنجاح بنظام الترجمة الموحد i18n', createdAt: new Date(Date.now() - 1000 * 60 * 60 * 240).toISOString() },
    { id: 'log-2', userId: 'u1', userName: 'أحمد القحطاني', action: 'توليد البيانات', details: 'تم استيراد قائمة السيارات الأساسية مع كامل المواصفات الفنية والمرفقات', createdAt: new Date(Date.now() - 1000 * 60 * 60 * 239).toISOString() },
    { id: 'log-3', userId: 'u2', userName: 'خالد العتيبي', action: 'إضافة حجز', details: 'تم حجز سيارة تويوتا كامري وتوليد الفاتورة الضريبية رقم INV-2026-1000', createdAt: new Date(Date.now() - 1000 * 60 * 60 * 48).toISOString() },
    { id: 'log-4', userId: 'u3', userName: 'سارة الدوسري', action: 'إضافة حجز', details: 'تم حجز سيارة مرسيدس E300 للعميل بدر الدوسري وتوليد العقد CONT-400014', createdAt: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString() }
  ];

  const notifications: Notification[] = [
    { id: 'nt-1', title: 'تأكيد حجز سيارة', message: 'تم حجز تويوتا لاند كروزر بنجاح بواسطة خالد العتيبي مع المستندات بالكامل', isRead: false, createdAt: new Date().toISOString() },
    { id: 'nt-2', title: 'حالة النظام ممتازة', message: 'تم عمل نسخة احتياطية تلقائية لقاعدة البيانات وتخزينها بنجاح.', isRead: true, createdAt: new Date(Date.now() - 1000 * 60 * 120).toISOString() }
  ];

  return {
    branches: mockBranches,
    users: mockUsers,
    settings: defaultSettings,
    cars,
    reservations,
    logs,
    notifications
  };
}
