/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useRef } from 'react';
import { 
  X, 
  UploadCloud, 
  FileText, 
  Check, 
  AlertCircle, 
  Wrench,
  Building,
  Plus,
  ChevronDown,
  ChevronUp,
  Download,
  Trash2,
  Eye,
  Lock,
  Sparkles
} from 'lucide-react';
import { Car, Branch, Attachment, TechnicalSpecs, AttachmentVersion } from '../types';
import { Language, getTranslation } from '../i18n/translations';

interface CarFormProps {
  car?: Car | null; // If editing
  branches: Branch[];
  token: string;
  lang: Language;
  onClose: () => void;
  onSave: (carData: any) => Promise<any> | void;
}

export default function CarForm({ car, branches, token, lang, onClose, onSave }: CarFormProps) {
  // Error state
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  // Local branches list for immediate reactivity when adding inline
  const [localBranches, setLocalBranches] = useState<Branch[]>(branches);
  useEffect(() => {
    setLocalBranches(branches);
  }, [branches]);

  // Inline branch addition states
  const [showAddBranchInline, setShowAddBranchInline] = useState(false);
  const [newBranchName, setNewBranchName] = useState('');
  const [newBranchLocation, setNewBranchLocation] = useState('');
  const [isAddingBranch, setIsAddingBranch] = useState(false);

  // Accordion toggle states
  const [showSpecsAccordion, setShowSpecsAccordion] = useState(false);
  const [showAttachmentsAccordion, setShowAttachmentsAccordion] = useState(false);

  // --- 11 SPECIFIC REQUESTED FIELDS + REQUIRED API FIELDS ---
  const [make, setMake] = useState(''); // 1. الماركة
  const [trim, setTrim] = useState(''); // 2. الفئة
  const [model, setModel] = useState(''); // 2. الطراز (Required by server)
  const [year, setYear] = useState(new Date().getFullYear()); // 3. سنة الموديل
  const [color, setColor] = useState(''); // 3. اللون
  const [vin, setVin] = useState(''); // 4. رقم الهيكل
  const [vinMatching, setVinMatching] = useState<'matching' | 'mismatch'>('matching'); // 4. مطابقة الهيكل
  const [status, setStatus] = useState<'available' | 'reserved' | 'sold' | 'not_for_sale' | 'out_of_stock'>('available'); // 5. حالة السيارة
  const [branchId, setBranchId] = useState(''); // 6. توجد السيارة (الفرع)
  const [supplier, setSupplier] = useState(''); // 7. المورد
  const [ownershipType, setOwnershipType] = useState('مباشر'); // 8. المالك (مباشر أو تصريف)
  const [leasingStatus, setLeasingStatus] = useState<'not_leased' | 'leased'>('not_leased'); // 9. حالة التأجير
  const [customsNumber, setCustomsNumber] = useState(''); // 10. رقم البطاقة الجمركية
  const [repInCharge, setRepInCharge] = useState(''); // 11. مندوب الحجز (ReadOnly - fetched automatically)

  // --- OTHER REQUIRED AND COMPATIBILITY FIELDS ---
  const [plateNumber, setPlateNumber] = useState('');
  const [plateType, setPlateType] = useState('خصوصي - ملاكي');
  const [serialNumber, setSerialNumber] = useState('');
  const [registrationNumber, setRegistrationNumber] = useState('');
  const [condition, setCondition] = useState('جديد (أصفار)');
  const [price, setPrice] = useState<number>(0);
  const [costPrice, setCostPrice] = useState<number>(0);
  const [tax, setTax] = useState<number>(0);
  const [discount, setDiscount] = useState<number>(0);
  const [finalPrice, setFinalPrice] = useState<number>(0);
  const [currency, setCurrency] = useState('ر.س');
  const [notes, setNotes] = useState('');
  const [interiorColor, setInteriorColor] = useState('');
  const [bodyType, setBodyType] = useState('سيدان');
  const [doors, setDoors] = useState<number>(4);
  const [seats, setSeats] = useState<number>(5);
  const [fuelType, setFuelType] = useState<'petrol' | 'diesel' | 'hybrid' | 'electric'>('petrol');
  const [transmission, setTransmission] = useState<'manual' | 'automatic'>('automatic');
  const [engineCapacity, setEngineCapacity] = useState<number>(2000);
  const [cylinders, setCylinders] = useState<number>(4);
  const [enginePower, setEnginePower] = useState<number>(180);
  const [drive, setDrive] = useState('دفع أمامي FWD');
  const [odometer, setOdometer] = useState<number>(0);
  const [originCountry, setOriginCountry] = useState('');
  const [assemblyCountry, setAssemblyCountry] = useState('');
  const [entryDate, setEntryDate] = useState(new Date().toISOString().split('T')[0]);
  const [exitDate, setExitDate] = useState('');
  const [purchaseDate, setPurchaseDate] = useState(new Date().toISOString().split('T')[0]);
  const [saleDate, setSaleDate] = useState('');
  const [warranty, setWarranty] = useState('ضمان الوكيل المعتمد الممتد');
  const [warrantyDuration, setWarrantyDuration] = useState<number>(5);
  const [mainImage, setMainImage] = useState('https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600');
  const [previousOwner, setPreviousOwner] = useState('');

  // --- TECHNICAL SPECS STATES ---
  const [gulfSpecs, setGulfSpecs] = useState(true);
  const [americanSpecs, setAmericanSpecs] = useState(false);
  const [europeanSpecs, setEuropeanSpecs] = useState(false);
  const [fuelConsumption, setFuelConsumption] = useState('14.5 كم/لتر');
  const [navigationSystem, setNavigationSystem] = useState(false);
  const [rearCamera, setRearCamera] = useState(true);
  const [camera360, setCamera360] = useState(false);
  const [radar, setRadar] = useState(false);
  const [frontSensors, setFrontSensors] = useState(false);
  const [rearSensors, setRearSensors] = useState(true);
  const [cruiseControl, setCruiseControl] = useState(true);
  const [adaptiveCruise, setAdaptiveCruise] = useState(false);
  const [laneAssist, setLaneAssist] = useState(false);
  const [blindSpot, setBlindSpot] = useState(false);
  const [appleCarPlay, setAppleCarPlay] = useState(true);
  const [androidAuto, setAndroidAuto] = useState(true);
  const [sunroof, setSunroof] = useState(false);
  const [panorama, setPanorama] = useState(false);
  const [leatherSeats, setLeatherSeats] = useState(false);
  const [heatedSeats, setHeatedSeats] = useState(false);
  const [cooledSeats, setCooledSeats] = useState(false);
  const [seatMemory, setSeatMemory] = useState(false);
  const [pushButtonStart, setPushButtonStart] = useState(true);
  const [remoteStart, setRemoteStart] = useState(false);
  const [ledLights, setLedLights] = useState(true);
  const [xenonLights, setXenonLights] = useState(false);
  const [numberOfKeys, setNumberOfKeys] = useState<number>(2);
  const [spareTire, setSpareTire] = useState(true);
  const [catalog, setCatalog] = useState(true);

  // --- ATTACHMENTS STATES ---
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('exterior_image');
  const [uploading, setUploading] = useState(false);
  const [activeHistoryAttachment, setActiveHistoryAttachment] = useState<Attachment | null>(null);

  // --- OFFICIAL CUSTOMS CARD FILE STATES ---
  const [cardFilePath, setCardFilePath] = useState('');
  const [cardFileName, setCardFileName] = useState('');
  const [cardFileType, setCardFileType] = useState('');
  const [cardFileDate, setCardFileDate] = useState('');
  const [cardUploading, setCardUploading] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const replaceInputRef = useRef<HTMLInputElement>(null);
  const replacingAttachmentIdRef = useRef<string | null>(null);

  // Populate form if editing
  useEffect(() => {
    if (car) {
      setMake(car.make || '');
      setModel(car.model || car.trim || '');
      setTrim(car.trim || '');
      setYear(car.year || new Date().getFullYear());
      setCondition(car.vehicleCondition || 'جديد (أصفار)');
      setBranchId(car.branchId || '');
      setSupplier(car.supplier || '');
      setPreviousOwner(car.previousOwner || '');
      setCostPrice(car.costPrice || 0);
      setPrice(car.price || 0);
      setTax(car.tax || 0);
      setDiscount(car.discount || 0);
      setFinalPrice(car.finalPrice || 0);
      setCurrency(car.currency || 'ر.س');
      setNotes(car.notes || '');

      setColor(car.color || '');
      setInteriorColor(car.interiorColor || '');
      setBodyType(car.bodyType || 'سيدان');
      setDoors(car.doors || 4);
      setSeats(car.seats || 5);
      setFuelType(car.fuelType || 'petrol');
      setTransmission(car.transmission || 'automatic');
      setEngineCapacity(car.engineCapacity || 2000);
      setCylinders(car.cylinders || 4);
      setEnginePower(car.enginePower || 180);
      setDrive(car.drive || 'دفع أمامي FWD');
      setOdometer(car.odometer || 0);
      setVin(car.vin || '');
      setVinMatching((car.vinMatching as 'matching' | 'mismatch') || 'matching');
      setStatus(car.status || 'available');
      setOwnershipType(car.ownershipType || 'مباشر');
      setLeasingStatus((car.leasingStatus as 'leased' | 'not_leased') || 'not_leased');
      setRepInCharge(car.repInCharge || '');
      
      setPlateNumber(car.plateNumber || '');
      setPlateType(car.plateType || 'خصوصي - ملاكي');
      setSerialNumber(car.serialNumber || '');
      setRegistrationNumber(car.registrationNumber || '');
      setCustomsNumber(car.customsNumber || '');
      setCardFilePath(car.cardFilePath || '');
      setCardFileName(car.cardFileName || '');
      setCardFileType(car.cardFileType || '');
      setCardFileDate(car.cardFileDate || '');
      setOriginCountry(car.originCountry || '');
      setAssemblyCountry(car.assemblyCountry || '');
      setEntryDate(car.entryDate ? car.entryDate.split('T')[0] : '');
      setExitDate(car.exitDate ? car.exitDate.split('T')[0] : '');
      setPurchaseDate(car.purchaseDate ? car.purchaseDate.split('T')[0] : '');
      setSaleDate(car.saleDate ? car.saleDate.split('T')[0] : '');
      setWarranty(car.warranty || '');
      setWarrantyDuration(car.warrantyDuration || 5);
      setMainImage(car.mainImage || 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600');
      
      let initialAttachments = car.attachments || [];
      if (car.cardFilePath && !initialAttachments.some(att => att.category === 'customs_card')) {
        initialAttachments = [
          ...initialAttachments,
          {
            id: `att-card-sync`,
            name: car.cardFileName || 'البطاقة الجمركية',
            type: car.cardFileType || 'pdf',
            url: car.cardFilePath,
            size: '0 KB',
            category: 'customs_card',
            createdAt: car.cardFileDate || new Date().toISOString(),
            version: 1,
            versions: []
          }
        ];
      }
      setAttachments(initialAttachments);

      if (car.specs) {
        setGulfSpecs(!!car.specs.gulfSpecs);
        setAmericanSpecs(!!car.specs.americanSpecs);
        setEuropeanSpecs(!!car.specs.europeanSpecs);
        setFuelConsumption(car.specs.fuelConsumption || '14.5 كم/لتر');
        setNavigationSystem(!!car.specs.navigationSystem);
        setRearCamera(!!car.specs.rearCamera);
        setCamera360(!!car.specs.camera360);
        setRadar(!!car.specs.radar);
        setFrontSensors(!!car.specs.frontSensors);
        setRearSensors(!!car.specs.rearSensors);
        setCruiseControl(!!car.specs.cruiseControl);
        setAdaptiveCruise(!!car.specs.adaptiveCruise);
        setLaneAssist(!!car.specs.laneAssist);
        setBlindSpot(!!car.specs.blindSpot);
        setAppleCarPlay(!!car.specs.appleCarPlay);
        setAndroidAuto(!!car.specs.androidAuto);
        setSunroof(!!car.specs.sunroof);
        setPanorama(!!car.specs.panorama);
        setLeatherSeats(!!car.specs.leatherSeats);
        setHeatedSeats(!!car.specs.heatedSeats);
        setCooledSeats(!!car.specs.cooledSeats);
        setSeatMemory(!!car.specs.seatMemory);
        setPushButtonStart(!!car.specs.pushButtonStart);
        setRemoteStart(!!car.specs.remoteStart);
        setLedLights(!!car.specs.ledLights);
        setXenonLights(!!car.specs.xenonLights);
        setNumberOfKeys(car.specs.numberOfKeys || 2);
        setSpareTire(!!car.specs.spareTire);
        setCatalog(!!car.specs.catalog);
      }
    } else {
      // Default to first branch if adding new car
      if (localBranches.length > 0) {
        setBranchId(localBranches[0].id);
      }
    }
  }, [car, localBranches]);

  // Recalculate Final Price
  useEffect(() => {
    const calcTax = Math.round(price * 0.15);
    setTax(calcTax);
    setFinalPrice(price + calcTax - discount);
  }, [price, discount]);

  // Handle inline branch creation
  const handleAddBranchInline = async () => {
    if (!newBranchName.trim()) return;
    setIsAddingBranch(true);
    setErrorMsg('');
    try {
      const res = await fetch('/api/branches', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          name: newBranchName.trim(),
          location: newBranchLocation.trim() || 'المملكة العربية السعودية'
        })
      });
      if (res.ok) {
        const addedBranch = await res.json();
        setLocalBranches(prev => [...prev, addedBranch]);
        setBranchId(addedBranch.id);
        setNewBranchName('');
        setNewBranchLocation('');
        setShowAddBranchInline(false);
        setSuccessMsg(lang === 'ar' ? 'تم إضافة الفرع بنجاح وتحديده تلقائياً.' : 'Branch added successfully and selected.');
        setTimeout(() => setSuccessMsg(''), 4000);
      } else {
        const err = await res.json();
        setErrorMsg(err.error || 'فشل إضافة الفرع');
      }
    } catch (err) {
      setErrorMsg('خطأ في الاتصال بالخادم لإضافة الفرع');
    } finally {
      setIsAddingBranch(false);
    }
  };

  // Uploader helper
  const uploadFile = async (file: File, subfolder?: string): Promise<{ url: string; size: string }> => {
    const reader = new FileReader();
    return new Promise<{ url: string; size: string }>((resolve, reject) => {
      reader.onload = async () => {
        try {
          const base64Data = reader.result as string;
          const response = await fetch('/api/upload', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
              name: file.name,
              type: file.name.split('.').pop() || 'pdf',
              data: base64Data,
              subfolder
            })
          });

          if (!response.ok) {
            const err = response.status === 413 
              ? { error: 'حجم الملف كبير جداً، الحد الأقصى المسموح به 15 ميجابايت.' }
              : await response.json();
            throw new Error(err.error || 'فشل تحميل الملف.');
          }

          const result = await response.json();
          resolve({ url: result.url, size: result.size });
        } catch (err) {
          reject(err);
        }
      };
      reader.onerror = () => reject(new Error('فشل قراءة الملف المحلي.'));
      reader.readAsDataURL(file);
    });
  };

  // Handle Official Customs Card Upload
  const handleCardUpload = async (e: any) => {
    const files = e.target.files || e.dataTransfer?.files;
    if (!files || files.length === 0) return;
    
    const file = files[0];
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    
    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(ext)) {
      setErrorMsg(lang === 'ar' 
        ? 'عذراً، يرجى اختيار ملف من نوع: PDF, JPG, JPEG, PNG, WEBP فقط.'
        : 'Sorry, please select a file of type: PDF, JPG, JPEG, PNG, WEBP only.'
      );
      return;
    }
    
    setCardUploading(true);
    setErrorMsg('');
    
    try {
      const res = await uploadFile(file, 'cars');
      setCardFilePath(res.url);
      setCardFileName(file.name);
      setCardFileType(ext);
      setCardFileDate(new Date().toISOString());

      // Synchronize to attachments array
      const cardAttachment: Attachment = {
        id: `att-card-${Date.now()}`,
        name: file.name,
        type: ext === 'pdf' ? 'pdf' : 'image',
        url: res.url,
        size: res.size || '0 KB',
        category: 'customs_card',
        createdAt: new Date().toISOString(),
        version: 1,
        versions: []
      };
      
      setAttachments(prev => {
        const filtered = prev.filter(att => att.category !== 'customs_card');
        return [...filtered, cardAttachment];
      });
    } catch (err: any) {
      setErrorMsg(err.message || (lang === 'ar' ? 'فشل رفع المستند الرسمي.' : 'Failed to upload official document.'));
    } finally {
      setCardUploading(false);
    }
  };

  const removeCardFile = () => {
    setCardFilePath('');
    setCardFileName('');
    setCardFileType('');
    setCardFileDate('');
    // Synchronize to attachments array
    setAttachments(prev => prev.filter(att => att.category !== 'customs_card'));
  };

  // Handle Drag & Drop Upload
  const handleFileUpload = async (e: any) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    setUploading(true);
    setErrorMsg('');

    try {
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const uploaded = await uploadFile(file);

        const newAttachment: Attachment = {
          id: `att-${Date.now()}-${i}`,
          name: file.name,
          type: file.name.split('.').pop()?.toLowerCase() === 'pdf' ? 'pdf' : 'image',
          url: uploaded.url,
          size: uploaded.size,
          category: selectedCategory,
          createdAt: new Date().toISOString(),
          version: 1,
          versions: []
        };

        setAttachments(prev => [...prev, newAttachment]);
      }
    } catch (err: any) {
      console.error(err);
      setErrorMsg(err.message || 'حدث خطأ أثناء رفع وتحميل المرفقات.');
    } finally {
      setUploading(false);
    }
  };

  // Replace attachment maintaining version history
  const handleReplacementUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    const attId = replacingAttachmentIdRef.current;
    if (!files || files.length === 0 || !attId) return;

    setUploading(true);
    setErrorMsg('');

    try {
      const file = files[0];
      const uploaded = await uploadFile(file);

      setAttachments(prev => {
        return prev.map(att => {
          if (att.id === attId) {
            const oldVersion: AttachmentVersion = {
              id: `v-${Date.now()}`,
              version: att.version,
              url: att.url,
              size: att.size,
              createdAt: att.createdAt
            };

            const updatedHistory = att.versions ? [...att.versions, oldVersion] : [oldVersion];

            return {
              ...att,
              name: file.name,
              url: uploaded.url,
              size: uploaded.size,
              version: att.version + 1,
              versions: updatedHistory,
              createdAt: new Date().toISOString()
            };
          }
          return att;
        });
      });
    } catch (err: any) {
      console.error(err);
      setErrorMsg(err.message || 'حدث خطأ أثناء استبدال المرفق.');
    } finally {
      setUploading(false);
      replacingAttachmentIdRef.current = null;
    }
  };

  const removeAttachment = (id: string) => {
    setAttachments(prev => prev.filter(att => att.id !== id));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');

    // Field Validations - ONLY Make, Trim, Color, Year, VIN are mandatory
    if (!make || !trim || !color || !year || !vin) {
      setErrorMsg(lang === 'ar' 
        ? 'يرجى ملء الحقول الإلزامية فقط: الماركة، الفئة، اللون، سنة الموديل، ورقم الهيكل.' 
        : 'Please fill the mandatory fields only: Make, Trim, Color, Model Year, and VIN.');
      return;
    }

    // VIN validation: allow 4 to 17 alphanumeric characters
    const vinRegex = /^[A-Z0-9]{4,17}$/i;
    if (!vinRegex.test(vin)) {
      setErrorMsg(lang === 'ar' ? 'رقم الهيكل VIN غير صحيح. يجب أن يتكون من 4 إلى 17 حرفاً ورقماً.' : 'VIN is invalid. It must be between 4 and 17 alphanumeric characters.');
      return;
    }

    const compiledSpecs: TechnicalSpecs = {
      gulfSpecs,
      americanSpecs,
      europeanSpecs,
      fuelConsumption,
      navigationSystem,
      rearCamera,
      camera360,
      radar,
      frontSensors,
      rearSensors,
      cruiseControl,
      adaptiveCruise,
      laneAssist,
      blindSpot,
      appleCarPlay,
      androidAuto,
      sunroof,
      panorama,
      leatherSeats,
      heatedSeats,
      cooledSeats,
      seatMemory,
      pushButtonStart,
      remoteStart,
      ledLights,
      xenonLights,
      numberOfKeys,
      spareTire,
      catalog
    };

    try {
      await onSave({
        make,
        model: model || trim, // Sync model to trim or model
        trim,
        year: parseInt(year.toString()) || new Date().getFullYear(),
        color,
        interiorColor,
        bodyType,
        doors: parseInt((doors || 0).toString()) || 4,
        seats: parseInt((seats || 0).toString()) || 5,
        fuelType,
        transmission,
        engineCapacity: parseInt((engineCapacity || 0).toString()) || 2000,
        cylinders: parseInt((cylinders || 0).toString()) || 4,
        enginePower: parseInt((enginePower || 0).toString()) || 180,
        drive,
        odometer: parseFloat((odometer || 0).toString()) || 0,
        vin,
        vinMatching, // 4. مطابقة الهيكل
        status, // 5. حالة السيارة
        branchId: branchId || (localBranches[0]?.id || 'branch-1'), // 6. الفرع (مع إدخال تلقائي)
        supplier, // 7. المورد
        ownershipType, // 8. المالك
        leasingStatus, // 9. حالة التأجير
        customsNumber, // 10. رقم البطاقة الجمركية
        cardFilePath,
        cardFileName,
        cardFileType,
        cardFileDate,
        repInCharge, // 11. مندوب الحجز

        // Other fields
        plateNumber: plateNumber || `لوحة-${vin.substring(13)}`, // Auto-fallback if empty
        plateType,
        serialNumber,
        registrationNumber,
        vehicleCondition: condition,
        costPrice: parseFloat((costPrice || 0).toString()) || 0,
        price: parseFloat((price || 0).toString()) || 0,
        tax: parseFloat((tax || 0).toString()) || 0,
        discount: parseFloat((discount || 0).toString()) || 0,
        finalPrice: parseFloat((finalPrice || 0).toString()) || 0,
        currency,
        entryDate,
        exitDate,
        purchaseDate,
        saleDate,
        warranty,
        warrantyDuration: parseInt((warrantyDuration || 0).toString()) || 5,
        notes,
        mainImage,
        attachments,
        specs: compiledSpecs,
        previousOwner
      });
    } catch (err: any) {
      setErrorMsg(err.message || (lang === 'ar' ? 'حدث خطأ أثناء حفظ السيارة.' : 'An error occurred while saving the car.'));
    }
  };

  return (
    <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto text-right font-sans">
      <div className="bg-slate-900 rounded-xl w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col shadow-2xl border border-slate-800 animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
          <div>
            <h3 className="font-extrabold text-sm text-white flex items-center gap-1.5">
              <Building className="w-4 h-4 text-indigo-400" />
              <span>
                {car ? (lang === 'ar' ? 'تعديل بيانات وإيداع السيارة' : 'Edit & Deposit Vehicle') : (lang === 'ar' ? 'إيداع سيارة جديدة بالمعرض' : 'Deposit New Vehicle')}
              </span>
            </h3>
            <p className="text-[10px] text-slate-500 mt-0.5">
              {lang === 'ar' 
                ? 'لوحة إدارة مدخلات أصول المعرض الموحدة والمؤمنة.' 
                : 'Unified logistics dashboard for vehicle deposits.'}
            </p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Unified Scrollable Container */}
        <form onSubmit={handleSubmit} className="flex-1 p-5 space-y-5 overflow-y-auto custom-scrollbar">
          
          {errorMsg && (
            <div className="p-3 bg-rose-500/10 border border-rose-500/20 rounded-lg text-rose-400 text-xs font-extrabold flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 animate-pulse" />
              <span>{errorMsg}</span>
            </div>
          )}

          {successMsg && (
            <div className="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-xs font-extrabold flex items-center gap-2">
              <Sparkles className="w-4 h-4 shrink-0" />
              <span>{successMsg}</span>
            </div>
          )}

          {/* MAIN FIELDSET: 11 POINT PROTOCOL (نافذة واحدة) */}
          <div className="bg-slate-950/40 p-4 rounded-xl border border-slate-800/80 space-y-4">
            <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-2 flex items-center gap-1.5">
              <Sparkles className="w-3.5 h-3.5" />
              <span>{lang === 'ar' ? 'المعلومات اللوجستية الأساسية للمركبة (البروتوكول الرسمي)' : 'Core Vehicle Logistics Protocol'}</span>
            </h4>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-right">
              
              {/* صورة السيارة الأساسية */}
              <div className="col-span-1 md:col-span-3 bg-slate-900/40 p-4 rounded-lg border border-slate-800 flex flex-col md:flex-row gap-4 items-center">
                <div className="w-full md:w-32 h-20 bg-slate-950 rounded overflow-hidden border border-slate-800 flex items-center justify-center shrink-0">
                  {mainImage ? (
                    <img src={mainImage} alt="Main Car Preview" className="w-full h-full object-cover" referrerPolicy="no-referrer" />
                  ) : (
                    <span className="text-[10px] text-slate-500">{lang === 'ar' ? 'لا توجد صورة' : 'No Image'}</span>
                  )}
                </div>
                <div className="flex-1 w-full space-y-2">
                  <label className="block text-[10px] font-bold text-slate-400">
                    {lang === 'ar' ? 'صورة السيارة الرئيسية' : 'Primary Car Image'}
                  </label>
                  <div className="flex flex-col sm:flex-row gap-2">
                    <input
                      type="text"
                      placeholder={lang === 'ar' ? "أدخل رابط مباشر للصورة (URL)" : "Direct Image URL"}
                      value={mainImage}
                      onChange={e => setMainImage(e.target.value)}
                      className="flex-1 text-xs px-3 py-1.5 rounded border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                    <div className="flex gap-2">
                      <input
                        type="file"
                        id="main-image-upload"
                        onChange={async (e) => {
                          const files = e.target.files;
                          if (!files || files.length === 0) return;
                          try {
                            const res = await uploadFile(files[0], 'cars');
                            setMainImage(res.url);
                          } catch (err: any) {
                            setErrorMsg(err.message || 'Error uploading image');
                          }
                        }}
                        className="hidden"
                        accept="image/*"
                      />
                      <button
                        type="button"
                        onClick={() => document.getElementById('main-image-upload')?.click()}
                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-[11px] font-bold transition cursor-pointer shrink-0"
                      >
                        {lang === 'ar' ? 'تحميل صورة' : 'Upload Image'}
                      </button>
                      {mainImage && mainImage !== 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600' && (
                        <button
                          type="button"
                          onClick={() => setMainImage('https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&q=80&w=600')}
                          className="px-2 py-1.5 bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white rounded text-[11px] font-bold transition cursor-pointer"
                        >
                          {lang === 'ar' ? 'إعادة تعيين' : 'Reset'}
                        </button>
                      )}
                    </div>
                  </div>
                  <p className="text-[9px] text-slate-500">
                    {lang === 'ar' ? 'يمكنك رفع صورة مباشرة من جهازك أو إدخال رابط ويب مباشر للصورة.' : 'You can upload an image directly from your device or paste a web link.'}
                  </p>
                </div>
              </div>

              {/* 1. الماركة */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '1. الماركة' : '1. Make'} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="مثال: تويوتا، نيسان"
                  value={make}
                  onChange={e => setMake(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                />
              </div>

              {/* 2. الفئة */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '2. الفئة / الطراز' : '2. Trim / Model'} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="مثال: كامري فل كامل GLE"
                  value={trim}
                  onChange={e => {
                    setTrim(e.target.value);
                    setModel(e.target.value); // Keep model & trim synced for backend consistency
                  }}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 transition"
                />
              </div>

              {/* 3. سنة الموديل واللون */}
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">
                    {lang === 'ar' ? '3. الموديل' : '3. Year'} *
                  </label>
                  <input
                    type="number"
                    required
                    value={year}
                    onChange={e => setYear(parseInt(e.target.value || new Date().getFullYear().toString()))}
                    className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">
                    {lang === 'ar' ? 'اللون' : 'Color'} *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="مثال: أبيض لؤلؤي"
                    value={color}
                    onChange={e => setColor(e.target.value)}
                    className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>

              {/* 4. رقم الهيكل + مطابقة الهيكل */}
              <div className="col-span-1 md:col-span-2 grid grid-cols-3 gap-2">
                <div className="col-span-2">
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">
                    {lang === 'ar' ? '4. رقم الهيكل (VIN)' : '4. VIN Number'} *
                  </label>
                  <input
                    type="text"
                    required
                    maxLength={17}
                    placeholder={lang === 'ar' ? "رقم فريد (من 4 إلى 17 حرف ورقم)" : "Unique 4-17 alphanumeric VIN"}
                    value={vin}
                    onChange={e => setVin(e.target.value.toUpperCase())}
                    className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono tracking-wider"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 mb-1">
                    {lang === 'ar' ? 'مطابقة الهيكل' : 'VIN Matching'}
                  </label>
                  <select
                    value={vinMatching}
                    onChange={e => setVinMatching(e.target.value as any)}
                    className="w-full text-xs px-1.5 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer font-bold"
                  >
                    <option value="matching" className="text-emerald-400">✅ {lang === 'ar' ? 'مطابق' : 'Matching'}</option>
                    <option value="mismatch" className="text-rose-400">❌ {lang === 'ar' ? 'غير مطابق' : 'Mismatch'}</option>
                  </select>
                </div>
              </div>

              {/* 5. حالة السيارة */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '5. حالة السيارة بالمخزن' : '5. Vehicle Status'}
                </label>
                <select
                  value={status}
                  onChange={e => setStatus(e.target.value as any)}
                  className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer font-bold"
                >
                  <option value="available" className="text-emerald-400">🟢 {lang === 'ar' ? 'متوفرة في المخزن' : 'Available'}</option>
                  <option value="reserved" className="text-amber-400">🟡 {lang === 'ar' ? 'محجوزة لعميل' : 'Reserved'}</option>
                  <option value="sold" className="text-indigo-400 font-bold">🔵 {lang === 'ar' ? 'مباعة بالكامل' : 'Sold'}</option>
                  <option value="not_for_sale" className="text-slate-400">🔴 {lang === 'ar' ? 'غير معروضة للبيع' : 'Not For Sale'}</option>
                  <option value="out_of_stock" className="text-rose-400">⚫ {lang === 'ar' ? 'خارج المعرض / المخزن' : 'Out of Stock'}</option>
                </select>
              </div>

              {/* 6. توجد السيارة (الفرع المعرض) مع نظام إضافة فروع فوري مدمج */}
              <div className="relative">
                <div className="flex justify-between items-center mb-1">
                  <label className="block text-[10px] font-bold text-slate-400">
                    {lang === 'ar' ? '6. موقع تواجد السيارة (الفرع)' : '6. Branch Location'}
                  </label>
                  <button
                    type="button"
                    onClick={() => setShowAddBranchInline(!showAddBranchInline)}
                    className="text-[9px] font-bold text-indigo-400 hover:text-indigo-300 flex items-center gap-0.5"
                  >
                    <Plus className="w-3 h-3" />
                    <span>{lang === 'ar' ? 'إضافة فرع جديد' : 'New Branch'}</span>
                  </button>
                </div>

                {showAddBranchInline ? (
                  <div className="absolute right-0 top-6 left-0 z-10 bg-slate-950 border border-slate-800 p-2.5 rounded shadow-xl space-y-2">
                    <p className="text-[9px] font-bold text-indigo-400">{lang === 'ar' ? 'إدخال سريع لفرع جديد:' : 'Quick add branch:'}</p>
                    <input
                      type="text"
                      placeholder="اسم الفرع (مثال: فرع الدمام)"
                      value={newBranchName}
                      onChange={e => setNewBranchName(e.target.value)}
                      className="w-full text-[10px] px-2 py-1 rounded border border-slate-800 bg-slate-900 text-white"
                    />
                    <input
                      type="text"
                      placeholder="الموقع الجغرافي"
                      value={newBranchLocation}
                      onChange={e => setNewBranchLocation(e.target.value)}
                      className="w-full text-[10px] px-2 py-1 rounded border border-slate-800 bg-slate-900 text-white"
                    />
                    <div className="flex gap-1.5 justify-end">
                      <button
                        type="button"
                        onClick={() => setShowAddBranchInline(false)}
                        className="px-2 py-1 bg-slate-800 hover:bg-slate-750 text-[9px] text-slate-300 rounded"
                      >
                        {lang === 'ar' ? 'إلغاء' : 'Cancel'}
                      </button>
                      <button
                        type="button"
                        disabled={isAddingBranch}
                        onClick={handleAddBranchInline}
                        className="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-[9px] text-white rounded font-bold"
                      >
                        {isAddingBranch ? '...' : (lang === 'ar' ? 'إضافة وتثبيت' : 'Add')}
                      </button>
                    </div>
                  </div>
                ) : null}

                <select
                  value={branchId}
                  onChange={e => setBranchId(e.target.value)}
                  className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                >
                  <option value="" disabled>{lang === 'ar' ? 'اختر الفرع' : 'Select Branch'}</option>
                  {localBranches.map(b => (
                    <option key={b.id} value={b.id}>{b.name}</option>
                  ))}
                </select>
              </div>

              {/* 7. المورد */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '7. المورد / المصدر' : '7. Supplier'}
                </label>
                <input
                  type="text"
                  placeholder="الشركة الموردة للسيارة"
                  value={supplier}
                  onChange={e => setSupplier(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              {/* 8. المالك */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '8. مالك السيارة (الملكية)' : '8. Ownership Type'}
                </label>
                <select
                  value={ownershipType}
                  onChange={e => setOwnershipType(e.target.value)}
                  className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                >
                  <option value="مباشر">{lang === 'ar' ? 'مباشر (أصل من أصول المعرض)' : 'Direct Ownership'}</option>
                  <option value="تصريف">{lang === 'ar' ? 'تصريف (برسم الأمانة والمبيعات)' : 'Consignment / Tasreef'}</option>
                </select>
              </div>

              {/* 9. حالة التجيير */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '9. التجيير' : '9. Leasing Status'}
                </label>
                <select
                  value={leasingStatus}
                  onChange={e => setLeasingStatus(e.target.value as any)}
                  className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                >
                  <option value="not_leased">{lang === 'ar' ? 'لم يجير' : 'Not Leased'}</option>
                  <option value="leased">{lang === 'ar' ? 'مجير' : 'Leased'}</option>
                </select>
              </div>

              {/* 10. رقم البطاقة الجمركية */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">
                  {lang === 'ar' ? '10. رقم البطاقة الجمركية' : '10. Customs Card Number'}
                </label>
                <input
                  type="text"
                  placeholder="رقم بطاقة الاستيراد الجمركي"
                  value={customsNumber}
                  onChange={e => setCustomsNumber(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              {/* ملف المستند الرسمي (البطاقة الجمركية) */}
              <div className="col-span-1 md:col-span-3 border-t border-slate-800/50 pt-3">
                <label className="block text-[10px] font-bold text-slate-400 mb-1.5 flex items-center gap-1">
                  <FileText className="w-3.5 h-3.5 text-indigo-400" />
                  <span>{lang === 'ar' ? 'ملف المستند الرسمي للسيارة (البطاقة الجمركية)' : 'Official Car Document File (Customs Card)'}</span>
                  <span className="text-slate-500 text-[9px] font-normal">({lang === 'ar' ? 'اختياري - يدعم PDF, JPG, JPEG, PNG, WEBP' : 'Optional - Supports PDF, JPG, JPEG, PNG, WEBP'})</span>
                </label>
                
                {cardFilePath ? (
                  /* File Uploaded State */
                  <div className="p-3 rounded bg-slate-950 border border-indigo-500/20 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 overflow-hidden">
                      <div className="w-8 h-8 rounded bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                        <FileText className="w-4 h-4" />
                      </div>
                      <div className="overflow-hidden">
                        <span className="text-xs font-bold text-slate-200 block truncate" title={cardFileName}>{cardFileName}</span>
                        <span className="text-[9px] text-indigo-400 font-mono block">
                          {cardFileType?.toUpperCase()} | {lang === 'ar' ? 'تاريخ الرفع:' : 'Uploaded:'} {cardFileDate ? new Date(cardFileDate).toLocaleDateString(lang === 'ar' ? 'ar-SA' : 'en-US') : '-'}
                        </span>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-2 shrink-0">
                      <a 
                        href={cardFilePath} 
                        target="_blank" 
                        rel="noreferrer"
                        className="p-1.5 rounded bg-slate-900 hover:bg-slate-800 text-indigo-400 hover:text-indigo-300 transition"
                        title={lang === 'ar' ? 'معاينة الملف' : 'Preview file'}
                      >
                        <Eye className="w-4 h-4" />
                      </a>
                      <button
                        type="button"
                        onClick={removeCardFile}
                        className="p-1.5 rounded bg-slate-900 hover:bg-rose-950 text-slate-500 hover:text-rose-400 transition"
                        title={lang === 'ar' ? 'إزالة المستند' : 'Remove document'}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ) : (
                  /* Upload and Drag Target State */
                  <div 
                    onDragOver={e => e.preventDefault()}
                    onDrop={e => {
                      e.preventDefault();
                      handleCardUpload({ target: { files: e.dataTransfer.files } });
                    }}
                    onClick={() => {
                      const input = document.getElementById('card-file-input');
                      if (input) input.click();
                    }}
                    className="border border-dashed border-slate-800 hover:border-indigo-500/60 rounded p-4 text-center cursor-pointer hover:bg-slate-950/30 transition group flex flex-col items-center justify-center gap-1.5"
                  >
                    <input 
                      type="file" 
                      id="card-file-input"
                      onChange={handleCardUpload}
                      className="hidden" 
                      accept=".pdf,image/jpeg,image/jpg,image/png,image/webp"
                    />
                    {cardUploading ? (
                      <div className="flex flex-col items-center gap-1">
                        <span className="animate-spin block w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full"></span>
                        <span className="text-[10px] font-bold text-indigo-400">{lang === 'ar' ? 'جاري رفع المستند وحفظه بالخادم...' : 'Uploading official document...'}</span>
                      </div>
                    ) : (
                      <>
                        <UploadCloud className="w-6 h-6 text-slate-500 group-hover:text-indigo-400 transition" />
                        <span className="text-[11px] text-slate-400 font-bold group-hover:text-slate-300 transition">
                          {lang === 'ar' ? 'اسحب المستند وأفلته هنا، أو اضغط للتصفح والرفع' : 'Drag & drop document here, or click to browse'}
                        </span>
                        <span className="text-[9px] text-slate-500">
                          PDF, JPG, JPEG, PNG, WEBP (Max 15MB)
                        </span>
                      </>
                    )}
                  </div>
                )}
              </div>

              {/* 11. مندوب الحجز (ReadOnly / Populated dynamically) */}
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1 text-indigo-300">
                  {lang === 'ar' ? '11. مندوب الحجز المسؤول' : '11. Booking Rep'} (تلقائي)
                </label>
                <div className="w-full text-xs px-3 py-2.5 rounded border border-slate-800/80 bg-slate-950/70 text-slate-400 flex items-center gap-1.5 select-none">
                  <Lock className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                  <span className="font-bold truncate">
                    {repInCharge ? repInCharge : (lang === 'ar' ? 'لا يوجد مندوب حجز حالياً' : 'No reservation logged')}
                  </span>
                </div>
              </div>

            </div>
          </div>

          {/* FINANCIAL SECTION: COMPATIBILITY AND REQUIRED BY CLIENT FLOWS */}
          <div className="bg-slate-950/40 p-4 rounded-xl border border-slate-800/80 space-y-4">
            <h4 className="text-[11px] font-extrabold text-indigo-400 border-b border-indigo-500/10 pb-2 flex items-center gap-1.5">
              <span>💰</span>
              <span>{lang === 'ar' ? 'التسعير والتكلفة والبيانات الأساسية الأخرى' : 'Financial Details & Asset Values'}</span>
            </h4>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-right">
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'سعر البيع النقدي' : 'Selling Price'}</label>
                <input
                  type="number"
                  value={price}
                  onChange={e => setPrice(parseFloat(e.target.value || '0'))}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 font-sans focus:outline-none focus:border-indigo-500"
                />
              </div>
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'سعر التكلفة' : 'Cost Price'}</label>
                <input
                  type="number"
                  value={costPrice}
                  onChange={e => setCostPrice(parseFloat(e.target.value || '0'))}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 font-sans focus:outline-none focus:border-indigo-500"
                />
              </div>
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'الضريبة (15% تلقائية)' : 'Tax (15% Auto)'}</label>
                <div className="w-full text-xs px-3 py-2 rounded border border-slate-800/60 bg-slate-950/60 text-slate-400 font-sans select-none">
                  {tax.toLocaleString()} {currency}
                </div>
              </div>
              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'السعر النهائي الشامل' : 'Final Price'}</label>
                <div className="w-full text-xs px-3 py-2 rounded border border-indigo-500/10 bg-indigo-500/5 text-indigo-400 font-extrabold font-sans select-none">
                  {finalPrice.toLocaleString()} {currency}
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'رقم اللوحة' : 'Plate Number'}</label>
                <input
                  type="text"
                  placeholder="مثال: أ ب ج 1234"
                  value={plateNumber}
                  onChange={e => setPlateNumber(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'الممشى (Odometer KM)' : 'Odometer (KM)'}</label>
                <input
                  type="number"
                  value={odometer}
                  onChange={e => setOdometer(parseFloat(e.target.value || '0'))}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'حالة الاستخدام' : 'Usage Condition'}</label>
                <select
                  value={condition}
                  onChange={e => setCondition(e.target.value)}
                  className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                >
                  <option value="جديد (أصفار)">جديد (أصفار) / Brand New</option>
                  <option value="مستعمل مميز (مضمون)">مستعمل مميز (مضمون) / Pre-Owned</option>
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'اللون الداخلي للمركبة' : 'Interior Color'}</label>
                <input
                  type="text"
                  placeholder="مثال: مخمل بيج"
                  value={interiorColor}
                  onChange={e => setInteriorColor(e.target.value)}
                  className="w-full text-xs px-3 py-2 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"
                />
              </div>

            </div>
          </div>

          {/* ACCORDION 1: TECHNICAL SPECS & OPTIONAL FEATURES */}
          <div className="bg-slate-950/40 rounded-xl border border-slate-800/80 overflow-hidden">
            <button
              type="button"
              onClick={() => setShowSpecsAccordion(!showSpecsAccordion)}
              className="w-full p-4 flex justify-between items-center bg-slate-950/60 hover:bg-slate-950 transition"
            >
              <span className="font-extrabold text-xs text-slate-300 flex items-center gap-1.5">
                <Wrench className="w-4 h-4 text-indigo-400" />
                <span>{lang === 'ar' ? 'المواصفات الفنية المتقدمة والمقصورة (اختياري)' : 'Technical Specs & Cab Options (Optional)'}</span>
              </span>
              {showSpecsAccordion ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
            </button>

            {showSpecsAccordion && (
              <div className="p-4 border-t border-slate-800 space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-right">
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'ناقل الحركة' : 'Transmission'}</label>
                    <select value={transmission} onChange={e => setTransmission(e.target.value as any)} className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-300">
                      <option value="automatic">{lang === 'ar' ? 'أوتوماتيك / Auto' : 'Automatic'}</option>
                      <option value="manual">{lang === 'ar' ? 'عادي / Manual' : 'Manual'}</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'نوع الوقود' : 'Fuel Type'}</label>
                    <select value={fuelType} onChange={e => setFuelType(e.target.value as any)} className="w-full text-xs px-2 py-2 rounded border border-slate-800 bg-slate-950 text-slate-300">
                      <option value="petrol">{lang === 'ar' ? 'بنزين / Petrol' : 'Petrol'}</option>
                      <option value="diesel">{lang === 'ar' ? 'ديزل / Diesel' : 'Diesel'}</option>
                      <option value="hybrid">{lang === 'ar' ? 'هجين / Hybrid' : 'Hybrid'}</option>
                      <option value="electric">{lang === 'ar' ? 'كهرباء بالكامل' : 'Electric'}</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'سعة المحرك (CC)' : 'Engine Capacity (CC)'}</label>
                    <input type="number" value={engineCapacity} onChange={e => setEngineCapacity(parseInt(e.target.value || '2000'))} className="w-full text-xs px-3 py-1.5 rounded border border-slate-800 bg-slate-950 text-slate-200 font-sans" />
                  </div>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 mb-1">{lang === 'ar' ? 'نوع الدفع' : 'Drive Train'}</label>
                    <input type="text" value={drive} onChange={e => setDrive(e.target.value)} className="w-full text-xs px-3 py-1.5 rounded border border-slate-800 bg-slate-950 text-slate-200" />
                  </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 mt-4">
                  {[
                    { label: 'gulfSpecs', state: gulfSpecs, set: setGulfSpecs },
                    { label: 'sunroof', state: sunroof, set: setSunroof },
                    { label: 'panorama', state: panorama, set: setPanorama },
                    { label: 'leatherSeats', state: leatherSeats, set: setLeatherSeats },
                    { label: 'appleCarPlay', state: appleCarPlay, set: setAppleCarPlay },
                    { label: 'androidAuto', state: androidAuto, set: setAndroidAuto },
                    { label: 'navigationSystem', state: navigationSystem, set: setNavigationSystem },
                    { label: 'rearCamera', state: rearCamera, set: setRearCamera },
                    { label: 'camera360', state: camera360, set: setCamera360 },
                    { label: 'radar', state: radar, set: setRadar },
                    { label: 'pushButtonStart', state: pushButtonStart, set: setPushButtonStart },
                    { label: 'remoteStart', state: remoteStart, set: setRemoteStart },
                  ].map((item, idx) => (
                    <label key={idx} className="flex items-center gap-2 text-xs font-bold text-slate-300 cursor-pointer hover:text-white bg-slate-950 p-2 rounded border border-slate-800 hover:border-slate-700">
                      <input type="checkbox" checked={item.state} onChange={e => item.set(e.target.checked)} className="rounded text-indigo-600 bg-slate-900 border-slate-800" />
                      <span>{getTranslation(lang, item.label as any)}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* ACCORDION 2: OFFICIAL ATTACHMENTS */}
          {status === 'reserved' && (
            <div className="bg-slate-950/40 rounded-xl border border-slate-800/80 overflow-hidden">
              <button
                type="button"
                onClick={() => setShowAttachmentsAccordion(!showAttachmentsAccordion)}
                className="w-full p-4 flex justify-between items-center bg-slate-950/60 hover:bg-slate-950 transition"
              >
                <span className="font-extrabold text-xs text-slate-300 flex items-center gap-1.5">
                  <FileText className="w-4 h-4 text-indigo-400" />
                  <span>{lang === 'ar' ? `المستندات والمرفقات الرسمية للمركبة (${attachments.length})` : `Official Documents & Media (${attachments.length})`}</span>
                </span>
                {showAttachmentsAccordion ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
              </button>

              {showAttachmentsAccordion && (
                <div className="p-4 border-t border-slate-800 space-y-4 text-right">
                  <div className="p-3 bg-slate-950 border border-slate-800/80 rounded-lg flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <span className="text-[10px] font-bold text-slate-400">{lang === 'ar' ? 'تصنيف المرفق لسرعة البحث:' : 'Doc Category:'}</span>
                      <select
                        value={selectedCategory}
                        onChange={e => setSelectedCategory(e.target.value)}
                        className="text-[11px] font-bold bg-slate-900 border border-slate-800 rounded px-2.5 py-1 text-indigo-400 cursor-pointer"
                      >
                        <option value="exterior_image">{lang === 'ar' ? 'صورة خارجية للمركبة' : 'Exterior Image'}</option>
                        <option value="interior_image">{lang === 'ar' ? 'صورة مقصورة داخلية' : 'Interior Image'}</option>
                        <option value="vin_image">{lang === 'ar' ? 'صورة رقم الهيكل المحفور' : 'VIN Engraving Photo'}</option>
                        <option value="istimara_image">{lang === 'ar' ? 'صورة الاستمارة / الترخيص' : 'Istimara Registration Scan'}</option>
                        <option value="id_image">{lang === 'ar' ? 'صورة هوية المالك / العميل' : 'Identity Scan'}</option>
                        <option value="contract">{lang === 'ar' ? 'صورة العقد المبرم' : 'Contract'}</option>
                        <option value="invoice">{lang === 'ar' ? 'صورة الفاتورة الضريبية' : 'Invoice'}</option>
                        <option value="other">{lang === 'ar' ? 'مرفق أو مستند عام آخر' : 'Other'}</option>
                      </select>
                    </div>
                  </div>

                  {/* Drag n Drop upload */}
                  <div 
                    onDragOver={e => e.preventDefault()}
                    onDrop={e => {
                      e.preventDefault();
                      handleFileUpload({ target: { files: e.dataTransfer.files } });
                    }}
                    onClick={() => fileInputRef.current?.click()}
                    className="border-2 border-dashed border-slate-800 hover:border-indigo-500/80 rounded-lg p-6 text-center cursor-pointer hover:bg-slate-950/40 transition group"
                  >
                    <input 
                      type="file" 
                      ref={fileInputRef} 
                      onChange={handleFileUpload} 
                      multiple 
                      className="hidden" 
                      accept="image/*,application/pdf"
                    />
                    <UploadCloud className="w-8 h-8 text-slate-500 group-hover:text-indigo-400 mx-auto transition" />
                    <p className="text-xs text-slate-400 font-bold mt-2">
                      {lang === 'ar' ? 'اسحب وأفلت الملفات هنا، أو اضغط للتصفح والرفع' : 'Drag & drop files here or click to browse'}
                    </p>
                    <p className="text-[9px] text-slate-500 mt-0.5">
                      {lang === 'ar' ? 'يدعم صور السيارة، الفحص، بطاقة الاستيراد الجمركية وعقود المبيعات' : 'Supports vehicle photos, customs reports and sales contracts'}
                    </p>
                  </div>

                  {uploading && (
                    <div className="p-2 bg-indigo-500/5 border border-dashed border-indigo-500/20 rounded text-xs font-bold text-indigo-400 flex items-center justify-center gap-2">
                      <span className="animate-spin block w-3 h-3 border-2 border-indigo-500 border-t-transparent rounded-full"></span>
                      <span>{lang === 'ar' ? 'جاري رفع وحقن المرفق بالملف بالخادم...' : 'Uploading attachment to server...'}</span>
                    </div>
                  )}

                  {/* Hidden input for replacement */}
                  <input 
                    type="file" 
                    ref={replaceInputRef} 
                    onChange={handleReplacementUpload} 
                    className="hidden" 
                    accept="image/*,application/pdf"
                  />

                  {/* Attachments List */}
                  {attachments.length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                      {attachments.map(att => (
                        <div key={att.id} className="p-3 rounded-lg border border-slate-800 bg-slate-950 flex flex-col gap-2">
                          <div className="flex items-start justify-between gap-3">
                            <div className="flex items-center gap-2 overflow-hidden">
                              <FileText className="w-4 h-4 text-indigo-400 shrink-0" />
                              <div className="overflow-hidden">
                                <span className="text-xs font-bold text-slate-200 block truncate" title={att.name}>{att.name}</span>
                                <span className="text-[9px] text-slate-500 block">
                                  {att.size} | {att.category?.toUpperCase() || 'GENERAL'}
                                </span>
                              </div>
                            </div>
                            <button
                              type="button"
                              onClick={() => removeAttachment(att.id)}
                              className="p-1 rounded bg-slate-900 hover:bg-rose-950 text-slate-500 hover:text-rose-400 transition"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

        </form>

        {/* Footer */}
        <div className="p-4 border-t border-slate-800 flex justify-between items-center bg-slate-950">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 rounded transition cursor-pointer"
          >
            {lang === 'ar' ? 'إلغاء التراجع' : 'Cancel'}
          </button>
          <button
            type="button"
            onClick={handleSubmit}
            className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-xs font-extrabold text-white rounded transition cursor-pointer shadow-lg shadow-indigo-600/15 flex items-center gap-1.5"
          >
            <Check className="w-4 h-4" />
            <span>{lang === 'ar' ? 'حفظ وتثبيت البيانات بالمعرض' : 'Save & Close'}</span>
          </button>
        </div>

      </div>
    </div>
  );
}
