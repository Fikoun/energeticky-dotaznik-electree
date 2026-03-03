/**
 * Test data for prefilling the form — used by admin users for testing.
 * Uses "Adrian Staněk" / "janko@stanektech.cz" as the primary contact.
 *
 * ALL 122 Raynet custom fields + 8 native/address/person fields are covered.
 * Conditional branches are set so that every downstream field is filled.
 * File-upload fields are left as empty arrays (no physical files to attach).
 * The 3 metadata fields (formId, formSubmittedAt, formUrl) are injected
 * server-side by RaynetConnector — they don't come from the form.
 */
export const getTestFormData = () => ({
  // ==========================================================================
  // KROK 1 — Identifikační údaje zákazníka (8 native/address/person + 2 custom)
  // ==========================================================================
  // native: companyName, ico, dic
  // address: email, phone, address, companyAddress
  // person: contactPerson
  // custom: customerType, customerType.otherSpecification
  companyName: 'StanekTech s.r.o.',
  ico: '12345678',
  dic: 'CZ12345678',
  contactPerson: 'Adrian Staněk',
  phone: '+420 777 123 456',
  email: 'janko@stanektech.cz',
  companyAddress: 'Vinohradská 42, 120 00 Praha 2',
  address: 'Průmyslová 18, 150 00 Praha 5 - Stodůlky',
  sameAsCompanyAddress: false,
  customerType: {
    industrial: true,
    commercial: true,
    services: false,
    agriculture: false,
    public: false,
    other: true,
    otherSpecification: 'Výzkum a vývoj bateriových technologií',
  },
  additionalContacts: [
    {
      id: '1',
      name: 'Jan Novák',
      position: 'Technický ředitel',
      phone: '+420 602 345 678',
      email: 'novak@stanektech.cz',
      isPrimary: false,
    },
    {
      id: '2',
      name: 'Eva Procházková',
      position: 'Energetický manažer',
      phone: '+420 608 111 222',
      email: 'prochazkova@stanektech.cz',
      isPrimary: false,
    },
  ],

  // ==========================================================================
  // KROK 2 — Parametry odběrného místa (14 custom from energy sources + technical)
  // ==========================================================================
  // custom: hasFveVte, fveVtePower, accumulationPercentage,
  //         interestedInFveVte (*), interestedInInstallationProcessing (*),
  //         interestedInElectromobility,
  //         hasTransformer, transformerPower, transformerVoltage, transformerYear,
  //         transformerType, transformerCurrent, coolingType,
  //         circuitBreakerType (*), customCircuitBreaker (*),
  //         mainCircuitBreaker, reservedPower, reservedOutput,
  //         distributionTerritory, ldsName (*), ldsOwner (*), ldsNotes (*),
  //         sharesElectricity, electricityShared, receivesSharedElectricity, electricityReceived,
  //         monthlyConsumption, monthlyMaxConsumption, significantConsumption
  //   (*) = conditional on another value; filled below even if not shown in UI
  hasFveVte: 'yes',
  fveVtePower: '150',
  accumulationPercentage: '30',
  interestedInFveVte: 'yes',               // shown only when hasFveVte=no, but fill for Raynet
  interestedInInstallationProcessing: 'yes', // shown only when interestedInFveVte=yes
  interestedInElectromobility: 'yes',
  hasTransformer: 'yes',
  transformerPower: '630',
  transformerVoltage: '22',
  coolingType: 'oil',
  transformerYear: '2018',
  transformerType: 'ABB RESIBLOC 630 kVA',
  transformerCurrent: '16.5',
  circuitBreakerType: '250A',              // shown only when hasTransformer=no, but fill
  customCircuitBreaker: 'Siemens 3VA2 250A', // shown only when circuitBreakerType=other
  sharesElectricity: 'yes',
  electricityShared: '2500',
  receivesSharedElectricity: 'yes',
  electricityReceived: '1800',
  mainCircuitBreaker: '250',
  reservedPower: '200',
  reservedOutput: '180',
  distributionTerritory: 'lds',            // pick LDS to fill lds* fields
  ldsName: 'Průmyslový park Stodůlky',
  ldsOwner: 'Stodůlky Industrial a.s.',
  ldsNotes: 'Smlouva o připojení do LDS platná do 2030.',
  monthlyConsumption: '45',
  monthlyMaxConsumption: '120',
  significantConsumption: 'Výrobní linka pracuje v třísměnném provozu, špičky v ranních hodinách. CNC stroje, kompresory, chladicí systém.',

  // ==========================================================================
  // KROK 3 — Energetické potřeby (27 custom + 32 TimeSlider sub-fields)
  // ==========================================================================
  // custom: hasDistributionCurves, measurementType, measurementTypeOther,
  //         yearlyConsumption, dailyAverageConsumption, maxConsumption, minConsumption,
  //         hasCriticalConsumption, criticalConsumptionDescription,
  //         energyAccumulation, energyAccumulationAmount, batteryCycles,
  //         requiresBackup, backupDescription, backupDuration, backupDurationHours,
  //         priceOptimization, hasElectricityProblems, electricityProblemsDetails,
  //         hasEnergyAudit, energyAuditDetails,
  //         hasOwnEnergySource, ownEnergySourceDetails,
  //         monthlyMaxConsumption, significantConsumption (already in step 2 section)
  hasDistributionCurves: 'yes',
  // distributionCurvesFile — file upload, cannot prefill
  measurementType: 'other',               // pick 'other' to fill measurementTypeOther
  measurementTypeOther: 'Průběhové měření s 15-minutovým intervalem (vlastní datalogger)',
  yearlyConsumption: '540',
  dailyAverageConsumption: '1480',
  maxConsumption: '180',
  minConsumption: '25',
  hasCriticalConsumption: 'yes',
  criticalConsumptionDescription: 'Serverovna a chladicí systém musí běžet nepřetržitě. Výpadek = riziko ztráty dat a poškození zásob.',
  energyAccumulation: 'specific',
  energyAccumulationAmount: '500',
  batteryCycles: 'multiple',
  requiresBackup: 'yes',
  backupDescription: 'Záložní napájení pro serverovnu, bezpečnostní systémy a nouzové osvětlení.',
  backupDuration: 'exact-time',            // pick exact-time to fill backupDurationHours
  backupDurationHours: '2.5',
  priceOptimization: 'yes',
  hasElectricityProblems: 'yes',
  electricityProblemsDetails: 'Občasné výpadky napětí – přibližně 2× měsíčně, poklesy napětí při špičkách odběru.',
  hasEnergyAudit: 'yes',                  // pick yes to fill energyAuditDetails
  energyAuditDetails: 'Audit proveden firmou EnergoConsult v r. 2024, doporučena instalace bateriového úložiště 500 kWh.',
  hasOwnEnergySource: 'yes',
  ownEnergySourceDetails: 'FVE 150 kWp na střeše výrobní haly A, instalace 2022.',

  // TimeSlider — vzory spotřeby (32 fields: weekdayPattern.* + weekendPattern.*)
  weekdayPattern: {
    morningPeakStart: 6,
    morningPeakEnd: 10,
    morningPeakConsumption: 85,
    noonLowStart: 11,
    noonLowEnd: 13,
    noonLowConsumption: 50,
    afternoonPeakStart: 14,
    afternoonPeakEnd: 18,
    afternoonPeakConsumption: 90,
    nightLowStart: 22,
    nightLowEnd: 5,
    nightLowConsumption: 20,
    q1Consumption: 80,
    q2Consumption: 70,
    q3Consumption: 75,
    q4Consumption: 85,
  },
  weekendPattern: {
    morningPeakStart: 8,
    morningPeakEnd: 12,
    morningPeakConsumption: 40,
    noonLowStart: 12,
    noonLowEnd: 14,
    noonLowConsumption: 30,
    afternoonPeakStart: 14,
    afternoonPeakEnd: 17,
    afternoonPeakConsumption: 45,
    nightLowStart: 21,
    nightLowEnd: 7,
    nightLowConsumption: 15,
    q1Consumption: 35,
    q2Consumption: 30,
    q3Consumption: 32,
    q4Consumption: 38,
  },

  // ==========================================================================
  // KROK 4 — Cíle a očekávání (5 custom)
  // ==========================================================================
  // custom: customerType (step 1), goalDetails, otherPurposeDescription,
  //         priority1, priority2, priority3
  goals: {
    fveOverflow: true,
    peakShaving: true,
    backupPower: true,
    machineSupport: true,
    powerReduction: true,
    energyTrading: true,
    subsidy: true,
    other: true,
  },
  otherPurposeDescription: 'Testování bateriových technologií pro potřeby vlastního R&D oddělení.',
  goalDetails: 'Primárně chceme optimalizovat spotřebu z FVE, snížit špičky odběru ze sítě a zajistit záložní napájení serverovny.',
  priority1: 'fve-overflow',
  priority2: 'peak-shaving',
  priority3: 'backup-power',

  // ==========================================================================
  // KROK 5 — Infrastruktura a prostor (10 custom)
  // ==========================================================================
  // custom: siteDescription, hasOutdoorSpace, outdoorSpaceSize,
  //         hasIndoorSpace, indoorSpaceSize, indoorSpaceType,
  //         accessibility, accessibilityLimitations,
  //         infrastructureNotes, hasProjectDocumentation
  // files: sitePhotos, visualizations, projectDocumentationFiles
  siteDescription: 'Areál se dvěma výrobními halami a administrativní budovou. K dispozici zpevněná plocha u trafostanice cca 80 m².',
  hasOutdoorSpace: 'yes',
  outdoorSpaceSize: '80',
  hasIndoorSpace: 'yes',
  indoorSpaceType: 'Nevyužitá technická místnost v přízemí haly B',
  indoorSpaceSize: '35',
  accessibility: 'limited',
  accessibilityLimitations: 'Příjezd kamionem možný pouze přes hlavní bránu, nutné ohlášení 24 h předem. Nosnost podlahy 5 t/m².',
  hasProjectDocumentation: 'yes',         // pick yes to fill documentationTypes
  documentationTypes: {
    sitePlan: true,
    electricalPlan: true,
    buildingPlan: true,
    other: true,
  },
  // projectDocumentationFiles — file upload
  infrastructureNotes: 'Trafostanice je umístěna na severní straně areálu, cca 15 m od navrhované lokace baterie. Kabelová trasa připravena.',

  // ==========================================================================
  // KROK 6 — Provozní a legislativní rámec (12 custom)
  // ==========================================================================
  // custom: gridConnectionPlanned, powerIncreaseRequested, requestedPowerIncrease,
  //         requestedOutputIncrease, connectionApplicationBy, willingToSignPowerOfAttorney,
  //         hasEnergeticSpecialist, specialistName, specialistPosition,
  //         specialistPhone, specialistEmail, legislativeNotes
  // files: connectionContractFile, connectionApplicationFile
  gridConnectionPlanned: 'yes',
  powerIncreaseRequested: 'yes',           // pick yes to fill requestedPowerIncrease + requestedOutputIncrease
  requestedPowerIncrease: '0.5',
  requestedOutputIncrease: '0.3',
  connectionApplicationBy: 'customerbyelectree',
  willingToSignPowerOfAttorney: 'yes',
  hasEnergeticSpecialist: 'yes',
  specialistName: 'Ing. Petr Svoboda',
  specialistPosition: 'specialist',
  specialistPhone: '+420 603 987 654',
  specialistEmail: 'svoboda@energo-consulting.cz',
  legislativeNotes: 'Firma má platné stavební povolení pro technologické změny v areálu. Připravena smlouva o připojení k LDS.',

  // ==========================================================================
  // KROK 7 — Navržený postup a poznámky (2 custom: additionalNotes + agreements/proposedSteps in form)
  // ==========================================================================
  proposedSteps: {
    preliminary: true,
    technical: true,
    detailed: true,
    consultancy: true,
    support: true,
    other: true,
    otherDescription: 'Pomoc se žádostí o dotaci z programu OPTAK.',
  },
  additionalNotes: 'Preferujeme realizaci v Q2 2026. Rozpočet předběžně 8–12 mil. Kč. Máme zájem i o servisní smlouvu.',
  agreements: {
    dataProcessing: true,
    technicalVisit: true,
    marketing: true,
  },

  // ==========================================================================
  // KROK 8 — Energetický dotazník (16 custom)
  // ==========================================================================
  // custom: billingMethod, spotSurcharge, fixPrice, gradualFixPrice, gradualSpotSurcharge,
  //         fixPercentage, spotPercentage, currentEnergyPrice, priceImportance,
  //         electricitySharing, sharingDetails, hasGas, gasConsumption, gasBill,
  //         hasCogeneration, cogenerationDetails,
  //         hotWaterConsumption, steamConsumption, otherConsumption, energyNotes
  // files: billingDocuments, cogenerationPhotos
  billingMethod: 'gradual',
  spotSurcharge: '0.12',
  fixPrice: '3.80',
  fixPercentage: '60',
  spotPercentage: '40',
  gradualFixPrice: '3.20',
  gradualSpotSurcharge: '0.15',
  currentEnergyPrice: '4.50',
  priceImportance: 7,
  electricitySharing: 'yes',              // pick yes to fill sharingDetails
  sharingDetails: 'Sdílení FVE přetoků se sousedním skladovým areálem přes LDS.',
  hasGas: 'yes',
  gasConsumption: '12000',
  gasBill: '180000',
  gasUsage: {
    heating: true,
    hotWater: true,
    technology: true,
    cooking: false,
  },
  hotWaterConsumption: '800',
  steamConsumption: '120',
  otherConsumption: 'Kompresorová stanice pro výrobní linku, UPS pro serverovnu.',
  hasCogeneration: 'yes',                 // pick yes to fill cogenerationDetails
  cogenerationDetails: 'Mikro-kogenerační jednotka Viessmann Vitobloc 20 kWe / 39 kWt, v provozu od 2021.',
  energyNotes: 'Uvažujeme o přechodu z plynového vytápění na tepelná čerpadla v horizontu 2–3 let. Zájem o komplexní energetický management.',
})

/**
 * Step notes for testing — these are stored in App.jsx state, not in form data,
 * but they get sent as stepNotes.1..8 to Raynet (8 custom fields).
 */
export const getTestStepNotes = () => ({
  1: 'Zákazník nalezen přes IČO v MERK API, údaje ověřeny.',
  2: 'Transformátor ABB RESIBLOC v dobrém stavu, revize 2024.',
  3: 'Odběrové diagramy dodány z portálu distributora.',
  4: 'Hlavní cíl: maximalizace vlastní spotřeby z FVE.',
  5: 'Prostor u trafostanice ideální, přístup pro jeřáb nutno ověřit.',
  6: 'Plná moc připravena, čeká na podpis jednatele.',
  7: 'Zákazník preferuje etapizaci: 1) studie → 2) realizace.',
  8: 'Kogenerace funguje jako doplněk, hlavní zdroj je FVE.',
})
