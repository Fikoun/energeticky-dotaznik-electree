<?php
/**
 * Raynet Custom Fields Management
 * 
 * Handles fetching, creating, and mapping custom fields for Raynet CRM entities.
 * Custom fields allow storing additional EnergyForms data in Raynet.
 */

namespace Raynet;

class RaynetCustomFields
{
    private RaynetApiClient $client;
    
    // Cache for custom field configurations
    private static array $configCache = [];
    private static ?int $cacheTime = null;
    private const CACHE_TTL = 300; // 5 minutes
    
    // Supported entity types
    public const ENTITY_COMPANY = 'Company';
    public const ENTITY_PERSON = 'Person';
    public const ENTITY_LEAD = 'Lead';
    
    // Data types supported by Raynet
    public const TYPE_STRING = 'STRING';
    public const TYPE_TEXT = 'TEXT';
    public const TYPE_DECIMAL = 'BIG_DECIMAL';
    public const TYPE_BOOLEAN = 'BOOLEAN';
    public const TYPE_DATE = 'DATE';
    public const TYPE_DATETIME = 'DATETIME';
    public const TYPE_ENUMERATION = 'ENUMERATION';
    public const TYPE_HYPERLINK = 'HYPERLINK';
    public const TYPE_MONETARY = 'MONETARY';
    public const TYPE_PERCENT = 'PERCENT';
    public const TYPE_FILE = 'FILE';
    
    // Group names for custom fields - displayed as sections in Raynet UI
    public const GROUP_COMPANY_INFO = 'EnergyForms - Společnost';
    public const GROUP_ENERGY_SOURCES = 'EnergyForms - Energetické zdroje';
    public const GROUP_CONSUMPTION = 'EnergyForms - Spotřeba';
    public const GROUP_SITE = 'EnergyForms - Lokalita';
    public const GROUP_TECHNICAL = 'EnergyForms - Technické údaje';
    public const GROUP_BILLING = 'EnergyForms - Fakturace';
    public const GROUP_METADATA = 'EnergyForms - Metadata';
    
    // =========================================================================
    // VALUE TRANSLATIONS (English keys → Czech labels for Raynet)
    // =========================================================================
    // Translates form field values from English keys to Czech display values
    // These are used for select/radio fields that store English keys
    public const VALUE_TRANSLATIONS = [
        // Boolean values
        'yes' => 'Ano',
        'no' => 'Ne',
        'true' => 'Ano',
        'false' => 'Ne',
        
        // Distribution territory
        'cez' => 'ČEZ Distribuce',
        'pre' => 'PRE Distribuce', 
        'egd' => 'E.GD Distribuce',
        'lds' => 'Lokální distribuční soustava',
        
        // Circuit breaker types
        'oil' => 'Olejový spínač',
        'vacuum' => 'Vakuový spínač',
        'SF6' => 'SF6 spínač',
        'other' => 'Jiný',
        'custom' => 'Vlastní specifikace',
        
        // Transformer voltage
        '22kV' => '22 kV',
        '35kV' => '35 kV', 
        '110kV' => '110 kV',
        
        // Transformer cooling
        'ONAN' => 'ONAN (přirozené chlazení)',
        'ONAF' => 'ONAF (nucené chlazení)',
        
        // Measurement type
        'quarter-hour' => 'Čtvrthodinové měření (A-měření)',
        'monthly' => 'Měsíční měření',
        'continuous' => 'Průběžné měření',
        
        // Energy accumulation
        'unknown' => 'Neví',
        'specific' => 'Konkrétní hodnota',
        
        // Battery cycles
        'once' => '1x denně',
        'multiple' => 'Vícekrát denně',
        'recommend' => 'Neznámo - doporučit',
        
        // Backup duration
        'minutes' => 'Desítky minut',
        'hours-1-3' => '1-3 hodiny',
        'hours-3-plus' => 'Více než 3 hodiny',
        'exact-time' => 'Přesně stanovená doba',
        
        // Accessibility
        'easy' => 'Snadná přístupnost',
        'moderate' => 'Středně obtížná',
        'difficult' => 'Obtížná přístupnost',
        
        // Billing method
        'fix' => 'Fixní cena',
        'spot' => 'Spotová cena',
        'gradual' => 'Postupná fixace',
        
        // Price importance
        'very-important' => 'Velmi důležité',
        'important' => 'Důležité',
        'not-important' => 'Není důležité',
        
        // Connection application - who submits
        'customer' => 'Zákazník sám',
        'customerbyelectree' => 'Zákazník prostřednictvím Electree',
        'electree' => 'Firma Electree na základě plné moci',
        'undecided' => 'Ještě nerozhodnuto',
        
        // Specialist position
        'specialist' => 'Specialista',
        'manager' => 'Správce',
        
        // Customer types 
        'industrial' => 'Průmysl',
        'commercial' => 'Komerční objekt',
        'services' => 'Služby / Logistika',
        'agriculture' => 'Zemědělství',
        'public' => 'Veřejný sektor',
        
        // Goals
        'energyIndependence' => 'Energetická nezávislost',
        'costSaving' => 'Úspora nákladů',
        'backupPower' => 'Záložní napájení',
        'peakShaving' => 'Peak shaving',
        'gridStabilization' => 'Stabilizace sítě',
        'environmentalBenefit' => 'Ekologický přínos',
        
        // Priorities
        'fve-overflow' => 'Úspora z přetoků z FVE',
        'peak-shaving' => 'Posun spotřeby (peak shaving)',
        'backup-power' => 'Záložní napájení',
        'grid-services' => 'Služby pro síť',
        'cost-optimization' => 'Optimalizace nákladů na elektřinu',
        'environmental' => 'Ekologický přínos',
        'machine-support' => 'Podpora výkonu strojů',
        'power-reduction' => 'Snížení rezervovaného příkonu',
        'energy-trading' => 'Možnost obchodování s energií',
        'subsidy' => 'Získání dotace',
        
        // Gas usage
        'heating' => 'Vytápění',
        'hot-water' => 'Ohřev teplé vody',
        'hotWater' => 'Ohřev teplé vody',
        'cooking' => 'Vaření',
        'production' => 'Výrobní procesy',
        'backup-heating' => 'Záložní vytápění',
        'technology' => 'Technologické procesy',
        
        // Time zones
        'nt' => 'NT (nízký tarif)',
        'vt' => 'VT (vysoký tarif)',
        'morning' => 'Ranní hodiny',
        'afternoon' => 'Odpolední hodiny',
        'evening' => 'Večerní hodiny',
        'night' => 'Noční hodiny',
        
        // Sizes
        'small' => 'Malá',
        'medium' => 'Střední',
        'large' => 'Velká',
        'extra-large' => 'Extra velká',
        
        // Space types
        'indoor' => 'Vnitřní',
        'outdoor' => 'Venkovní',
        'container' => 'Kontejner',
        'warehouse' => 'Sklad',
        'hall' => 'Hala',
        
        // Urgency
        'low' => 'Nízká',
        'normal' => 'Normální',
        'high' => 'Vysoká',
        'urgent' => 'Urgentní',
    ];
    
