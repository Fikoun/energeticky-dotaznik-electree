import { useState, useEffect } from 'react'
import { useForm, FormProvider } from 'react-hook-form'
import StepIndicator from './components/StepIndicator'
import FormStep1 from './components/steps/FormStep1'
import FormStep2 from './components/steps/FormStep2'
import FormStep3 from './components/steps/FormStep3'
import FormStep4 from './components/steps/FormStep4'
import FormStep5 from './components/steps/FormStep5'
import FormStep6 from './components/steps/FormStep6'
import FormStep7 from './components/steps/FormStep7'
import FormStep8 from './components/steps/FormStep8'
import FormSummary from './components/FormSummary'
import FormHistory from './components/FormHistory'
import OfflineNotification from './components/OfflineNotification'
import TopBar from './components/TopBar'
import Login from './components/Login'
import useAutoSave from './hooks/useAutoSave'
import { clearSessionTempId } from './hooks/useFileUpload'
import { Battery, Zap } from 'lucide-react'
import { saveFormData, loadFormData, isOffline, addToSubmissionQueue, initializeOfflineSupport } from './utils/formStorage'
import { uploadFiles } from './utils/fileUpload'

const TOTAL_STEPS = 8

function App() {
  const [currentStep, setCurrentStep] = useState(1)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submissionComplete, setSubmissionComplete] = useState(false)
  const [user, setUser] = useState(null)
  const [isOnline, setIsOnline] = useState(navigator.onLine)
  const [currentView, setCurrentView] = useState('form') // 'form' | 'history'
  const [editingForm, setEditingForm] = useState(null)
  
  // Nové stavy pro poznámky a navigaci
  const [visitedSteps, setVisitedSteps] = useState(new Set([1])) // Uživatel začíná na kroku 1
  const [stepNotes, setStepNotes] = useState({
    1: '',
    2: '',
    3: '',
    4: '',
    5: '',
    6: '',
    7: '',
    8: ''
  })

  // useEffect pro správnou inicializaci navštívených kroků
  useEffect(() => {
    // Při změně currentStep zajistíme, že aktuální krok je označen jako navštívený
    // ale neztrácíme dříve navštívené kroky
    setVisitedSteps(prev => {
      const newVisitedSteps = new Set(prev)
      newVisitedSteps.add(currentStep)
      console.log(`Adding step ${currentStep} to visited steps. New visited steps:`, Array.from(newVisitedSteps))
      return newVisitedSteps
    })
  }, [currentStep])

  // Inicializace - při prvním načtení označíme všechny kroky do aktuálního jako navštívené
  useEffect(() => {
    const initialVisitedSteps = new Set()
    for (let i = 1; i <= currentStep; i++) {
      initialVisitedSteps.add(i)
    }
    console.log(`Initial visited steps for currentStep ${currentStep}:`, Array.from(initialVisitedSteps))
    setVisitedSteps(initialVisitedSteps)
  }, []) // Spustí se pouze jednou při mount
  
  const methods = useForm({
    defaultValues: {
      // Krok 1 - Identifikační údaje
      companyName: '',
      ico: '',
      dic: '',
      contactPerson: '',
      phone: '',
      email: '',
      address: '',
      
      // Krok 2 - Parametry odběrného místa
      hasFveVte: '',
      fveVtePower: '',
      accumulationPercentage: '',
      interestedInFveVte: '',
      interestedInInstallationProcessing: '',
      hasTransformer: '',
      transformerPower: '',
      transformerVoltage: '',
      coolingType: '',
      transformerYear: '',
      transformerType: '',
      transformerCurrent: '',
      circuitBreakerType: '',
      customCircuitBreaker: '',
      sharesElectricity: '',
      electricityShared: '',
      receivesSharedElectricity: '',
      electricityReceived: '',
      mainCircuitBreaker: '',
      reservedPower: '',
      monthlyConsumption: '',
      monthlyMaxConsumption: '',
      significantConsumption: '',
      
      // Krok 3 - Energetické potřeby
      distributionTerritory: '',
      cezTerritory: '',
      edsTerritory: '',
      preTerritory: '',
      ldsName: '',
      ldsOwner: '',
      ldsNotes: '',
      measurementType: '',
      measurementTypeOther: '',
      weekdayStart: 8,
      weekdayEnd: 17,
      weekdayConsumption: 0,
      weekendStart: 10,
      weekendEnd: 15,
      weekendConsumption: 0,
      
      // Krok 4 - Cíle a očekávání
      solarInstallation: '',
      plannedInstallationDate: '',
      roofType: '',
      roofOrientation: '',
      installationLocation: '',
      
      // Krok 5 - Infrastruktura
      batteryCapacity: '',
      batteryType: '',
      installationPreference: '',
      hasPhotos: false,
      photos: [],
      hasVisualization: false,
      visualization: [],
      
      // Krok 6 - Provozní rámec
      hasCapacityIncrease: false,
      capacityIncreaseDetails: '',
      hasConnectionApplication: false,
      connectionApplication: [],
      
      // Krok 7 - Poznámky
      timeline: '',
      urgency: '',
      additionalNotes: '',
      
      // Krok 8 - Energetický dotazník
      energyPricing: '',
      currentEnergyPrice: '',
      billingMethod: '',
      billingDocuments: [],
      electricitySharing: '',
      monthlySharedElectricity: '',
      monthlyReceivedElectricity: '',
      hasGasConsumption: false,
      gasConsumption: '',
      hasCogeneration: false,
      cogenerationDetails: '',
      cogenerationPhotos: []
    }
  })

  // Auto-save functionality
  const autoSaveStatus = useAutoSave(methods, user, currentStep)
  const { formId, setFormId, disableAutoSave, enableAutoSave } = autoSaveStatus

  const handleLogin = (userData) => {
    console.log('User logged in:', userData)
    setUser(userData)
    // Save user to localStorage for persistence across page reloads
    localStorage.setItem('batteryFormUser', JSON.stringify(userData))
    setCurrentView('form')
  }

  const handleEditForm = (form) => {
    console.log('Editing form:', form)
    // Re-enable auto-save for editing
    enableAutoSave()
    
    if (form) {
      // Edit existing form - clear temp ID since we have a real form ID
      clearSessionTempId()
      const formData = JSON.parse(form.form_data || '{}')
      methods.reset(formData)
      setFormId(form.id)
      setEditingForm(form)
      console.log('Form data loaded for editing:', Object.keys(formData))
    } else {
      // New form - clear temp ID to get a fresh one
      clearSessionTempId()
      methods.reset()
      setFormId(null)
      setEditingForm(null)
      console.log('Creating new form')
    }
    setCurrentView('form')
    setCurrentStep(1)
    // Scroll to top when switching to form view
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const handleNewForm = () => {
    // Resetovat formulář na prázdný stav - použít stejnou strukturu jako defaultValues
    const emptyValues = {
      // Krok 1 - Identifikační údaje
      companyName: '',
      ico: '',
      dic: '',
      contactPerson: '',
      phone: '',
      email: '',
      address: '',
      
      // Krok 2 - Parametry odběrného místa
      hasFveVte: '',
      fveVtePower: '',
      accumulationPercentage: '',
      interestedInFveVte: '',
      interestedInInstallationProcessing: '',
      hasTransformer: '',
      transformerPower: '',
      transformerVoltage: '',
      coolingType: '',
      transformerYear: '',
      transformerType: '',
      transformerCurrent: '',
      circuitBreakerType: '',
      customCircuitBreaker: '',
      sharesElectricity: '',
      electricityShared: '',
      receivesSharedElectricity: '',
      electricityReceived: '',
      mainCircuitBreaker: '',
      reservedPower: '',
      monthlyConsumption: '',
      monthlyMaxConsumption: '',
      significantConsumption: '',
      
      // Krok 3 - Energetické potřeby
      distributionTerritory: '',
      cezTerritory: '',
      edsTerritory: '',
      preTerritory: '',
      ldsName: '',
      ldsOwner: '',
      ldsNotes: '',
      measurementType: '',
      measurementTypeOther: '',
      weekdayStart: 8,
      weekdayEnd: 17,
      weekdayConsumption: 0,
      weekendStart: 10,
      weekendEnd: 15,
      weekendConsumption: 0,
      
      // Krok 4 - Cíle a očekávání
      solarInstallation: '',
      plannedInstallationDate: '',
      roofType: '',
      roofOrientation: '',
      installationLocation: '',
      
      // Krok 5 - Infrastruktura
      batteryCapacity: '',
      batteryType: '',
      installationPreference: '',
      hasPhotos: false,
      photos: [],
      hasVisualization: false,
      visualization: [],
      
      // Krok 6 - Provozní rámec
      hasCapacityIncrease: false,
      capacityIncreaseDetails: '',
      hasConnectionApplication: false,
      connectionApplication: [],
      
      // Krok 7 - Poznámky
      timeline: '',
      urgency: '',
      additionalNotes: '',
      
      // Krok 8 - Energetický dotazník
      energyPricing: '',
      currentEnergyPrice: '',
      billingMethod: '',
      billingDocuments: [],
      electricitySharing: '',
      monthlySharedElectricity: '',
      monthlyReceivedElectricity: '',
      hasGasConsumption: false,
      gasConsumption: '',
      hasCogeneration: false,
      cogenerationDetails: '',
      cogenerationPhotos: []
    }
    
    methods.reset(emptyValues)
    setFormId(null)
    setEditingForm(null)
    setCurrentStep(1)
    setSubmissionComplete(false)
    setCurrentView('form')
    // Clear the session temp ID so new uploads get a fresh temp ID
    clearSessionTempId()
    // Re-enable auto-save for the new form
    enableAutoSave()
    console.log('Creating new form - all fields cleared')
    // Scroll to top when creating new form
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  // Initialize offline support and load saved user
  useEffect(() => {
    initializeOfflineSupport();
    
    // Load saved user data from localStorage
    try {
      const savedUser = localStorage.getItem('batteryFormUser');
      if (savedUser) {
        const userData = JSON.parse(savedUser);
        console.log('Restored user from localStorage:', userData);
        setUser(userData);
      }
    } catch (error) {
      console.error('Error loading saved user:', error);
      localStorage.removeItem('batteryFormUser');
    }
    
    // Load saved form data
    const savedData = loadFormData();
    if (savedData) {
      // Remove metadata before resetting form
      const { _lastSaved, _version, _currentStep, ...formData } = savedData;
      methods.reset(formData);
      if (_currentStep && _currentStep <= TOTAL_STEPS) {
        setCurrentStep(_currentStep);
      }
    }
  }, [methods]);

  // Monitor online status
  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Auto-save form data
  useEffect(() => {
    const subscription = methods.watch((data) => {
      const dataToSave = { ...data, _currentStep: currentStep };
      saveFormData(dataToSave);
    });

    return () => subscription.unsubscribe();
  }, [methods, currentStep]);

  const handleLogout = async () => {
    try {
      // Volání auth API pro odhlášení
      await fetch('auth.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'logout'
        })
      })
    } catch (error) {
      console.error('Error during logout:', error)
    }
    
    // Vyčištění stavu aplikace
    setUser(null)
    localStorage.removeItem('batteryFormUser')
    setCurrentStep(1)
    setSubmissionComplete(false)
    setCurrentView('form')
    setEditingForm(null)
    setFormId(null)
    // Clear session temp ID on logout
    clearSessionTempId()
    methods.reset()
  }

  const nextStep = async () => {
    if (currentStep < TOTAL_STEPS) {
      // Validace aktuálního kroku před přechodem na další
      const fieldsToValidate = getStepFields(currentStep)
      const isValid = await methods.trigger(fieldsToValidate)
      
      if (isValid) {
        setCurrentStep(currentStep + 1)
        // Scroll to top when changing steps
        window.scrollTo({ top: 0, behavior: 'smooth' })
      } else {
        // Pokud validace selže, nepřejdeme na další krok
        console.log('Formulář obsahuje chyby, nelze pokračovat')
        // Scroll to first error
        const firstErrorElement = document.querySelector('.error, [aria-invalid="true"]')
        if (firstErrorElement) {
          firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
      }
    }
  }

  // Definuje která pole patří ke kterému kroku
  const getStepFields = (step) => {
    switch (step) {
      case 1:
        return ['companyName', 'customerType.main', 'ico', 'contactPerson', 'phone', 'email', 'address']
      case 2:
        return ['currentProvider', 'currentTariff', 'monthlyConsumption', 'currentInstallation']
      case 3:
        return ['hasDistributionCurves', 'measurementType', 'hasCriticalConsumption', 'energyAccumulation', 'requiresBackup']
      case 4:
        return ['solarInstallation', 'plannedInstallationDate', 'roofType', 'roofOrientation', 'installationLocation']
      case 5:
        return ['batteryCapacity', 'batteryType', 'installationPreference', 'installationLocation']
      case 6:
        return ['projectBudget', 'financingMethod', 'hasEnergeticSpecialist']
      case 7:
        return ['additionalRequirements', 'hasOtherDocuments']
      case 8:
        return ['energyQuestionnaire']
      default:
        return []
    }
  }

  const prevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1)
      // Scroll to top when changing steps
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  const goToStep = (step) => {
    // Debug informace
    console.log(`Attempting to go to step ${step}`)
    console.log(`Current step: ${currentStep}`)
    console.log(`Visited steps:`, Array.from(visitedSteps))
    
    // Omezení navigace pouze na navštívené kroky
    if (!visitedSteps.has(step)) {
      console.log(`Step ${step} is not visited yet`)
      alert(`Nelze přejít na krok ${step}. Projděte formulář postupně.`)
      return
    }
    
    console.log(`Going to step ${step}`)
    if (step >= 1 && step <= TOTAL_STEPS) {
      setCurrentStep(step)
      // Scroll to top when changing steps
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  const goToNextStep = () => {
    if (currentStep < TOTAL_STEPS) {
      const nextStep = currentStep + 1
      setCurrentStep(nextStep)
      // Scroll to top when changing steps
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  const goToPreviousStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1)
      // Scroll to top when changing steps
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  // Funkce pro aktualizaci poznámky kroku
  const updateStepNote = (step, note) => {
    setStepNotes(prev => ({
      ...prev,
      [step]: note
    }))
  }

  // Názvy kroků pro poznámky - podle skutečných FormStep komponent
  const stepNames = {
    1: 'Identifikační údaje zákazníka',
    2: 'Parametry odběrného místa',
    3: 'Energetické potřeby',
    4: 'Cíle a očekávání',
    5: 'Infrastruktura a prostor',
    6: 'Provozní a legislativní rámec',
    7: 'Navržený postup a poznámky',
    8: 'Energetický dotazník'
  }

  const onSubmit = async (data) => {
    console.log('Form submission started')
    setIsSubmitting(true)
    
    try {
      // Generate or use existing form ID
      const currentFormId = formId || `form_${user?.id || 'anonymous'}_${Date.now()}`;
      
      // Step 1: Upload files first
      console.log('Uploading files...')
      const uploadResult = await uploadFiles(data, currentFormId);
      console.log('File upload result:', uploadResult);
      
      // Step 2: Prepare form data with file references
      const submissionData = {
        ...data,
        stepNotes: stepNotes,
        uploadedFiles: uploadResult.uploadedFiles || {},
        user: user ? {
          id: user.id,
          name: user.fullName || user.name,
          email: user.email
        } : null,
        formId: currentFormId,
        submittedAt: new Date().toISOString(),
        isDraft: false
      };

      console.log('Submitting data:', {
        hasUser: !!user,
        userId: user?.id,
        formId: currentFormId,
        dataKeys: Object.keys(data).length,
        hasUploadedFiles: Object.keys(uploadResult.uploadedFiles || {}).length > 0
      })

      if (isOffline()) {
        console.log('Device is offline, saving to queue')
        const queueId = addToSubmissionQueue(submissionData);
        console.log('Form saved offline with ID:', queueId);
        
        alert('Zařízení je offline. Formulář byl uložen a bude automaticky odeslán po obnovení připojení.');
        setSubmissionComplete(true);
        return;
      }

      console.log('Sending form to server...')
      const response = await fetch('/public/submit-form.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(submissionData)
      });
      
      console.log('Server response status:', response.status)
      
      if (response.ok) {
        const result = await response.json();
        console.log('Server response:', result)
        
        if (result.success) {
          // IMPORTANT: Disable auto-save FIRST to prevent it from overwriting the 'submitted' status
          disableAutoSave();
          
          if (result.requiresGdprConfirmation) {
            alert('Formulář byl úspěšně odeslán. Na váš email jsme zaslali odkaz pro potvrzení souhlasu GDPR.');
          } else {
            alert('Formulář byl úspěšně odeslán!');
          }
          
          // Clear saved form data
          localStorage.removeItem('batteryFormData');
          // Clear session temp ID since form is submitted
          clearSessionTempId();
          setSubmissionComplete(true);
          setEditingForm(null);
          setFormId(null);
        } else {
          throw new Error(result.error || 'Neznámá chyba serveru');
        }
      } else {
        const errorText = await response.text()
        console.error('Server error response:', errorText)
        throw new Error(`Server error: ${response.status} - ${errorText}`);
      }
    } catch (error) {
      console.error('Error submitting form:', error);
      
      // Show detailed error message
      let errorMessage = 'Došlo k chybě při odesílání formuláře: ' + error.message
      
      if (error.message.includes('fetch')) {
        errorMessage += '\n\nMožné příčiny:\n- Problém s připojením k internetu\n- Server není dostupný\n- Blokování požadavku firewallem'
      }
      
      alert(errorMessage)
      
      // Save to offline queue as fallback
      const queueId = addToSubmissionQueue(submissionData);
      console.log('Form saved to offline queue due to error:', queueId);
      
    } finally {
      setIsSubmitting(false);
    }
  }

  if (submissionComplete) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <div className="max-w-2xl mx-auto text-center">
          <div className="bg-white rounded-2xl shadow-xl p-12">
            <div className="flex justify-center mb-6">
              <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                <Zap className="h-10 w-10 text-green-600" />
              </div>
            </div>
            <h1 className="text-3xl font-bold text-gray-900 mb-4">
              Děkujeme za vyplnění dotazníku!
            </h1>
            <p className="text-lg text-gray-600 mb-8">
              Vaše údaje byly úspěšně odeslány a budou zpracovány. Brzy se vám ozve náš specialista s nabídkou na míru.
            </p>
            <button 
              onClick={() => window.location.reload()} 
              className="btn-primary"
            >
              Vyplnit další dotazník
            </button>
          </div>
        </div>
      </div>
    )
  }

  const renderStep = () => {
    switch (currentStep) {
      case 1: return <FormStep1 />
      case 2: return <FormStep2 />
      case 3: return <FormStep3 formId={formId} />
      case 4: return <FormStep4 />
      case 5: return <FormStep5 formId={formId} />
      case 6: return <FormStep6 formId={formId} />
      case 7: return <FormStep7 />
      case 8: return <FormStep8 formId={formId} />
      default: return <FormStep1 />
    }
  }

  // Pokud uživatel není přihlášený, zobraz login
  if (!user) {
    return <Login onLogin={handleLogin} />
  }

  // Pokud je zobrazena historie formulářů
  if (currentView === 'history') {
    return (
      <FormHistory 
        user={user} 
        onEditForm={handleEditForm}
        onBackToForms={() => setCurrentView('form')}
      />
    )
  }

  return (
    <FormProvider {...methods}>
      <div className="min-h-screen bg-gray-50">
        {/* Top Bar */}
        <TopBar 
          user={user}
          currentView={currentView}
          onViewChange={setCurrentView}
          onLogout={handleLogout}
          autoSaveStatus={autoSaveStatus}
          onNewForm={handleNewForm}
        />

        <div className="py-8 px-4">
          <div className="max-w-4xl mx-auto">
            {/* Header */}
            <div className="text-center mb-8">
              <OfflineNotification isOnline={isOnline} />

              <div className="flex items-center justify-center gap-3 mb-4">
                <Battery className="h-8 w-8 text-primary-600" />
                <h1 className="text-3xl font-bold text-gray-900">
                  Průzkumný dotazník
                </h1>
              </div>
              <p className="text-lg text-gray-600 mb-2">
                pro bateriové úložiště / energetická řešení
              </p>
              <div className="flex items-center justify-center gap-2 text-sm text-gray-500">
                <span>Electree</span>
                <span>•</span>
                <span>Krok {currentStep} z {TOTAL_STEPS}</span>
                {editingForm && (
                  <>
                    <span>•</span>
                    <span className="text-blue-600">Editace formuláře</span>
                  </>
                )}
              </div>
            </div>

            {/* Progress Indicator */}
            <StepIndicator 
              currentStep={currentStep} 
              totalSteps={TOTAL_STEPS} 
              onStepClick={goToStep}
              visitedSteps={visitedSteps}
              stepNotes={stepNotes}
              stepNames={stepNames}
              onUpdateStepNote={updateStepNote}
            />

            {/* Form Content */}
            <form onSubmit={methods.handleSubmit(onSubmit)} className="mt-8">
              <div className="form-step">
                {renderStep()}
                
                {/* Navigation Buttons */}
                <div className="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                  <button
                    type="button"
                    onClick={prevStep}
                    disabled={currentStep === 1}
                    className={`${
                      currentStep === 1 
                        ? 'opacity-0 pointer-events-none' 
                        : 'btn-secondary'
                    }`}
                  >
                    Zpět
                  </button>

                  {currentStep === TOTAL_STEPS ? (
                  <div className="flex gap-4">
                    <FormSummary 
                      user={user} 
                      stepNotes={stepNotes}
                      stepNames={stepNames}
                    />
                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {isSubmitting ? 'Odesílám...' : 'Odeslat dotazník'}
                    </button>
                  </div>
                  ) : (
                    <button
                      type="button"
                      onClick={nextStep}
                      className="btn-primary"
                    >
                      Další krok
                    </button>
                  )}
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </FormProvider>
  )
}

export default App
