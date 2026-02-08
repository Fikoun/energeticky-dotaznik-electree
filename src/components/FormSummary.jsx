import { useState } from 'react'
import { useFormContext } from 'react-hook-form'
import { Eye, X, User, FileText, Building, Zap, Target, Battery, DollarSign, MessageSquare, HelpCircle, Sun, Home, StickyNote } from 'lucide-react'

const FormSummary = ({ user, stepNotes, stepNames }) => {
  const [isOpen, setIsOpen] = useState(false)
  const { watch } = useFormContext()
  const formData = watch()

  // Helper function to format values for display
  const formatValue = (value, defaultText = 'Neuvedeno') => {
    if (value === null || value === undefined || value === '' || value === false) {
      return defaultText
    }
    if (typeof value === 'boolean') {
      return value ? 'Ano' : 'Ne'
    }
    
    // Special handling for FileList objects
    if (value instanceof FileList) {
      if (value.length === 0) {
        return defaultText
      }
      const fileNames = Array.from(value).map(file => file.name)
      return fileNames.join(', ')
    }
    
    // Special handling for File objects  
    if (value instanceof File) {
      return value.name
    }
    
    // Handle arrays and array-like values
    if (Array.isArray(value)) {
      const meaningfulItems = value
        .map(item => {
          if (item === null || item === undefined || item === '' || item === false) {
            return null
          }
          if (typeof item === 'string' || typeof item === 'number') {
            return String(item)
          }
          if (typeof item === 'object' && item?.name) {
            return item.name
          }
          return null
        })
        .filter(Boolean)

      return meaningfulItems.length > 0 ? meaningfulItems.join(', ') : defaultText
    }

    // Avoid rendering empty plain objects
    if (typeof value === 'object') {
      const hasContent = Object.values(value).some(v => v !== null && v !== '' && v !== false && v !== undefined)
      if (!hasContent) return defaultText
    }
    return String(value)
  }

  const renderBadgeList = (items, fallbackText = 'Nevybráno') => {
    if (!items || items.length === 0) {
      return <span className="text-gray-500">{fallbackText}</span>
    }

    return (
      <div className="flex flex-wrap gap-2">
        {items.map((item, index) => {
          const label = typeof item === 'string' ? item : item.label
          const icon = typeof item === 'string' ? null : item.icon
          return (
            <span
              key={`${label}-${index}`}
              className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-800 text-xs font-medium"
            >
              {icon && <span aria-hidden>{icon}</span>}
              <span>{label}</span>
            </span>
          )
        })}
      </div>
    )
  }

  // Helper function to format uploaded files from server
  const formatUploadedFiles = (fieldName, defaultText = 'Žádné soubory') => {
    // First try to get uploaded files from server response
    const uploadedFiles = formData.uploadedFiles?.[fieldName]
    
    if (uploadedFiles) {
      if (Array.isArray(uploadedFiles)) {
        return uploadedFiles.map(f => f.originalName || f.name).join(', ')
      } else {
        return uploadedFiles.originalName || uploadedFiles.name
      }
    }
    
    // If no server files, try to get from form data (immediate uploads)
    const formFiles = formData[fieldName]
    
    if (formFiles && Array.isArray(formFiles) && formFiles.length > 0) {
      // Check if these are uploaded file objects
      if (formFiles[0]?.uploaded) {
        return formFiles.map(f => f.name).join(', ')
      }
    }
    
    return defaultText
  }

  // Helper function to render step note if it exists
  const renderStepNote = (stepNumber) => {
    // Použijeme stepNumber přímo jako klíč (ne step${stepNumber})
    const note = stepNotes?.[stepNumber]
    
    // Debug log
    console.log(`Debug - Krok ${stepNumber}:`, {
      stepNotes: stepNotes,
      noteForStep: note,
      hasNote: !!note && note.trim() !== ''
    })
    
    // Mapování čísel kroků na jejich názvy
    const stepNameMap = {
      1: 'Identifikační údaje zákazníka',
      2: 'Parametry odběrného místa',
      3: 'Energetické potřeby',
      4: 'Cíle a očekávání',
      5: 'Infrastruktura a prostor',
      6: 'Provozní a legislativní rámec',
      7: 'Navržený postup a poznámky',
      8: 'Energetický dotazník'
    }
    
    const stepName = stepNameMap[stepNumber] || `Krok ${stepNumber}`
    
    if (!note || note.trim() === '') {
      return null
    }
    
    return (
      <div className="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3">
        <div className="flex items-center gap-2 mb-2">
          <StickyNote className="h-4 w-4 text-amber-600" />
          <span className="font-medium text-amber-800">{stepName} - poznámka</span>
        </div>
        <div className="text-sm text-amber-700">
          {note}
        </div>
      </div>
    )
  }

  // Helper function to format radio and select values to Czech
  const formatCzechValue = (value, fieldType) => {
    if (!value || value === '') return 'Neuvedeno'
    
    const translations = {
      // FormStep2 - Parametry odběrného místa
      hasFveVte: { 'yes': 'Ano', 'no': 'Ne' },
      interestedInFveVte: { 'yes': 'Ano', 'no': 'Ne' },
      interestedInInstallationProcessing: { 'yes': 'Ano', 'no': 'Ne' },
      interestedInElectromobility: { 'yes': 'Ano', 'no': 'Ne' },
      hasTransformer: { 'yes': 'Ano', 'no': 'Ne' },
      transformerVoltage: { '22kV': '22kV', '35kV': '35kV', '110kV': '110kV', 'other': 'Jiné' },
      coolingType: { 'ONAN': 'ONAN', 'ONAF': 'ONAF', 'other': 'Jiné' },
      circuitBreakerType: { 'SF6': 'SF6 spínač', 'vacuum': 'Vakuový spínač', 'oil': 'Olejový spínač', 'other': 'Jiný typ', 'custom': 'Vlastní specifikace' },
      
      // FormStep3 - Energetické potřeby
      hasDistributionCurves: { 'yes': 'Ano', 'no': 'Ne' },
      distributionTerritory: { 'cez': 'ČEZ', 'pre': 'PRE', 'egd': 'E.GD', 'lds': 'LDS' },
      measurementType: { 'quarter-hour': 'Čtvrthodinové měření (A-měření)', 'other': 'Jiné' },
      hasCriticalConsumption: { 'yes': 'Ano', 'no': 'Ne' },
      energyAccumulation: { 'unknown': 'Neví', 'specific': 'Konkrétní hodnota' },
      batteryCycles: { 'once': '1x denně', 'multiple': 'Vícekrát denně', 'recommend': 'Neznámo - doporučit' },
      requiresBackup: { 'yes': 'Ano', 'no': 'Ne' },
      backupDuration: { 'minutes': 'Desítky minut', 'hours-1-3': '1-3 hodiny', 'hours-3-plus': 'Více než 3 hodiny' },
      priceOptimization: { 'yes': 'Ano', 'no': 'Ne', 'unsure': 'Nejsem si jistý' },
      hasElectricityProblems: { 'yes': 'Ano', 'no': 'Ne' },
      hasEnergyAudit: { 'yes': 'Ano', 'no': 'Ne' },
      hasOwnEnergySource: { 'yes': 'Ano', 'no': 'Ne' },
      canProvideLoadSchema: { 'yes': 'Ano', 'no': 'Ne' },
      
      // FormStep5 - Infrastruktura
      hasOutdoorSpace: { 'yes': 'Ano', 'no': 'Ne' },
      hasIndoorSpace: { 'yes': 'Ano', 'no': 'Ne' },
      accessibility: { 'unlimited': 'Bez omezení', 'limited': 'Omezený' },
      hasProjectDocumentation: { 'yes': 'Ano', 'no': 'Ne' },
      
      // FormStep6 - Legislativní rámec
      gridConnectionPlanned: { 'yes': 'Ano', 'no': 'Ne' },
      powerIncreaseRequested: { 'yes': 'Ano', 'no': 'Ne' },
      connectionApplicationBy: { 'customer': 'Zákazník sám', 'electree': 'Firma Electree na základě plné moci', 'undecided': 'Ještě nerozhodnuto' },
      willingToSignPowerOfAttorney: { 'yes': 'Ano', 'no': 'Ne' },
      hasEnergeticSpecialist: { 'yes': 'Ano', 'no': 'Ne' },
      specialistPosition: { 'specialist': 'Specialista', 'manager': 'Správce' },
      
      // FormStep8 - Energetický dotazník
      billingMethod: { 'spot': 'Spotová cena', 'fix': 'Fixní cena', 'combined': 'Kombinace fix/spot', 'gradual': 'Postupná fixace' },
      priceImportance: { 
        'very-important': 'Velmi důležitá', 
        'important': 'Důležitá', 
        'neutral': 'Neutrální', 
        'less-important': 'Méně důležitá', 
        'not-important': 'Nedůležitá' 
      },
      electricitySharing: { 'yes': 'Ano', 'no': 'Ne' },
      hasGas: { 'yes': 'Ano', 'no': 'Ne' },
      hasCogeneration: { 'yes': 'Ano', 'no': 'Ne' }
    }
    
    return translations[fieldType]?.[value] || formatValue(value)
  }

  // Get selected customer types from FormStep1
  const getSelectedCustomerTypes = () => {
    if (!formData.customerType) return []
    
    const typeLabels = {
      industrial: { label: 'Průmysl', icon: '🏭' },
      commercial: { label: 'Komerční objekt', icon: '🏢' },
      services: { label: 'Služby / Logistika', icon: '🚚' },
      agriculture: { label: 'Zemědělství', icon: '🌾' },
      public: { label: 'Veřejný sektor', icon: '🏛️' }
    }

    const selected = Object.entries(typeLabels)
      .filter(([key]) => formData.customerType?.[key])
      .map(([, meta]) => meta)

    if (formData.customerType.other && formData.customerType.otherSpecification) {
      selected.push({ label: formData.customerType.otherSpecification, icon: '❓' })
    } else if (formData.customerType.other) {
      selected.push({ label: 'Jiný typ', icon: '❓' })
    }

    return selected
  }

  // Get selected goals from FormStep4
  const getSelectedGoals = () => {
    if (!formData.goals) return []
    
    const goalLabels = {
      fveOverflow: { label: 'Úspora z přetoků z FVE', icon: '☀️' },
      peakShaving: { label: 'Posun spotřeby (peak shaving)', icon: '📉' },
      backupPower: { label: 'Záloha při výpadku sítě', icon: '🛡️' },
      machineSupport: { label: 'Podpora výkonu strojů', icon: '⚙️' },
      powerReduction: { label: 'Snížení rezervovaného příkonu', icon: '🔌' },
      energyTrading: { label: 'Možnost obchodování s energií', icon: '💱' },
      subsidy: { label: 'Získání dotace', icon: '🎯' },
      other: { label: 'Jiný účel', icon: '❓' }
    }

    const selected = Object.entries(goalLabels)
      .filter(([key]) => formData.goals?.[key])
      .map(([, meta]) => meta)

    if (formData.goals?.other && formData.otherPurposeDescription) {
      selected.push({ label: formData.otherPurposeDescription, icon: '📝' })
    }

    return selected
  }

  // Get priorities from FormStep4
  const getPriorities = () => {
    const priorityLabels = {
      'fve-overflow': 'Úspora z přetoků z FVE',
      'peak-shaving': 'Posun spotřeby (peak shaving)',
      'backup-power': 'Záloha při výpadku sítě',
      'machine-support': 'Podpora výkonu strojů',
      'power-reduction': 'Snížení rezervovaného příkonu',
      'energy-trading': 'Možnost obchodování s energií',
      subsidy: 'Získání dotace',
      other: 'Jiný účel'
    }
    
    return {
      priority1: priorityLabels[formData.priority1] || 'Neuvedeno',
      priority2: priorityLabels[formData.priority2] || 'Neuvedeno', 
      priority3: priorityLabels[formData.priority3] || 'Neuvedeno'
    }
  }

  // Get documentation types from FormStep5
  const getSelectedDocumentationTypes = () => {
    if (!formData.documentationTypes) return 'Nevybráno'
    
    const types = []
    if (formData.documentationTypes.sitePlan) types.push('Situační plán areálu')
    if (formData.documentationTypes.electricalPlan) types.push('Elektrická dokumentace')
    if (formData.documentationTypes.buildingPlan) types.push('Půdorysy budov')
    if (formData.documentationTypes.other) types.push('Jiná dokumentace')
    
    return types.length > 0 ? types.join(', ') : 'Nevybráno'
  }

  // Get proposed steps from FormStep7
  const getProposedSteps = () => {
    const steps = formData.proposedSteps || {}
    const selectedSteps = []
    
    const stepLabels = {
      preliminary: 'Předběžná nabídka',
      technical: 'Technická prohlídka', 
      detailed: 'Příprava zakázky a připojení',
      consultancy: 'Konzultace s energetikem',
      support: 'Možnost obchodování s energií',
      other: 'Jiný postup'
    }
    
    Object.keys(steps).forEach(key => {
      if (steps[key] && stepLabels[key]) {
        selectedSteps.push(stepLabels[key])
      }
    })
    
    return selectedSteps.length > 0 ? selectedSteps.join(', ') : 'Nevybráno'
  }

  if (!isOpen) {
    return (
      <button
        type="button"
        onClick={() => setIsOpen(true)}
        className="btn-secondary flex items-center gap-2"
      >
        <Eye className="h-4 w-4" />
        Zobrazit souhrn
      </button>
    )
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-xl font-bold text-gray-900">Souhrn formuláře</h2>
          <button
            onClick={() => setIsOpen(false)}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="h-5 w-5 text-gray-500" />
          </button>
        </div>
        
        <div className="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
          <div className="space-y-6">
            {/* Informace o uživateli */}
            {user && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="flex items-center gap-2 mb-3">
                  <User className="h-5 w-5 text-blue-600" />
                  <span className="font-semibold text-blue-900">Vyplněno uživatelem</span>
                </div>
                <div className="bg-white rounded p-3">
                  <div className="text-sm text-blue-600">Jméno a příjmení</div>
                  <div className="font-medium text-blue-900">{user.fullName || user.name}</div>
                  {user.email && (
                    <div className="text-sm text-blue-600 mt-1">Email: {user.email}</div>
                  )}
                </div>
              </div>
            )}

            {/* Krok 1 - Identifikační údaje zákazníka */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Building className="h-5 w-5 mr-2 text-blue-600" />
                1. Identifikační údaje zákazníka
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span className="font-medium">Název společnosti/jméno:</span> {formatValue(formData.companyName)}</div>
                <div><span className="font-medium">IČO:</span> {formatValue(formData.ico)}</div>
                <div><span className="font-medium">DIČ:</span> {formatValue(formData.dic)}</div>
                <div><span className="font-medium">Kontaktní osoba:</span> {formatValue(formData.contactPerson)}</div>
                <div><span className="font-medium">Telefon:</span> {formatValue(formData.phone)}</div>
                <div><span className="font-medium">Email:</span> {formatValue(formData.email)}</div>
                <div className="md:col-span-2"><span className="font-medium">Adresa sídla firmy:</span> {formatValue(formData.companyAddress)}</div>
                <div className="md:col-span-2"><span className="font-medium">Adresa odběrného místa:</span> {formatValue(formData.address)}</div>
                <div><span className="font-medium">Stejná adresa jako sídlo:</span> {formatValue(formData.sameAsCompanyAddress, 'Ne')}</div>
                <div className="md:col-span-2">
                  <span className="font-medium">Typ zákazníka:</span>
                  <div className="mt-1">{renderBadgeList(getSelectedCustomerTypes(), 'Nevybráno')}</div>
                </div>
                {formData.customerType?.other && formData.customerType?.otherSpecification && (
                  <div className="md:col-span-2"><span className="font-medium">Upřesnění typu:</span> {formatValue(formData.customerType.otherSpecification)}</div>
                )}
              </div>

              {/* Další kontakty */}
              {formData.additionalContacts && formData.additionalContacts.length > 0 && (
                <div className="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                  <h5 className="font-semibold text-gray-800 mb-3 flex items-center">
                    <User className="w-5 h-5 mr-2 text-gray-600" />
                    Další kontakty
                  </h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {formData.additionalContacts.map((contact, index) => (
                      <div key={index} className="bg-white p-3 rounded border border-gray-200">
                        <div className="text-sm">
                          <div className="font-medium text-gray-800 mb-1">
                            {formatValue(contact.name)}
                            {contact.isPrimary && <span className="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">Primární kontakt</span>}
                          </div>
                          {contact.position && (
                            <div className="text-gray-600 mb-1">Pozice: {formatValue(contact.position)}</div>
                          )}
                          {contact.phone && (
                            <div className="text-gray-600">Tel: {formatValue(contact.phone)}</div>
                          )}
                          {contact.email && (
                            <div className="text-gray-600">Email: {formatValue(contact.email)}</div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Detailní informace o společnosti z MERK */}
              {formData.companyDetails && (
                <div className="mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                  <h5 className="font-semibold text-gray-800 mb-4 flex items-center">
                    <Building className="w-5 h-5 mr-2 text-blue-600" />
                    Detailní informace o společnosti z MERK databáze
                  </h5>
                  
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 text-sm">
                    {/* Základní údaje */}
                    <div className="bg-white p-3 rounded shadow-sm">
                      <h6 className="font-medium text-gray-700 mb-2">Základní údaje</h6>
                      <div className="space-y-1">
                        {formData.companyDetails.legal_form && (
                          <div><span className="font-medium">Právní forma:</span> {formData.companyDetails.legal_form}</div>
                        )}
                        {formData.companyDetails.status && (
                          <div><span className="font-medium">Stav:</span> {formData.companyDetails.status}</div>
                        )}
                        <div><span className="font-medium">Plátce DPH:</span> {formData.companyDetails.is_vatpayer ? 'Ano' : 'Ne'}</div>
                        {formData.companyDetails.estab_date && (
                          <div><span className="font-medium">Datum založení:</span> {new Date(formData.companyDetails.estab_date).toLocaleDateString('cs-CZ')}</div>
                        )}
                      </div>
                    </div>

                    {/* Podnikání */}
                    <div className="bg-white p-3 rounded shadow-sm">
                      <h6 className="font-medium text-gray-700 mb-2">Podnikání</h6>
                      <div className="space-y-1">
                        {formData.companyDetails.industry && (
                          <div><span className="font-medium">Hlavní činnost:</span> {formData.companyDetails.industry}</div>
                        )}
                        {formData.companyDetails.magnitude && (
                          <div><span className="font-medium">Velikost:</span> {formData.companyDetails.magnitude}</div>
                        )}
                        {formData.companyDetails.turnover && (
                          <div><span className="font-medium">Obrat:</span> {formData.companyDetails.turnover}</div>
                        )}
                        {formData.companyDetails.years_in_business && (
                          <div><span className="font-medium">Doba podnikání:</span> {formData.companyDetails.years_in_business} let</div>
                        )}
                      </div>
                    </div>

                    {/* Úřední údaje */}
                    <div className="bg-white p-3 rounded shadow-sm">
                      <h6 className="font-medium text-gray-700 mb-2">Úřední údaje</h6>
                      <div className="space-y-1">
                        {formData.companyDetails.court && (
                          <div><span className="font-medium">Registrační soud:</span> {formData.companyDetails.court}</div>
                        )}
                        {formData.companyDetails.court_file && (
                          <div><span className="font-medium">Spisová značka:</span> {formData.companyDetails.court_file}</div>
                        )}
                        {formData.companyDetails.databox_id && (
                          <div><span className="font-medium">Datová schránka:</span> {formData.companyDetails.databox_id}</div>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Rozšířené informace z raw_data pokud jsou dostupné */}
                  {formData.companyDetails.raw_data && (
                    <div className="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                      {/* Kontaktní údaje */}
                      {(formData.companyDetails.raw_data.emails || formData.companyDetails.raw_data.phones || formData.companyDetails.raw_data.webs) && (
                        <div className="bg-white p-3 rounded shadow-sm">
                          <h6 className="font-medium text-gray-700 mb-2">Kontaktní údaje</h6>
                          <div className="space-y-1">
                            {formData.companyDetails.raw_data.emails && formData.companyDetails.raw_data.emails.length > 0 && (
                              <div>
                                <span className="font-medium">Emaily:</span>
                                <div className="ml-2">
                                  {formData.companyDetails.raw_data.emails.slice(0, 3).map((emailData, index) => {
                                    const emailAddress = typeof emailData === 'string' ? emailData : emailData.email || emailData.address || '';
                                    return emailAddress ? <div key={index} className="text-blue-600">{emailAddress}</div> : null;
                                  })}
                                </div>
                              </div>
                            )}
                            {formData.companyDetails.raw_data.phones && formData.companyDetails.raw_data.phones.length > 0 && (
                              <div>
                                <span className="font-medium">Telefony:</span>
                                <div className="ml-2">
                                  {formData.companyDetails.raw_data.phones.slice(0, 3).map((phoneData, index) => {
                                    const phoneNumber = typeof phoneData === 'string' ? phoneData : phoneData.phone || phoneData.number || '';
                                    return phoneNumber ? <div key={index} className="text-blue-600">{phoneNumber}</div> : null;
                                  })}
                                </div>
                              </div>
                            )}
                            {formData.companyDetails.raw_data.webs && formData.companyDetails.raw_data.webs.length > 0 && (
                              <div>
                                <span className="font-medium">Webové stránky:</span>
                                <div className="ml-2">
                                  {formData.companyDetails.raw_data.webs.slice(0, 2).map((web, index) => (
                                    <div key={index} className="text-blue-600">{web.url}</div>
                                  ))}
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      )}

                      {/* Finanční údaje */}
                      {(formData.companyDetails.raw_data.profit || formData.companyDetails.raw_data.company_index || formData.companyDetails.raw_data.subsidies) && (
                        <div className="bg-white p-3 rounded shadow-sm">
                          <h6 className="font-medium text-gray-700 mb-2">Finanční údaje</h6>
                          <div className="space-y-1">
                            {formData.companyDetails.raw_data.profit && formData.companyDetails.raw_data.profit.amount && (
                              <div><span className="font-medium">Zisk ({formData.companyDetails.raw_data.profit.year}):</span> {new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' }).format(formData.companyDetails.raw_data.profit.amount)}</div>
                            )}
                            {formData.companyDetails.raw_data.company_index && (
                              <div><span className="font-medium">Index společnosti:</span> {formData.companyDetails.raw_data.company_index.value}/100</div>
                            )}
                            {formData.companyDetails.raw_data.subsidies && formData.companyDetails.raw_data.subsidies.total_amount && (
                              <div><span className="font-medium">Dotace celkem:</span> {new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' }).format(formData.companyDetails.raw_data.subsidies.total_amount)}</div>
                            )}
                          </div>
                        </div>
                      )}

                      {/* Právní informace */}
                      {(formData.companyDetails.raw_data.insolvency || formData.companyDetails.raw_data.execution || formData.companyDetails.raw_data.court_cases) && (
                        <div className="bg-white p-3 rounded shadow-sm lg:col-span-2">
                          <h6 className="font-medium text-gray-700 mb-2">Právní informace</h6>
                          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            {formData.companyDetails.raw_data.insolvency && (
                              <div>
                                <span className="font-medium">Insolvenční řízení:</span>
                                <span className={`ml-2 font-semibold ${formData.companyDetails.raw_data.insolvency.is_insolvent ? 'text-red-600' : 'text-green-600'}`}>
                                  {formData.companyDetails.raw_data.insolvency.is_insolvent ? 'Ano' : 'Ne'}
                                </span>
                              </div>
                            )}
                            {formData.companyDetails.raw_data.execution && (
                              <div>
                                <span className="font-medium">Exekuce:</span>
                                <span className={`ml-2 font-semibold ${formData.companyDetails.raw_data.execution.has_execution ? 'text-red-600' : 'text-green-600'}`}>
                                  {formData.companyDetails.raw_data.execution.has_execution ? 'Ano' : 'Ne'}
                                </span>
                              </div>
                            )}
                            {formData.companyDetails.raw_data.court_cases && formData.companyDetails.raw_data.court_cases.total > 0 && (
                              <div>
                                <span className="font-medium">Soudní spory:</span>
                                <span className="ml-2 font-semibold">{formData.companyDetails.raw_data.court_cases.total}</span>
                              </div>
                            )}
                          </div>
                        </div>
                      )}

                      {/* Statutární orgány */}
                      {formData.companyDetails.raw_data.body && formData.companyDetails.raw_data.body.persons && formData.companyDetails.raw_data.body.persons.length > 0 && (
                        <div className="bg-white p-3 rounded shadow-sm lg:col-span-2">
                          <h6 className="font-medium text-gray-700 mb-2">Vedení společnosti</h6>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {formData.companyDetails.raw_data.body.persons.slice(0, 4).map((person, index) => (
                              <div key={index} className="border rounded p-2 bg-gray-50">
                                <div className="font-medium">
                                  {person.degree_before && `${person.degree_before} `}
                                  {person.first_name} {person.last_name}
                                  {person.degree_after && `, ${person.degree_after}`}
                                </div>
                                <div className="text-xs text-gray-600">{person.company_role}</div>
                                {person.age && <div className="text-xs text-gray-500">Věk: {person.age} let</div>}
                              </div>
                            ))}
                          </div>
                          {formData.companyDetails.raw_data.body.persons.length > 4 && (
                            <div className="text-xs text-gray-500 mt-2">
                              ... a {formData.companyDetails.raw_data.body.persons.length - 4} dalších osob
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}

              {renderStepNote(1)}
            </div>

            {/* Krok 2 - Parametry odběrného místa */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Sun className="h-5 w-5 mr-2 text-orange-600" />
                2. Parametry odběrného místa
              </h4>
              <div className="space-y-4 text-sm">
                {/* FVE/VTE sekce */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">FVE/VTE instalace:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Má instalovanou FVE/VTE:</span> {formatCzechValue(formData.hasFveVte, 'hasFveVte')}</div>
                    {formData.hasFveVte === 'yes' && (
                      <>
                        <div><span className="font-medium">Výkon FVE:</span> {formatValue(formData.fveVtePower)} kWp</div>
                        <div><span className="font-medium">Akumulace přetoků:</span> {formatValue(formData.accumulationPercentage)} %</div>
                      </>
                    )}
                    {formData.hasFveVte === 'no' && (
                      <>
                        <div><span className="font-medium">Zájem o instalaci FVE:</span> {formatCzechValue(formData.interestedInFveVte, 'interestedInFveVte')}</div>
                        {formData.interestedInFveVte === 'yes' && (
                          <div><span className="font-medium">Zájem o zpracování instalace:</span> {formatCzechValue(formData.interestedInInstallationProcessing, 'interestedInInstallationProcessing')}</div>
                        )}
                      </>
                    )}
                  </div>
                </div>

                {/* Elektromobilita */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Elektromobilita:</h5>
                  <div><span className="font-medium">Zájem o elektromobilitu:</span> {formatCzechValue(formData.interestedInElectromobility, 'interestedInElectromobility')}</div>
                </div>

                {/* Trafo a technické údaje */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Transformátor:</h5>
                  <div><span className="font-medium">Má transformátor:</span> {formatCzechValue(formData.hasTransformer, 'hasTransformer')}</div>
                  {formData.hasTransformer === 'yes' && (
                    <div className="mt-2 ml-4 space-y-2">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><span className="font-medium">Výkon transformátoru:</span> {formatValue(formData.transformerPower)} kVA</div>
                        <div><span className="font-medium">Napětí:</span> {formatValue(formData.transformerVoltage)}</div>
                        <div><span className="font-medium">Chlazení:</span> {formatValue(formData.coolingType)}</div>
                        <div><span className="font-medium">Rok výroby:</span> {formatValue(formData.transformerYear)}</div>
                        <div><span className="font-medium">Typ transformátoru:</span> {formatValue(formData.transformerType)}</div>
                        <div><span className="font-medium">Proud na VN:</span> {formatValue(formData.transformerCurrent)} A</div>
                      </div>
                    </div>
                  )}
                </div>

                {/* Jistič a sdílení */}
                <div>
                  <h5 className="font-medium mb-2">Jistič a další údaje:</h5>
                  <div className="space-y-2">
                    <div><span className="font-medium">Typ jističe VN:</span> {formatValue(formData.circuitBreakerType)}</div>
                    {formData.circuitBreakerType === 'custom' && formData.customCircuitBreaker && (
                      <div className="ml-4"><span className="font-medium">Vlastní specifikace:</span> {formatValue(formData.customCircuitBreaker)}</div>
                    )}
                    <div><span className="font-medium">Sdílení elektřiny s jinými subjekty:</span> {formatValue(formData.sharesElectricity, 'Ne')}</div>
                  </div>
                </div>

                {/* Technické parametry */}
                <div>
                  <h5 className="font-medium mb-2">Technické parametry:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Hlavní jistič:</span> {formatValue(formData.mainCircuitBreaker)} A</div>
                    <div><span className="font-medium">Rezervovaný příkon:</span> {formatValue(formData.reservedPower)} kW</div>
                    <div><span className="font-medium">Měsíční spotřeba:</span> {formatValue(formData.monthlyConsumption)} MWh</div>
                    <div><span className="font-medium">Měsíční maximum odběru:</span> {formatValue(formData.monthlyMaxConsumption)} kW</div>
                    <div className="md:col-span-2"><span className="font-medium">Významné odběry:</span> {formatValue(formData.significantConsumption)}</div>
                  </div>
                </div>
              </div>
              {renderStepNote(2)}
            </div>

            {/* Krok 3 - Energetické potřeby */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Zap className="h-5 w-5 mr-2 text-yellow-600" />
                3. Energetické potřeby
              </h4>
              <div className="space-y-3 text-sm">
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Obecné údaje:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Odběrové diagramy:</span> {formatCzechValue(formData.hasDistributionCurves, 'hasDistributionCurves')}</div>
                    <div><span className="font-medium">Distribuční území:</span> {formatCzechValue(formData.distributionTerritory, 'distributionTerritory')}</div>
                    {formData.distributionTerritory === 'lds' && (
                      <div className="md:col-span-2 ml-4 space-y-1">
                        <div><span className="font-medium">Název LDS:</span> {formatValue(formData.ldsName)}</div>
                        {formData.ldsOwner && (
                          <div><span className="font-medium">Vlastník LDS:</span> {formatValue(formData.ldsOwner)}</div>
                        )}
                        {formData.ldsNotes && (
                          <div><span className="font-medium">Poznámky k LDS:</span> {formatValue(formData.ldsNotes)}</div>
                        )}
                      </div>
                    )}
                    {formData.hasDistributionCurves === 'yes' && formData.distributionCurvesFile && (
                      <div className="md:col-span-2"><span className="font-medium">Soubor s diagramy:</span> {formatUploadedFiles('distributionCurvesFile') || formatValue(formData.distributionCurvesFile)}</div>
                    )}
                    <div><span className="font-medium">Typ měření:</span> {formatCzechValue(formData.measurementType, 'measurementType')}</div>
                    {formData.measurementType === 'other' && formData.measurementTypeOther && (
                      <div className="md:col-span-2"><span className="font-medium">Jiné měření:</span> {formatValue(formData.measurementTypeOther)}</div>
                    )}
                  </div>
                </div>
                
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Energetické parametry:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Roční spotřeba:</span> {formatValue(formData.yearlyConsumption)} MWh</div>
                    <div><span className="font-medium">Denní spotřeba:</span> {formatValue(formData.dailyAverageConsumption)} kWh</div>
                    <div><span className="font-medium">Max. odběr:</span> {formatValue(formData.maxConsumption)} kW</div>
                    <div><span className="font-medium">Min. odběr:</span> {formatValue(formData.minConsumption)} kW</div>
                  </div>
                </div>
                
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Kritické spotřeby:</h5>
                  <div><span className="font-medium">Kritická spotřeba:</span> {formatCzechValue(formData.hasCriticalConsumption, 'hasCriticalConsumption')}</div>
                  {formData.hasCriticalConsumption === 'yes' && formData.criticalConsumptionDescription && (
                    <div className="mt-2"><span className="font-medium">Popis kritických spotřeb:</span> {formatValue(formData.criticalConsumptionDescription)}</div>
                  )}
                </div>
                
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Akumulace a cykly:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Akumulace energie:</span> {formatCzechValue(formData.energyAccumulation, 'energyAccumulation')}</div>
                    {formData.energyAccumulation === 'specific' && formData.energyAccumulationAmount && (
                      <div><span className="font-medium">Konkrétní hodnota:</span> {formatValue(formData.energyAccumulationAmount)} kWh</div>
                    )}
                    <div><span className="font-medium">Cykly baterie:</span> {formatCzechValue(formData.batteryCycles, 'batteryCycles')}</div>
                  </div>
                </div>
                
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Zálohování:</h5>
                  <div><span className="font-medium">Požadavek na zálohování:</span> {formatCzechValue(formData.requiresBackup, 'requiresBackup')}</div>
                  {formData.requiresBackup === 'yes' && formData.backupDescription && (
                    <div className="mt-2"><span className="font-medium">Co zálohovat:</span> {formatValue(formData.backupDescription)}</div>
                  )}
                  <div className="mt-2"><span className="font-medium">Výdrž zálohy:</span> {formatCzechValue(formData.backupDuration, 'backupDuration')}</div>
                  <div className="mt-2"><span className="font-medium">Řízení podle ceny:</span> {formatCzechValue(formData.priceOptimization, 'priceOptimization')}</div>
                </div>
                
                <div>
                  <h5 className="font-medium mb-2">Doplňující technické otázky:</h5>
                  <div className="space-y-2">
                    <div><span className="font-medium">Problémy s výpadky elektřiny:</span> {formatCzechValue(formData.hasElectricityProblems, 'hasElectricityProblems')}</div>
                    {formData.hasElectricityProblems === 'yes' && formData.electricityProblemsDetails && (
                      <div className="ml-4"><span className="font-medium">Detaily výpadků:</span> {formatValue(formData.electricityProblemsDetails)}</div>
                    )}
                    
                    <div><span className="font-medium">Energetický audit:</span> {formatCzechValue(formData.hasEnergyAudit, 'hasEnergyAudit')}</div>
                    {formData.hasEnergyAudit === 'yes' && formData.energyAuditDetails && (
                      <div className="ml-4"><span className="font-medium">Detaily auditu:</span> {formatValue(formData.energyAuditDetails)}</div>
                    )}
                    
                    <div><span className="font-medium">Vlastní výrobní zdroj:</span> {formatCzechValue(formData.hasOwnEnergySource, 'hasOwnEnergySource')}</div>
                    {formData.hasOwnEnergySource === 'yes' && formData.ownEnergySourceDetails && (
                      <div className="ml-4"><span className="font-medium">Detaily zdroje:</span> {formatValue(formData.ownEnergySourceDetails)}</div>
                    )}
                    
                    <div><span className="font-medium">Může zaslat schéma zatížení:</span> {formatCzechValue(formData.canProvideLoadSchema, 'canProvideLoadSchema')}</div>
                    {formData.canProvideLoadSchema === 'yes' && formData.loadSchemaDetails && (
                      <div className="ml-4"><span className="font-medium">Detaily schématu:</span> {formatValue(formData.loadSchemaDetails)}</div>
                    )}
                  </div>
                </div>
              </div>
              {renderStepNote(3)}
            </div>

            {/* Krok 4 - Cíle a očekávání */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Target className="h-5 w-5 mr-2 text-purple-600" />
                4. Cíle a očekávání
              </h4>
              <div className="space-y-3 text-sm">
                <div>
                  <span className="font-medium">Vybrané cíle:</span>
                  <div className="mt-1">{renderBadgeList(getSelectedGoals(), 'Nevybráno')}</div>
                </div>
                {formData.goals?.other && formData.otherPurposeDescription && (
                  <div><span className="font-medium">Jiný účel:</span> {formatValue(formData.otherPurposeDescription)}</div>
                )}
                <div><span className="font-medium">Doplňující informace:</span> {formatValue(formData.goalDetails)}</div>
                <div className="border-t pt-3">
                  <h5 className="font-medium mb-2">Priorita cílů:</h5>
                  <div>1. {getPriorities().priority1}</div>
                  <div>2. {getPriorities().priority2}</div>
                  <div>3. {getPriorities().priority3}</div>
                </div>
              </div>
              {renderStepNote(4)}
            </div>

            {/* Krok 5 - Infrastruktura a prostor */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Home className="h-5 w-5 mr-2 text-green-600" />
                5. Infrastruktura a prostor
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><span className="font-medium">Venkovní prostor:</span> {formatCzechValue(formData.hasOutdoorSpace, 'hasOutdoorSpace')}</div>
                {formData.hasOutdoorSpace === 'yes' && (
                  <div><span className="font-medium">Velikost venkovního prostoru:</span> {formatValue(formData.outdoorSpaceSize)} m²</div>
                )}
                <div><span className="font-medium">Vnitřní prostor:</span> {formatCzechValue(formData.hasIndoorSpace, 'hasIndoorSpace')}</div>
                {formData.hasIndoorSpace === 'yes' && (
                  <>
                    <div><span className="font-medium">Typ prostoru:</span> {formatValue(formData.indoorSpaceType)}</div>
                    <div><span className="font-medium">Velikost vnitřního prostoru:</span> {formatValue(formData.indoorSpaceSize)} m²</div>
                  </>
                )}
                <div><span className="font-medium">Přístupnost:</span> {formatCzechValue(formData.accessibility, 'accessibility')}</div>
                {formData.accessibility === 'limited' && (
                  <div className="md:col-span-2"><span className="font-medium">Omezení přístupnosti:</span> {formatValue(formData.accessibilityLimitations)}</div>
                )}
                <div><span className="font-medium">Projektová dokumentace:</span> {formatCzechValue(formData.hasProjectDocumentation, 'hasProjectDocumentation')}</div>
                {formData.hasProjectDocumentation === 'yes' && (
                  <>
                    <div className="md:col-span-2"><span className="font-medium">Typ dokumentace:</span> {getSelectedDocumentationTypes()}</div>
                    {formData.projectDocumentationFiles && (
                      <div className="md:col-span-2"><span className="font-medium">Nahrané soubory:</span> {formatUploadedFiles('projectDocumentationFiles') || formatValue(formData.projectDocumentationFiles)}</div>
                    )}
                  </>
                )}

                {/* Soubory a popisy */}
                {formData.sitePhotos && (
                  <div className="md:col-span-2"><span className="font-medium">Fotografie místa:</span> {formatUploadedFiles('sitePhotos') || formatValue(formData.sitePhotos)}</div>
                )}
                {formData.visualizations && (
                  <div className="md:col-span-2"><span className="font-medium">Vizualizace/nákresy:</span> {formatUploadedFiles('visualizations') || formatValue(formData.visualizations)}</div>
                )}
                {formData.siteDescription && (
                  <div className="md:col-span-2"><span className="font-medium">Popis místa instalace:</span> {formatValue(formData.siteDescription)}</div>
                )}
                {formData.infrastructureNotes && (
                  <div className="md:col-span-2"><span className="font-medium">Poznámky k infrastruktuře:</span> {formatValue(formData.infrastructureNotes)}</div>
                )}
                
                <div className="md:col-span-2"><span className="font-medium">Doplňující informace:</span> {formatValue(formData.additionalInfrastructureInfo)}</div>
              </div>
              {renderStepNote(5)}
            </div>

            {/* Krok 6 - Provozní a legislativní rámec */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <FileText className="h-5 w-5 mr-2 text-indigo-600" />
                6. Provozní a legislativní rámec
              </h4>
              <div className="space-y-3 text-sm">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div><span className="font-medium">Připojení k DS/ČEPS:</span> {formatCzechValue(formData.gridConnectionPlanned, 'gridConnectionPlanned')}</div>
                  {formData.gridConnectionPlanned === 'yes' && (
                    <>
                      <div><span className="font-medium">Navýšení rezervovaného příkonu:</span> {formatCzechValue(formData.powerIncreaseRequested, 'powerIncreaseRequested')}</div>
                      {formData.powerIncreaseRequested === 'yes' && (
                        <>
                          <div><span className="font-medium">Požadované navýšení příkonu:</span> {formatValue(formData.requestedPowerIncrease)} kW</div>
                          <div><span className="font-medium">Požadované navýšení výkonu:</span> {formatValue(formData.requestedOutputIncrease)} kW</div>
                        </>
                      )}
                    </>
                  )}
                  <div><span className="font-medium">Žádost o připojení podá:</span> {formatCzechValue(formData.connectionApplicationBy, 'connectionApplicationBy')}</div>
                  <div><span className="font-medium">Ochota podepsat plnou moc:</span> {formatCzechValue(formData.willingToSignPowerOfAttorney, 'willingToSignPowerOfAttorney')}</div>
                  <div><span className="font-medium">Má energetického specialistu:</span> {formatCzechValue(formData.hasEnergeticSpecialist, 'hasEnergeticSpecialist')}</div>
                </div>

                {/* Soubory */}
                {(formData.connectionContractFile || formData.connectionApplicationFile) && (
                  <div className="border-t pt-3">
                    <h5 className="font-medium mb-2">Dokumenty připojení:</h5>
                    <div className="space-y-1">
                      {formData.connectionContractFile && (
                        <div><span className="font-medium">Smlouva o připojení:</span> {formatValue(formData.connectionContractFile)}</div>
                      )}
                      {formData.connectionApplicationFile && (
                        <div><span className="font-medium">Žádost o připojení:</span> {formatValue(formData.connectionApplicationFile)}</div>
                      )}
                    </div>
                  </div>
                )}
                
                {formData.hasEnergeticSpecialist === 'yes' && (
                  <div className="border-t pt-3">
                    <h5 className="font-medium mb-2">Údaje o specialistovi:</h5>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div><span className="font-medium">Jméno:</span> {formatValue(formData.specialistName)}</div>
                      <div><span className="font-medium">Pozice:</span> {formatCzechValue(formData.specialistPosition, 'specialistPosition')}</div>
                      <div><span className="font-medium">Telefon:</span> {formatValue(formData.specialistPhone)}</div>
                      <div><span className="font-medium">Email:</span> {formatValue(formData.specialistEmail)}</div>
                    </div>
                  </div>
                )}

                {formData.legislativeNotes && (
                  <div className="border-t pt-3">
                    <div><span className="font-medium">Poznámky k legislativnímu rámci:</span> {formatValue(formData.legislativeNotes)}</div>
                  </div>
                )}
              </div>
              {renderStepNote(6)}
            </div>

            {/* Krok 7 - Navržený postup a poznámky */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <MessageSquare className="h-5 w-5 mr-2 text-orange-600" />
                7. Navržený postup a poznámky
              </h4>
              <div className="space-y-3 text-sm">
                <div><span className="font-medium">Navržené kroky:</span> {getProposedSteps()}</div>
                {formData.proposedSteps?.other && formData.proposedSteps?.otherDescription && (
                  <div><span className="font-medium">Jiný postup:</span> {formatValue(formData.proposedSteps.otherDescription)}</div>
                )}
                <div><span className="font-medium">Dodatečné poznámky:</span> {formatValue(formData.additionalNotes)}</div>
                
                <div className="border-t pt-3">
                  <h5 className="font-medium mb-2">Souhlasy:</h5>
                  <div className="space-y-1">
                    <div><span className="font-medium">Zpracování osobních údajů:</span> {formatValue(formData.agreements?.dataProcessing, 'Ne')}</div>
                    <div><span className="font-medium">Návštěva technika:</span> {formatValue(formData.agreements?.technicalVisit, 'Ne')}</div>
                    <div><span className="font-medium">Obchodní sdělení:</span> {formatValue(formData.agreements?.marketing, 'Ne')}</div>
                  </div>
                </div>
              </div>
              {renderStepNote(7)}
            </div>

            {/* Krok 8 - Energetický dotazník */}
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <h4 className="font-semibold text-gray-800 mb-3 flex items-center">
                <Battery className="h-5 w-5 mr-2 text-emerald-600" />
                8. Energetický dotazník
              </h4>
              <div className="space-y-4 text-sm">
                {/* Ceník elektřiny */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Ceník elektřiny:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Ceník elektřiny VT:</span> {formatValue(formData.electricityPriceVT)} Kč/kWh</div>
                    <div><span className="font-medium">Ceník elektřiny NT:</span> {formatValue(formData.electricityPriceNT)} Kč/kWh</div>
                    <div><span className="font-medium">Cena za distribuci VT:</span> {formatValue(formData.distributionPriceVT)} Kč/kWh</div>
                    <div><span className="font-medium">Cena za distribuci NT:</span> {formatValue(formData.distributionPriceNT)} Kč/kWh</div>
                    <div><span className="font-medium">Systémové služby:</span> {formatValue(formData.systemServices)} Kč/kWh</div>
                    <div><span className="font-medium">OTE:</span> {formatValue(formData.ote)} Kč/kWh</div>
                    <div><span className="font-medium">Poplatky za vyúčtování:</span> {formatValue(formData.billingFees)} Kč/měsíc</div>
                    <div><span className="font-medium">Způsob vyúčtování:</span> {formatCzechValue(formData.billingMethod, 'billingMethod')}</div>
                  </div>
                  
                  {/* Detaily vyúčtování podle typu */}
                  {formData.billingMethod === 'spot' && formData.spotSurcharge && (
                    <div className="mt-3 ml-4">
                      <div><span className="font-medium">Přirážka na spot cenu:</span> {formatValue(formData.spotSurcharge)} Kč/MWh</div>
                    </div>
                  )}
                  
                  {formData.billingMethod === 'fix' && formData.fixPrice && (
                    <div className="mt-3 ml-4">
                      <div><span className="font-medium">Fixní cena elektřiny:</span> {formatValue(formData.fixPrice)} Kč/kWh</div>
                    </div>
                  )}
                  
                  {formData.billingMethod === 'combined' && (
                    <div className="mt-3 ml-4 space-y-1">
                      {formData.fixPercentage && (
                        <div><span className="font-medium">Podíl fix (%):</span> {formatValue(formData.fixPercentage)} %</div>
                      )}
                      {formData.spotPercentage && (
                        <div><span className="font-medium">Podíl spot (%):</span> {formatValue(formData.spotPercentage)} %</div>
                      )}
                    </div>
                  )}
                  
                  {formData.billingMethod === 'gradual' && (
                    <div className="mt-3 ml-4 space-y-1">
                      {formData.gradualFixPrice && (
                        <div><span className="font-medium">Postupná fixní cena:</span> {formatValue(formData.gradualFixPrice)} Kč/kWh</div>
                      )}
                      {formData.gradualSpotSurcharge && (
                        <div><span className="font-medium">Postupná spot přirážka:</span> {formatValue(formData.gradualSpotSurcharge)} Kč/MWh</div>
                      )}
                    </div>
                  )}
                  
                  {formData.billingDocuments && (
                    <div className="mt-3"><span className="font-medium">Doklady o vyúčtování:</span> {formatUploadedFiles('billingDocuments') || formatValue(formData.billingDocuments)}</div>
                  )}
                </div>
                
                {/* Současná cena elektřiny */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Současná cena elektřiny:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {formData.currentEnergyPrice && (
                      <div><span className="font-medium">Aktuální cena elektřiny:</span> {formatValue(formData.currentEnergyPrice)} Kč/kWh</div>
                    )}
                    <div><span className="font-medium">Důležitost ceny elektřiny:</span> {formatCzechValue(formData.priceImportance, 'priceImportance')}</div>
                  </div>
                </div>
                
                {/* Sdílení energie */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Sdílení energie:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Zájem o sdílení energie:</span> {formatCzechValue(formData.electricitySharing, 'electricitySharing')}</div>
                    {formData.electricitySharing === 'yes' && formData.sharingDetails && (
                      <div className="md:col-span-2"><span className="font-medium">Detaily sdílení:</span> {formatValue(formData.sharingDetails)}</div>
                    )}
                  </div>
                </div>
                
                {/* Plyn */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Plyn:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Má plyn:</span> {formatCzechValue(formData.hasGas, 'hasGas')}</div>
                    {formData.hasGas === 'yes' && (
                      <>
                        {formData.gasConsumption && (
                          <div><span className="font-medium">Roční spotřeba plynu:</span> {formatValue(formData.gasConsumption)} m³/rok</div>
                        )}
                        {formData.gasBill && (
                          <div><span className="font-medium">Náklady na plyn:</span> {formatValue(formData.gasBill)} Kč/rok</div>
                        )}
                        {(formData.gasUsage?.heating || formData.gasUsage?.hotWater || formData.gasUsage?.technology || formData.gasUsage?.cooking) && (
                          <div className="md:col-span-2">
                            <span className="font-medium">Použití plynu:</span>
                            <div className="ml-4 mt-1">
                              {formData.gasUsage?.heating && <div>• Vytápění</div>}
                              {formData.gasUsage?.hotWater && <div>• Ohřev vody</div>}
                              {formData.gasUsage?.technology && <div>• Technologie/výroba</div>}
                              {formData.gasUsage?.cooking && <div>• Vaření</div>}
                            </div>
                          </div>
                        )}
                      </>
                    )}
                  </div>
                </div>
                
                {/* Další spotřeby */}
                <div className="border-b pb-3">
                  <h5 className="font-medium mb-2">Další spotřeby:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {formData.hotWaterConsumption && (
                      <div><span className="font-medium">Spotřeba teplé vody:</span> {formatValue(formData.hotWaterConsumption)} l/den</div>
                    )}
                    {formData.steamConsumption && (
                      <div><span className="font-medium">Spotřeba páry:</span> {formatValue(formData.steamConsumption)} kg/hod</div>
                    )}
                    {formData.otherConsumption && (
                      <div className="md:col-span-2"><span className="font-medium">Jiné spotřeby:</span> {formatValue(formData.otherConsumption)}</div>
                    )}
                  </div>
                </div>
                
                {/* Kogenerační jednotka */}
                <div>
                  <h5 className="font-medium mb-2">Kogenerační jednotka:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><span className="font-medium">Má kogenerační jednotku:</span> {formatCzechValue(formData.hasCogeneration, 'hasCogeneration')}</div>
                    {formData.hasCogeneration === 'yes' && (
                      <>
                        {formData.cogenerationDetails && (
                          <div className="md:col-span-2"><span className="font-medium">Detaily kogenerační jednotky:</span> {formatValue(formData.cogenerationDetails)}</div>
                        )}
                        {formData.cogenerationPhotos && (
                          <div className="md:col-span-2"><span className="font-medium">Fotografie parametrů:</span> {formatUploadedFiles('cogenerationPhotos') || formatValue(formData.cogenerationPhotos)}</div>
                        )}
                      </>
                    )}
                  </div>
                </div>
                
                {/* Doplňující informace */}
                {formData.energyNotes && (
                  <div className="border-t pt-3">
                    <div><span className="font-medium">Doplňující informace k energetice:</span> {formatValue(formData.energyNotes)}</div>
                  </div>
                )}
              </div>
              {renderStepNote(8)}
            </div>
          </div>
        </div>
        
        <div className="p-6 border-t border-gray-200 bg-gray-50">
          <button
            onClick={() => setIsOpen(false)}
            className="btn-primary w-full"
          >
            Zavřít souhrn
          </button>
        </div>
      </div>
    </div>
  )
}

export default FormSummary