    // =========================================================================
    // INTELLIGENT AUTO-MAPPING CONFIGURATION
    // =========================================================================
    // Maps EnergyForms fields to Raynet native fields or custom fields
    // 
    // Mapping types:
    //   'native'  - Maps to built-in Raynet Company/Person field
    //   'address' - Maps to Company address/contactInfo structure
    //   'person'  - Maps to linked Person entity
    //   'custom'  - Requires custom field in Raynet (needs to be created first)
    //   'skip'    - Don't sync this field (handled elsewhere or not needed)
    // =========================================================================
    
    public const AUTO_MAPPING = [
        // =====================================================================
        // NATIVE RAYNET COMPANY FIELDS (no custom field needed)
        // =====================================================================
        'companyName' => [
            'target' => 'native',
            'raynetField' => 'name',
            'description' => 'Název společnosti → Company.name (povinné pole)',
        ],
        'ico' => [
            'target' => 'native',
            'raynetField' => 'regNumber',
            'description' => 'IČO → Company.regNumber (identifikace firmy)',
        ],
        'dic' => [
            'target' => 'native',
            'raynetField' => 'taxNumber',
            'description' => 'DIČ → Company.taxNumber',
        ],
        
        // =====================================================================
        // ADDRESS & CONTACT INFO (part of Company.addresses array)
        // =====================================================================
        'email' => [
            'target' => 'address',
            'raynetField' => 'contactInfo.email',
            'description' => 'E-mail → Company.addresses[0].contactInfo.email',
        ],
        'phone' => [
            'target' => 'address',
            'raynetField' => 'contactInfo.tel1',
            'extra' => ['tel1Type' => 'mobil'],
            'description' => 'Telefon → Company.addresses[0].contactInfo.tel1',
        ],
        'address' => [
            'target' => 'address',
            'raynetField' => 'address.street',
            'description' => 'Adresa odběrného místa → Company.addresses[0].address.street',
        ],
        'companyAddress' => [
            'target' => 'address',
            'raynetField' => 'address.fullAddress',
            'description' => 'Adresa společnosti → Company.addresses (hlavní adresa)',
            'isPrimary' => true,
        ],
        
        // =====================================================================
        // PERSON ENTITY (creates linked contact person)
        // =====================================================================
        'contactPerson' => [
            'target' => 'person',
            'raynetField' => 'fullName',
            'description' => 'Kontaktní osoba → Person.firstName + lastName',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - ENERGETICKÉ ZDROJE (⚡ FVE, VTE, kogenerace)
        // =====================================================================
        'hasFveVte' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_has_fve_vte',
            'description' => 'Má fotovoltaiku nebo větrnou elektrárnu',
        ],
        'fveVtePower' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_fve_vte_power',
            'description' => 'Instalovaný výkon FVE/VTE v kW',
        ],
        'accumulationPercentage' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_accumulation_pct',
            'description' => 'Procento energie směřující do akumulace',
        ],
        'interestedInFveVte' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_interest_fve',
            'description' => 'Zájem o instalaci FVE/VTE',
        ],
        'interestedInInstallationProcessing' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_interest_install',
            'description' => 'Zájem o zpracování instalace',
        ],
        'interestedInElectromobility' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_interest_emobility',
            'description' => 'Zájem o elektromobilitu a nabíjecí stanice',
        ],
        'energyAccumulation' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_accumulation_type',
            'description' => 'Typ akumulace energie (neví/konkrétní hodnota)',
        ],
        'energyAccumulationAmount' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_accumulation_kwh',
            'description' => 'Kapacita akumulace v kWh',
        ],
        'batteryCycles' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_battery_cycles',
            'description' => 'Požadovaný denní počet cyklů baterie',
        ],
        'hasGas' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_has_gas',
            'description' => 'Má připojení na plyn',
        ],
        'hasCogeneration' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_has_cogeneration',
            'description' => 'Má kogenerační jednotku',
        ],
        'cogenerationDetails' => [
            'target' => 'custom',
            'group' => self::GROUP_ENERGY_SOURCES,
            'suggestedName' => 'ef_cogeneration_info',
            'description' => 'Podrobnosti o kogeneraci',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - TECHNICKÉ ÚDAJE (🔧 transformátory, jističe, zálohy)
        // =====================================================================
        'hasTransformer' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_has_transformer',
            'description' => 'Má vlastní transformátor',
        ],
        'transformerPower' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_transformer_kva',
            'description' => 'Výkon transformátoru v kVA',
        ],
        'transformerVoltage' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_transformer_voltage',
            'description' => 'Napěťová hladina transformátoru',
        ],
        'transformerYear' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_transformer_year',
            'description' => 'Rok výroby transformátoru',
        ],
        'mainCircuitBreaker' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_main_breaker',
            'description' => 'Hlavní jistič (typ a hodnota)',
        ],
        'reservedPower' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_reserved_power',
            'description' => 'Rezervovaný příkon v kW',
        ],
        'reservedOutput' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_reserved_output',
            'description' => 'Rezervovaný výkon v kW',
        ],
        'requiresBackup' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_needs_backup',
            'description' => 'Vyžaduje záložní napájení',
        ],
        'backupDescription' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_backup_desc',
            'description' => 'Popis požadavků na zálohu',
        ],
        'backupDuration' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_backup_duration',
            'description' => 'Požadovaná doba zálohy',
        ],
        'gridConnectionPlanned' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_grid_planned',
            'description' => 'Plánované připojení k distribuční síti',
        ],
        'powerIncreaseRequested' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_power_increase',
            'description' => 'Žádost o navýšení příkonu',
        ],
        'requestedPowerIncrease' => [
            'target' => 'custom',
            'group' => self::GROUP_TECHNICAL,
            'suggestedName' => 'ef_power_increase_kw',
            'description' => 'Požadované navýšení příkonu v kW',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - SPOTŘEBA (📊 měření, profil spotřeby)
        // =====================================================================
        'monthlyConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_monthly_mwh',
            'description' => 'Měsíční spotřeba v MWh',
        ],
        'yearlyConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_yearly_mwh',
            'description' => 'Roční spotřeba v MWh',
        ],
        'dailyAverageConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_daily_avg_kwh',
            'description' => 'Průměrná denní spotřeba v kWh',
        ],
        'maxConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_max_consumption',
            'description' => 'Maximální špičková spotřeba',
        ],
        'minConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_min_consumption',
            'description' => 'Minimální spotřeba (základní zatížení)',
        ],
        'measurementType' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_measurement_type',
            'description' => 'Typ měření (A/B/C)',
        ],
        'hasCriticalConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_has_critical',
            'description' => 'Má kritickou spotřebu',
        ],
        'criticalConsumptionDescription' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_critical_desc',
            'description' => 'Popis kritické spotřeby',
        ],
        'gasConsumption' => [
            'target' => 'custom',
            'group' => self::GROUP_CONSUMPTION,
            'suggestedName' => 'ef_gas_consumption',
            'description' => 'Roční spotřeba plynu',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - LOKALITA (📍 prostory, přístupnost)
        // =====================================================================
        'siteDescription' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_site_desc',
            'description' => 'Popis lokality a prostředí',
        ],
        'hasOutdoorSpace' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_has_outdoor',
            'description' => 'Má venkovní prostor pro instalaci',
        ],
        'outdoorSpaceSize' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_outdoor_size',
            'description' => 'Velikost venkovního prostoru',
        ],
        'hasIndoorSpace' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_has_indoor',
            'description' => 'Má vnitřní prostor pro instalaci',
        ],
        'indoorSpaceSize' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_indoor_size',
            'description' => 'Velikost vnitřního prostoru',
        ],
        'accessibility' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_accessibility',
            'description' => 'Přístupnost lokality',
        ],
        'accessibilityLimitations' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_access_limits',
            'description' => 'Omezení přístupu',
        ],
        'infrastructureNotes' => [
            'target' => 'custom',
            'group' => self::GROUP_SITE,
            'suggestedName' => 'ef_infra_notes',
            'description' => 'Poznámky k infrastruktuře',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - FAKTURACE (💰 ceny, platby)
        // =====================================================================
        'billingMethod' => [
            'target' => 'custom',
            'group' => self::GROUP_BILLING,
            'suggestedName' => 'ef_billing_method',
            'description' => 'Způsob fakturace',
        ],
        'currentEnergyPrice' => [
            'target' => 'custom',
            'group' => self::GROUP_BILLING,
            'suggestedName' => 'ef_energy_price',
            'description' => 'Aktuální cena energie (Kč/kWh)',
        ],
        'priceImportance' => [
            'target' => 'custom',
            'group' => self::GROUP_BILLING,
            'suggestedName' => 'ef_price_priority',
            'description' => 'Důležitost ceny pro rozhodování',
        ],
        'priceOptimization' => [
            'target' => 'custom',
            'group' => self::GROUP_BILLING,
            'suggestedName' => 'ef_wants_optimization',
            'description' => 'Zájem o cenovou optimalizaci',
        ],
        
        // =====================================================================
        // CUSTOM FIELDS - METADATA (📋 info o formuláři, kontakty)
        // =====================================================================
        'goalDetails' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_goals',
            'description' => 'Cíle a očekávání zákazníka',
        ],
        'otherPurposeDescription' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_other_purpose',
            'description' => 'Jiný účel bateriového úložiště',
        ],
        'willingToSignPowerOfAttorney' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_poa_willing',
            'description' => 'Ochota podepsat plnou moc',
        ],
        'hasEnergeticSpecialist' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_has_specialist',
            'description' => 'Má vlastního energetického specialistu',
        ],
        'specialistName' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_specialist_name',
            'description' => 'Jméno energetického specialisty',
        ],
        'legislativeNotes' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_legal_notes',
            'description' => 'Legislativní poznámky',
        ],
        'additionalNotes' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_notes',
            'description' => 'Dodatečné poznámky',
        ],
        'energyNotes' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_energy_notes',
            'description' => 'Poznámky k energetice',
        ],
        'formSubmittedAt' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_submitted_at',
            'description' => 'Datum a čas odeslání formuláře',
        ],
        'formId' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_form_id',
            'description' => 'ID formuláře v EnergyForms',
        ],
        'formUrl' => [
            'target' => 'custom',
            'group' => self::GROUP_METADATA,
            'suggestedName' => 'ef_form_url',
            'description' => 'Odkaz na formulář v admin panelu',
        ],
    ];
    
    // EnergyForms field definitions for mapping
    // Maps EnergyForms field names to their labels, types, step, and group
    public const FORM_FIELDS = [
        // Step 1: Company info → GROUP_COMPANY_INFO
        'companyName' => ['label' => 'Název společnosti', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'ico' => ['label' => 'IČO', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'dic' => ['label' => 'DIČ', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'contactPerson' => ['label' => 'Kontaktní osoba', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'email' => ['label' => 'E-mail', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'phone' => ['label' => 'Telefon', 'type' => self::TYPE_STRING, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'address' => ['label' => 'Adresa odběrného místa', 'type' => self::TYPE_TEXT, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        'companyAddress' => ['label' => 'Adresa společnosti', 'type' => self::TYPE_TEXT, 'step' => 1, 'group' => self::GROUP_COMPANY_INFO],
        
        // Step 2: Energy sources & transformers → GROUP_ENERGY_SOURCES
        'hasFveVte' => ['label' => 'Má FVE/VTE', 'type' => self::TYPE_BOOLEAN, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'fveVtePower' => ['label' => 'Výkon FVE/VTE (kW)', 'type' => self::TYPE_DECIMAL, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'accumulationPercentage' => ['label' => 'Procento akumulace', 'type' => self::TYPE_PERCENT, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'interestedInFveVte' => ['label' => 'Zájem o FVE/VTE', 'type' => self::TYPE_BOOLEAN, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'interestedInInstallationProcessing' => ['label' => 'Zájem o zpracování instalace', 'type' => self::TYPE_BOOLEAN, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'interestedInElectromobility' => ['label' => 'Zájem o elektromobilitu', 'type' => self::TYPE_BOOLEAN, 'step' => 2, 'group' => self::GROUP_ENERGY_SOURCES],
        'hasTransformer' => ['label' => 'Má transformátor', 'type' => self::TYPE_BOOLEAN, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'transformerPower' => ['label' => 'Výkon transformátoru (kVA)', 'type' => self::TYPE_DECIMAL, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'transformerVoltage' => ['label' => 'Napětí transformátoru', 'type' => self::TYPE_STRING, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'transformerYear' => ['label' => 'Rok výroby transformátoru', 'type' => self::TYPE_STRING, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'mainCircuitBreaker' => ['label' => 'Hlavní jistič', 'type' => self::TYPE_STRING, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'reservedPower' => ['label' => 'Rezervovaný příkon', 'type' => self::TYPE_DECIMAL, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'reservedOutput' => ['label' => 'Rezervovaný výkon', 'type' => self::TYPE_DECIMAL, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'monthlyConsumption' => ['label' => 'Měsíční spotřeba', 'type' => self::TYPE_DECIMAL, 'step' => 2, 'group' => self::GROUP_CONSUMPTION],
        'circuitBreakerType' => ['label' => 'Typ jističe', 'type' => self::TYPE_STRING, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        'coolingType' => ['label' => 'Typ chlazení transformátoru', 'type' => self::TYPE_STRING, 'step' => 2, 'group' => self::GROUP_TECHNICAL],
        
        // Step 3: Consumption profile → GROUP_CONSUMPTION
        'yearlyConsumption' => ['label' => 'Roční spotřeba (MWh)', 'type' => self::TYPE_DECIMAL, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'dailyAverageConsumption' => ['label' => 'Denní průměrná spotřeba', 'type' => self::TYPE_DECIMAL, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'maxConsumption' => ['label' => 'Maximální spotřeba', 'type' => self::TYPE_DECIMAL, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'minConsumption' => ['label' => 'Minimální spotřeba', 'type' => self::TYPE_DECIMAL, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'measurementType' => ['label' => 'Typ měření', 'type' => self::TYPE_STRING, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'distributionTerritory' => ['label' => 'Distribuční území', 'type' => self::TYPE_STRING, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'hasCriticalConsumption' => ['label' => 'Kritická spotřeba', 'type' => self::TYPE_BOOLEAN, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'criticalConsumptionDescription' => ['label' => 'Popis kritické spotřeby', 'type' => self::TYPE_TEXT, 'step' => 3, 'group' => self::GROUP_CONSUMPTION],
        'energyAccumulation' => ['label' => 'Akumulace energie', 'type' => self::TYPE_STRING, 'step' => 3, 'group' => self::GROUP_ENERGY_SOURCES],
        'energyAccumulationAmount' => ['label' => 'Množství akumulace', 'type' => self::TYPE_DECIMAL, 'step' => 3, 'group' => self::GROUP_ENERGY_SOURCES],
        'batteryCycles' => ['label' => 'Cykly baterie', 'type' => self::TYPE_STRING, 'step' => 3, 'group' => self::GROUP_ENERGY_SOURCES],
        'requiresBackup' => ['label' => 'Vyžaduje zálohu', 'type' => self::TYPE_BOOLEAN, 'step' => 3, 'group' => self::GROUP_TECHNICAL],
        'backupDescription' => ['label' => 'Popis zálohy', 'type' => self::TYPE_TEXT, 'step' => 3, 'group' => self::GROUP_TECHNICAL],
        'backupDuration' => ['label' => 'Doba zálohy', 'type' => self::TYPE_STRING, 'step' => 3, 'group' => self::GROUP_TECHNICAL],
        'priceOptimization' => ['label' => 'Optimalizace ceny', 'type' => self::TYPE_BOOLEAN, 'step' => 3, 'group' => self::GROUP_BILLING],
        
        // Step 4: Goals & expectations → GROUP_METADATA
        'goalDetails' => ['label' => 'Detail cílů', 'type' => self::TYPE_TEXT, 'step' => 4, 'group' => self::GROUP_METADATA],
        'otherPurposeDescription' => ['label' => 'Jiný účel - popis', 'type' => self::TYPE_TEXT, 'step' => 4, 'group' => self::GROUP_METADATA],
        'priority1' => ['label' => 'Priorita 1', 'type' => self::TYPE_STRING, 'step' => 4, 'group' => self::GROUP_METADATA],
        'priority2' => ['label' => 'Priorita 2', 'type' => self::TYPE_STRING, 'step' => 4, 'group' => self::GROUP_METADATA],
        'priority3' => ['label' => 'Priorita 3', 'type' => self::TYPE_STRING, 'step' => 4, 'group' => self::GROUP_METADATA],
        
        // Step 5: Site & infrastructure → GROUP_SITE
        'siteDescription' => ['label' => 'Popis lokality', 'type' => self::TYPE_TEXT, 'step' => 5, 'group' => self::GROUP_SITE],
        'hasOutdoorSpace' => ['label' => 'Má venkovní prostor', 'type' => self::TYPE_BOOLEAN, 'step' => 5, 'group' => self::GROUP_SITE],
        'outdoorSpaceSize' => ['label' => 'Velikost venkovního prostoru', 'type' => self::TYPE_STRING, 'step' => 5, 'group' => self::GROUP_SITE],
        'hasIndoorSpace' => ['label' => 'Má vnitřní prostor', 'type' => self::TYPE_BOOLEAN, 'step' => 5, 'group' => self::GROUP_SITE],
        'indoorSpaceSize' => ['label' => 'Velikost vnitřního prostoru', 'type' => self::TYPE_STRING, 'step' => 5, 'group' => self::GROUP_SITE],
        'accessibility' => ['label' => 'Přístupnost', 'type' => self::TYPE_STRING, 'step' => 5, 'group' => self::GROUP_SITE],
        'accessibilityLimitations' => ['label' => 'Omezení přístupnosti', 'type' => self::TYPE_TEXT, 'step' => 5, 'group' => self::GROUP_SITE],
        'infrastructureNotes' => ['label' => 'Poznámky k infrastruktuře', 'type' => self::TYPE_TEXT, 'step' => 5, 'group' => self::GROUP_SITE],
        
        // Step 6: Legislative & technical → GROUP_TECHNICAL
        'gridConnectionPlanned' => ['label' => 'Plánované připojení k síti', 'type' => self::TYPE_BOOLEAN, 'step' => 6, 'group' => self::GROUP_TECHNICAL],
        'powerIncreaseRequested' => ['label' => 'Žádost o navýšení příkonu', 'type' => self::TYPE_BOOLEAN, 'step' => 6, 'group' => self::GROUP_TECHNICAL],
        'requestedPowerIncrease' => ['label' => 'Požadované navýšení příkonu', 'type' => self::TYPE_DECIMAL, 'step' => 6, 'group' => self::GROUP_TECHNICAL],
        'connectionApplicationBy' => ['label' => 'Kdo podá žádost o připojení', 'type' => self::TYPE_STRING, 'step' => 6, 'group' => self::GROUP_TECHNICAL],
        'willingToSignPowerOfAttorney' => ['label' => 'Ochoten podepsat plnou moc', 'type' => self::TYPE_BOOLEAN, 'step' => 6, 'group' => self::GROUP_METADATA],
        'hasEnergeticSpecialist' => ['label' => 'Má energetického specialistu', 'type' => self::TYPE_BOOLEAN, 'step' => 6, 'group' => self::GROUP_METADATA],
        'specialistName' => ['label' => 'Jméno specialisty', 'type' => self::TYPE_STRING, 'step' => 6, 'group' => self::GROUP_METADATA],
        'specialistPosition' => ['label' => 'Pozice specialisty', 'type' => self::TYPE_STRING, 'step' => 6, 'group' => self::GROUP_METADATA],
        'legislativeNotes' => ['label' => 'Legislativní poznámky', 'type' => self::TYPE_TEXT, 'step' => 6, 'group' => self::GROUP_METADATA],
        
        // Step 7: Additional info → GROUP_METADATA
        'additionalNotes' => ['label' => 'Dodatečné poznámky', 'type' => self::TYPE_TEXT, 'step' => 7, 'group' => self::GROUP_METADATA],
        'urgency' => ['label' => 'Naléhavost', 'type' => self::TYPE_STRING, 'step' => 7, 'group' => self::GROUP_METADATA],
        
        // Step 8: Billing & energy details → GROUP_BILLING
        'billingMethod' => ['label' => 'Způsob účtování', 'type' => self::TYPE_STRING, 'step' => 8, 'group' => self::GROUP_BILLING],
        'currentEnergyPrice' => ['label' => 'Aktuální cena energie', 'type' => self::TYPE_MONETARY, 'step' => 8, 'group' => self::GROUP_BILLING],
        'priceImportance' => ['label' => 'Důležitost ceny', 'type' => self::TYPE_STRING, 'step' => 8, 'group' => self::GROUP_BILLING],
        'hasGas' => ['label' => 'Má plyn', 'type' => self::TYPE_BOOLEAN, 'step' => 8, 'group' => self::GROUP_ENERGY_SOURCES],
        'gasConsumption' => ['label' => 'Spotřeba plynu', 'type' => self::TYPE_DECIMAL, 'step' => 8, 'group' => self::GROUP_CONSUMPTION],
        'hasCogeneration' => ['label' => 'Má kogeneraci', 'type' => self::TYPE_BOOLEAN, 'step' => 8, 'group' => self::GROUP_ENERGY_SOURCES],
        'cogenerationDetails' => ['label' => 'Detail kogenerace', 'type' => self::TYPE_TEXT, 'step' => 8, 'group' => self::GROUP_ENERGY_SOURCES],
        'energyNotes' => ['label' => 'Energetické poznámky', 'type' => self::TYPE_TEXT, 'step' => 8, 'group' => self::GROUP_METADATA],
        
        // Metadata → GROUP_METADATA
        'formSubmittedAt' => ['label' => 'Datum odeslání', 'type' => self::TYPE_DATETIME, 'step' => 0, 'group' => self::GROUP_METADATA],
        'formId' => ['label' => 'ID formuláře', 'type' => self::TYPE_STRING, 'step' => 0, 'group' => self::GROUP_METADATA],
        'formUrl' => ['label' => 'URL formuláře', 'type' => self::TYPE_HYPERLINK, 'step' => 0, 'group' => self::GROUP_METADATA],
    ];
    
    /**
     * Get the auto-mapping configuration
     */
    public static function getAutoMapping(): array
    {
        return self::AUTO_MAPPING;
    }
    
    /**
     * Get auto-mapping grouped by target type
     */
    public static function getAutoMappingByTarget(): array
    {
        $grouped = [
            'native' => [],
            'address' => [],
            'person' => [],
            'custom' => [],
            'skip' => [],
        ];
        
        foreach (self::AUTO_MAPPING as $formField => $config) {
            $target = $config['target'] ?? 'custom';
            $grouped[$target][$formField] = $config;
        }
        
        return $grouped;
    }
    
    /**
     * Get custom fields grouped by their Raynet group
     */
    public static function getCustomFieldsByGroup(): array
    {
        $grouped = [];
        
        foreach (self::AUTO_MAPPING as $formField => $config) {
            if ($config['target'] !== 'custom') {
                continue;
            }
            
            $group = $config['group'] ?? self::GROUP_METADATA;
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            
            // Merge with FORM_FIELDS to get type info
            $fieldDef = self::FORM_FIELDS[$formField] ?? [];
            $grouped[$group][$formField] = array_merge($config, [
                'label' => $fieldDef['label'] ?? $formField,
                'type' => $fieldDef['type'] ?? self::TYPE_STRING,
                'step' => $fieldDef['step'] ?? 0,
            ]);
        }
        
        return $grouped;
    }
    
    /**
     * Generate the default mapping for database storage
     * This creates a mapping that can be saved to settings table
     */
    public static function generateDefaultMapping(): array
    {
        $mapping = [];
        
        foreach (self::AUTO_MAPPING as $formField => $config) {
            if ($config['target'] === 'custom') {
                // For custom fields, use suggested name (will be replaced with actual Raynet field name after creation)
                $mapping[$formField] = $config['suggestedName'] ?? "ef_{$formField}";
            }
            // Native, address, and person fields are handled directly in sync logic
        }
        
        return $mapping;
    }
    
    /**
     * Get all defined group names
     */
    public static function getGroups(): array
    {
        return [
            self::GROUP_COMPANY_INFO => 'Základní údaje o společnosti',
            self::GROUP_ENERGY_SOURCES => 'Energetické zdroje (FVE, VTE, kogenerace)',
            self::GROUP_CONSUMPTION => 'Spotřeba a měření',
            self::GROUP_SITE => 'Lokalita a infrastruktura',
            self::GROUP_TECHNICAL => 'Technické údaje',
            self::GROUP_BILLING => 'Fakturace a ceny',
            self::GROUP_METADATA => 'Metadata formuláře',
        ];
    }
    
    /**
     * Get fields grouped by their group name
     */
    public static function getFieldsByGroup(): array
    {
        $grouped = [];
        foreach (self::FORM_FIELDS as $fieldName => $config) {
            $group = $config['group'] ?? self::GROUP_METADATA;
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$fieldName] = $config;
        }
        return $grouped;
    }
    
    public function __construct(RaynetApiClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Get all custom field configurations for all entities
     * 
     * @param bool $forceRefresh Force refresh from API
     * @return array Custom field configurations grouped by entity
     */
    public function getConfig(bool $forceRefresh = false): array
    {
        // Check cache
        if (!$forceRefresh && !empty(self::$configCache) && self::$cacheTime !== null) {
            if (time() - self::$cacheTime < self::CACHE_TTL) {
                return self::$configCache;
            }
        }
        
        $response = $this->client->get('/customField/config/');
        
        if ($response && isset($response['data'])) {
            self::$configCache = $response['data'];
            self::$cacheTime = time();
            return self::$configCache;
        }
        
        return [];
    }
    
    /**
     * Get custom fields for a specific entity type
     * 
     * @param string $entityType Entity type (Company, Person, Lead)
     * @return array Array of custom field configurations
     */
    public function getFieldsForEntity(string $entityType): array
    {
        $config = $this->getConfig();
        return $config[$entityType] ?? [];
    }
    
    /**
     * Get company custom fields
     */
    public function getCompanyFields(): array
    {
        return $this->getFieldsForEntity(self::ENTITY_COMPANY);
    }
    
    /**
     * Get person custom fields
     */
    public function getPersonFields(): array
    {
        return $this->getFieldsForEntity(self::ENTITY_PERSON);
    }
    
    /**
     * Get enum values for a specific custom field
     * 
     * @param string $entityType Entity type
     * @param string $fieldName Field name (e.g., 'priority_xyz99')
     * @return array List of allowed values
     */
    public function getEnumValues(string $entityType, string $fieldName): array
    {
        $response = $this->client->get("/customField/enum/{$entityType}/{$fieldName}/");
        
        if ($response && isset($response['data'])) {
            return $response['data'];
        }
        
        return [];
    }
    
    /**
     * Create a new custom field on Raynet
     * 
     * @param string $entityType Entity type (Company, Person, Lead)
     * @param array $fieldConfig Field configuration
     * @return array Created field info with generated field name
     */
    public function createField(string $entityType, array $fieldConfig): array
    {
        // Validate required fields
        if (empty($fieldConfig['label']) || strlen($fieldConfig['label']) < 3) {
            throw new RaynetException('Field label must be at least 3 characters');
        }
        
        if (empty($fieldConfig['groupName']) || strlen($fieldConfig['groupName']) < 3) {
            throw new RaynetException('Group name must be at least 3 characters');
        }
        
        if (empty($fieldConfig['dataType'])) {
            throw new RaynetException('Data type is required');
        }
        
        $data = [
            'label' => $fieldConfig['label'],
            'groupName' => $fieldConfig['groupName'],
            'dataType' => $fieldConfig['dataType'],
            'showInListView' => $fieldConfig['showInListView'] ?? false,
            'showInFilterView' => $fieldConfig['showInFilterView'] ?? false,
        ];
        
        // Add description if provided
        if (!empty($fieldConfig['description'])) {
            $data['description'] = $fieldConfig['description'];
        }
        
        // For enumeration type, add allowed values
        if ($fieldConfig['dataType'] === self::TYPE_ENUMERATION && !empty($fieldConfig['enumeration'])) {
            $data['enumeration'] = $fieldConfig['enumeration'];
        }
        
        error_log("createField: Creating field '{$fieldConfig['label']}' with type {$fieldConfig['dataType']} in group {$fieldConfig['groupName']}");
        
        try {
            $response = $this->client->put("/customField/config/{$entityType}/", $data);
        } catch (\Exception $e) {
            error_log("createField: API call failed: " . $e->getMessage());
            throw new RaynetException('API call failed: ' . $e->getMessage());
        }
        
        // Clear cache after creating new field
        self::$configCache = [];
        self::$cacheTime = null;
        
        if ($response && isset($response['data'])) {
            error_log("createField: Success, got fieldName: " . ($response['data']['fieldName'] ?? $response['data']['name'] ?? 'unknown'));
            return [
                'success' => true,
                'fieldName' => $response['data']['fieldName'] ?? $response['data']['name'] ?? null,
                'data' => $response['data']
            ];
        }
        
        error_log("createField: Failed, response: " . json_encode($response));
        throw new RaynetException('Failed to create custom field - no data in response');
    }
    
    /**
     * Delete a custom field from Raynet
     * 
     * @param string $entityType Entity type (Company, Person, etc.)
     * @param string $fieldName The technical name of the field to delete (e.g., 'cf_Rocni_spotreba_abcd1234')
     * @return bool True if deletion was successful
     * @throws RaynetException If deletion fails
     */
    public function deleteField(string $entityType, string $fieldName): bool
    {
        if (empty($fieldName)) {
            throw new RaynetException('Field name is required for deletion');
        }
        
        if (empty($entityType)) {
            throw new RaynetException('Entity type is required for deletion');
        }
        
        error_log("deleteField: Deleting field '{$fieldName}' from entity '{$entityType}'");
        
        try {
            $this->client->delete("/customField/config/{$entityType}/{$fieldName}/");
        } catch (\Exception $e) {
            error_log("deleteField: API call failed: " . $e->getMessage());
            throw new RaynetException('Failed to delete field: ' . $e->getMessage());
        }
        
        // Clear cache after deleting field
        self::$configCache = [];
        self::$cacheTime = null;
        
        error_log("deleteField: Successfully deleted field '{$fieldName}'");
        return true;
    }
    
    /**
     * Create multiple custom fields for EnergyForms mapping
     * 
     * @param string $entityType Entity type
     * @param array $formFields List of EnergyForms field names to create
     * @param string|null $groupName Optional override group name (uses field's default group if null)
     * @return array Results of field creation
     */
    public function createFieldsFromFormMapping(
        string $entityType,
        array $formFields,
        ?string $groupName = null
    ): array {
        $results = [
            'created' => [],
            'errors' => []
        ];
        
        foreach ($formFields as $formField) {
            if (!isset(self::FORM_FIELDS[$formField])) {
                $results['errors'][] = [
                    'field' => $formField,
                    'error' => 'Unknown form field'
                ];
                continue;
            }
            
            $fieldDef = self::FORM_FIELDS[$formField];
            
            // Use provided group name or field's default group
            $fieldGroup = $groupName ?? $fieldDef['group'] ?? self::GROUP_METADATA;
            
            try {
                // MONETARY type can be problematic - use DECIMAL instead
                $dataType = $fieldDef['type'];
                if ($dataType === self::TYPE_MONETARY) {
                    error_log("createFieldsFromFormMapping: Converting MONETARY to DECIMAL for field {$formField}");
                    $dataType = self::TYPE_DECIMAL;
                }
                
                $result = $this->createField($entityType, [
                    'label' => $fieldDef['label'],
                    'groupName' => $fieldGroup,
                    'dataType' => $dataType,
                    'description' => "EnergyForms: {$formField}",
                    'showInListView' => true,
                    'showInFilterView' => true,
                ]);
                
                $results['created'][] = [
                    'formField' => $formField,
                    'raynetField' => $result['fieldName'],
                    'label' => $fieldDef['label'],
                    'group' => $fieldGroup
                ];
                
                error_log("createFieldsFromFormMapping: Created field {$formField} => {$result['fieldName']}");
            } catch (RaynetException $e) {
                error_log("createFieldsFromFormMapping: FAILED to create {$formField}: " . $e->getMessage());
                $results['errors'][] = [
                    'field' => $formField,
                    'label' => $fieldDef['label'],
                    'error' => $e->getMessage()
                ];
            }
            
            // Small delay to respect rate limits
            usleep(100000); // 100ms
        }
        
        return $results;
    }
    
    /**
     * Build custom fields object for entity creation/update
     * 
     * @param array $formData Form data from EnergyForms
     * @param array $mapping Mapping configuration (formField => raynetFieldName)
     * @return array Custom fields object for Raynet API
     */
    public function buildCustomFieldsPayload(array $formData, array $mapping): array
    {
        $customFields = [];
        
        error_log("buildCustomFieldsPayload: Processing " . count($mapping) . " field mappings");
        error_log("buildCustomFieldsPayload: Form data keys: " . implode(', ', array_keys($formData)));
        
        foreach ($mapping as $formField => $raynetField) {
            if (!isset($formData[$formField])) {
                continue;
            }
            
            $value = $formData[$formField];
            
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Get field type for proper formatting
            $fieldType = self::FORM_FIELDS[$formField]['type'] ?? self::TYPE_STRING;
            
            // Format value based on type
            $formattedValue = $this->formatValue($value, $fieldType);
            
            if ($formattedValue !== null) {
                $customFields[$raynetField] = $formattedValue;
                error_log("buildCustomFieldsPayload: Mapped {$formField} => {$raynetField} = " . json_encode($formattedValue));
            }
        }
        
        error_log("buildCustomFieldsPayload: Built " . count($customFields) . " custom fields");
        
        return $customFields;
    }
    
    /**
     * Format a value for the appropriate Raynet field type
     */
    private function formatValue($value, string $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case self::TYPE_DECIMAL:
            case self::TYPE_MONETARY:
            case self::TYPE_PERCENT:
                return is_numeric($value) ? (float) $value : null;
                
            case self::TYPE_DATE:
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d');
                }
                return is_string($value) ? date('Y-m-d', strtotime($value)) : null;
                
            case self::TYPE_DATETIME:
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d H:i');
                }
                return is_string($value) ? date('Y-m-d H:i', strtotime($value)) : null;
                
            case self::TYPE_TEXT:
            case self::TYPE_STRING:
            case self::TYPE_ENUMERATION:
                // Translate value if it's a known key
                $stringValue = is_string($value) ? $value : (string) $value;
                return $this->translateValue($stringValue);
                
            case self::TYPE_HYPERLINK:
            default:
                return is_string($value) ? $value : (string) $value;
        }
    }
    
    /**
     * Translate a value from English key to Czech label
     * If the value contains multiple items (array or comma-separated), translates each
     * 
     * @param mixed $value The value to translate
     * @return string The translated value or original if no translation found
     */
    private function translateValue($value): string
    {
        // Handle arrays (e.g., multi-select fields)
        if (is_array($value)) {
            $translated = [];
            foreach ($value as $key => $val) {
                // For associative arrays like {heating: true, cooking: true}
                if (is_string($key) && ($val === true || $val === '1' || $val === 1)) {
                    $translated[] = self::VALUE_TRANSLATIONS[$key] ?? $key;
                }
                // For indexed arrays like ['heating', 'cooking']
                elseif (is_string($val)) {
                    $translated[] = self::VALUE_TRANSLATIONS[$val] ?? $val;
                }
            }
            return implode(', ', $translated);
        }
        
        // Handle string values
        if (is_string($value)) {
            // Check for direct translation
            if (isset(self::VALUE_TRANSLATIONS[$value])) {
                return self::VALUE_TRANSLATIONS[$value];
            }
            
            // Check case-insensitive
            $lowerValue = strtolower($value);
            if (isset(self::VALUE_TRANSLATIONS[$lowerValue])) {
                return self::VALUE_TRANSLATIONS[$lowerValue];
            }
            
            // No translation found, return original
            return $value;
        }
        
        return (string) $value;
    }
    
    /**
     * Get available form fields grouped by step
     */
    public function getFormFieldsByStep(): array
    {
        $byStep = [];
        
        foreach (self::FORM_FIELDS as $fieldName => $fieldDef) {
            $step = $fieldDef['step'];
            if (!isset($byStep[$step])) {
                $byStep[$step] = [];
            }
            $byStep[$step][$fieldName] = $fieldDef;
        }
        
        ksort($byStep);
        return $byStep;
    }
    
    /**
     * Get all available form fields
     */
    public function getFormFields(): array
    {
        return self::FORM_FIELDS;
    }
    
    /**
     * Get data type options for UI
     */
    public function getDataTypeOptions(): array
    {
        return [
            self::TYPE_STRING => 'Text (krátký)',
            self::TYPE_TEXT => 'Text (dlouhý)',
            self::TYPE_DECIMAL => 'Číslo',
            self::TYPE_BOOLEAN => 'Ano/Ne',
            self::TYPE_DATE => 'Datum',
            self::TYPE_DATETIME => 'Datum a čas',
            self::TYPE_ENUMERATION => 'Výběr z možností',
            self::TYPE_HYPERLINK => 'Odkaz',
            self::TYPE_MONETARY => 'Peněžní částka',
            self::TYPE_PERCENT => 'Procento',
        ];
    }
    
    /**
     * Auto-detect mapping from existing Raynet fields by matching labels
     * 
     * This method fetches existing Company custom fields from Raynet and
     * attempts to match them to EnergyForms fields based on the label.
     * It builds a reverse lookup from AUTO_MAPPING labels to form field names.
     * 
     * @return array ['mapping' => [...], 'matched' => [...], 'unmatched' => [...]]
     */
    public function detectMappingFromExistingFields(): array
    {
        // Build label -> formField lookup from AUTO_MAPPING
        $labelToFormField = [];
        foreach (self::AUTO_MAPPING as $formField => $config) {
            if ($config['target'] !== 'custom') {
                continue; // Only care about custom fields
            }
            
            // Get the expected label for this field
            $expectedLabel = self::FORM_FIELDS[$formField]['label'] ?? null;
            if ($expectedLabel) {
                // Normalize for comparison (lowercase, no extra spaces)
                $normalizedLabel = mb_strtolower(trim($expectedLabel));
                $labelToFormField[$normalizedLabel] = $formField;
            }
        }
        
        // Fetch existing fields from Raynet
        $raynetFields = $this->getCompanyFields();
        
        $mapping = [];
        $matched = [];
        $unmatched = [];
        
        foreach ($raynetFields as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldLabel = $field['label'] ?? null;
            $groupName = $field['groupName'] ?? null;
            
            if (!$fieldName || !$fieldLabel) {
                continue;
            }
            
            // Only consider EnergyForms groups
            if ($groupName && strpos($groupName, 'EnergyForms') === false) {
                continue;
            }
            
            // Try to match by label
            $normalizedLabel = mb_strtolower(trim($fieldLabel));
            
            if (isset($labelToFormField[$normalizedLabel])) {
                $formField = $labelToFormField[$normalizedLabel];
                $mapping[$formField] = $fieldName;
                $matched[] = [
                    'formField' => $formField,
                    'raynetField' => $fieldName,
                    'label' => $fieldLabel,
                    'groupName' => $groupName
                ];
            } else {
                $unmatched[] = [
                    'raynetField' => $fieldName,
                    'label' => $fieldLabel,
                    'groupName' => $groupName
                ];
            }
        }
        
        error_log("detectMappingFromExistingFields: Matched " . count($matched) . " fields, unmatched: " . count($unmatched));
        
        return [
            'mapping' => $mapping,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'totalRaynetFields' => count($raynetFields),
            'totalFormFields' => count($labelToFormField)
        ];
    }
}
