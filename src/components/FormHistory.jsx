import { useState, useEffect } from 'react'
import { useForm, FormProvider } from 'react-hook-form'
import { FileText, Edit, Trash2, Eye, Calendar, User, Building, Phone, Mail, ArrowLeft } from 'lucide-react'

const FormHistory = ({ user, onEditForm, onBackToForms }) => {
  const [forms, setForms] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedForm, setSelectedForm] = useState(null)

  useEffect(() => {
    loadUserForms()
  }, [user])

  const loadUserForms = async () => {
    try {
      console.log('Loading forms for user:', user.id)
      const response = await fetch(`/public/get-user-forms.php?userId=${user.id}`)
      console.log('Response status:', response.status)
      
      if (response.ok) {
        const data = await response.json()
        console.log('Response data:', data)
        setForms(data.forms || [])
      } else {
        console.error('Response not ok:', response.status, response.statusText)
        const errorText = await response.text()
        console.error('Error response:', errorText)
      }
    } catch (error) {
      console.error('Error loading forms:', error)
    } finally {
      setLoading(false)
    }
  }

  const deleteForm = async (formId) => {
    if (!confirm('Opravdu chcete smazat tento formulář?')) return

    try {
      const response = await fetch('/public/delete-form.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ formId, userId: user.id })
      })

      if (response.ok) {
        setForms(forms.filter(form => form.id !== formId))
        alert('Formulář byl úspěšně smazán')
      }
    } catch (error) {
      console.error('Error deleting form:', error)
      alert('Chyba při mazání formuláře')
    }
  }

  const getStatusBadge = (status) => {
    const statusConfig = {
      draft: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Rozepsaný' },
      submitted: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Odeslaný' },
      confirmed: { bg: 'bg-green-100', text: 'text-green-800', label: 'Potvrzený' },
      processing: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Zpracovává se' }
    }

    const config = statusConfig[status] || statusConfig.draft
    
    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
        {config.label}
      </span>
    )
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('cs-CZ', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  if (selectedForm) {
    return <FormDetail form={selectedForm} onBack={() => setSelectedForm(null)} />
  }

  if (loading) {
    return (
      <div className="max-w-6xl mx-auto p-6">
        <div className="flex items-center justify-center py-12">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto mb-4"></div>
            <p className="text-gray-600">Načítám formuláře...</p>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-6xl mx-auto p-6">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Moje formuláře</h1>
          <p className="text-gray-600">Přehled všech vašich dotazníků a jejich stavu</p>
        </div>
        
        <div className="flex gap-3">
          <button
            onClick={onBackToForms}
            className="btn-secondary flex items-center gap-2"
          >
            <ArrowLeft className="h-4 w-4" />
            Zpět na formulář
          </button>
          <button
            onClick={() => onEditForm(null)} // Nový formulář
            className="btn-primary flex items-center gap-2"
          >
            <FileText className="h-4 w-4" />
            Nový formulář
          </button>
        </div>
      </div>

      {forms.length === 0 ? (
        <div className="text-center py-12">
          <FileText className="h-16 w-16 text-gray-300 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">Žádné formuláře</h3>
          <p className="text-gray-600 mb-6">Zatím jste nevytvořili žádný formulář.</p>
          <button
            onClick={() => onEditForm(null)}
            className="btn-primary"
          >
            Vytvořit první formulář
          </button>
        </div>
      ) : (
        <div className="grid gap-6">
          {forms.map((form) => (
            <div key={form.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-3">
                    <Building className="h-5 w-5 text-gray-400" />
                    <h3 className="text-lg font-semibold text-gray-900">
                      {form.company_name || 'Bez názvu společnosti'}
                    </h3>
                    {getStatusBadge(form.status)}
                  </div>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm text-gray-600">
                    <div className="flex items-center gap-2">
                      <User className="h-4 w-4" />
                      <span>{form.contact_person || 'Bez kontaktní osoby'}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Phone className="h-4 w-4" />
                      <span>{form.phone || 'Bez telefonu'}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Mail className="h-4 w-4" />
                      <span>{form.email || 'Bez emailu'}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4" />
                      <span>{formatDate(form.updated_at || form.created_at)}</span>
                    </div>
                  </div>

                  {form.gdpr_confirmed_at && (
                    <div className="mt-3 flex items-center gap-2 text-sm text-green-600">
                      <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                      <span>GDPR potvrzeno {formatDate(form.gdpr_confirmed_at)}</span>
                    </div>
                  )}
                </div>

                <div className="flex items-center gap-2 ml-4">
                  <button
                    onClick={() => setSelectedForm(form)}
                    className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    title="Zobrazit detail"
                  >
                    <Eye className="h-4 w-4" />
                  </button>
                  
                  {form.status === 'draft' && (
                    <button
                      onClick={() => onEditForm(form)}
                      className="p-2 text-blue-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                      title="Upravit"
                    >
                      <Edit className="h-4 w-4" />
                    </button>
                  )}
                  
                  <button
                    onClick={() => deleteForm(form.id)}
                    className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Smazat"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

const FormDetail = ({ form, onBack }) => {
  const formData = JSON.parse(form.form_data || '{}')
  const stepNotes = formData.stepNotes || {}

  // Správné názvy kroků podle FormStep komponent
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

  const getStepGradient = (step) => {
    const gradients = {
      1: 'from-blue-500 to-blue-600',
      2: 'from-emerald-500 to-emerald-600',
      3: 'from-purple-500 to-purple-600',
      4: 'from-orange-500 to-orange-600',
      5: 'from-red-500 to-red-600',
      6: 'from-indigo-500 to-indigo-600',
      7: 'from-yellow-500 to-yellow-600',
      8: 'from-pink-500 to-pink-600'
    }
    return gradients[step] || 'from-gray-500 to-gray-600'
  }

  const getStepIcon = (step) => {
    const icons = {
      1: '👤',
      2: '🏠',
      3: '📊',
      4: '🎯',
      5: '🏢',
      6: '⚖️',
      7: '📋',
      8: '⚡'
    }
    return icons[step] || '📄'
  }

  const getFieldIcon = (field) => {
    const icons = {
      companyName: '🏢',
      contactPerson: '👤',
      phone: '📞',
      email: '📧',
      ico: '🆔',
      dic: '📄',
      address: '📍',
      companyAddress: '🏢',
      customerType: '🏷️'
    }
    return icons[field] || 'ℹ️'
  }

  const formatFieldValue = (key, value) => {
    if (value === null || value === undefined || value === '' || value === false) {
      return <span className="text-gray-400 italic">Nevyplněno</span>
    }
    
    if (typeof value === 'object' && value !== null) {
      if (key.includes('customerType')) {
        const types = []
        Object.entries(value).forEach(([type, selected]) => {
          if (selected) {
            const typeLabels = {
              industrial: '🏭 Průmysl',
              commercial: '🏢 Komerční objekt',
              services: '🚚 Služby / Logistika',
              agriculture: '🌾 Zemědělství',
              public: '🏛️ Veřejný sektor',
              other: '❓ Jiný'
            }
            types.push(typeLabels[type] || type)
          }
        })
        return types.length > 0 ? types.join(', ') : <span className="text-gray-400 italic">Nevyplněno</span>
      }
      return JSON.stringify(value)
    }
    
    if (key === 'phone') {
      return <a href={`tel:${value}`} className="text-blue-600 hover:underline">{value}</a>
    }
    
    if (key === 'email') {
      return <a href={`mailto:${value}`} className="text-blue-600 hover:underline">{value}</a>
    }
    
    if (value === 'yes') {
      return <span className="text-emerald-600 font-medium">✓ Ano</span>
    }
    
    if (value === 'no') {
      return <span className="text-red-600 font-medium">✗ Ne</span>
    }
    
    return value.toString()
  }

  const getFieldLabel = (key) => {
    const labels = {
      // Krok 1
      'companyName': 'Název společnosti / jméno',
      'ico': 'IČO',
      'dic': 'DIČ',
      'contactPerson': 'Kontaktní osoba',
      'phone': 'Telefon',
      'email': 'E-mail',
      'address': 'Adresa odběrného místa',
      'companyAddress': 'Adresa sídla firmy',
      'customerType': 'Typ zákazníka',
      
      // Krok 2
      'hasFveVte': 'Má instalovanou FVE/VTE',
      'fveVtePower': 'Výkon FVE/VTE',
      'hasTransformer': 'Má trafostanici',
      'transformerPower': 'Výkon trafostanice',
      'circuitBreakerType': 'Typ hlavního jističe',
      'mainCircuitBreaker': 'Hlavní jistič',
      'reservedPower': 'Rezervovaný příkon',
      'monthlyConsumption': 'Měsíční spotřeba',
      
      // Další pole...
    }
    
    return labels[key] || key.replace(/([A-Z])/g, ' $1').toLowerCase()
  }

  // Rozdělení dat do kroků
  const processedSteps = {}
  Object.entries(formData).forEach(([key, value]) => {
    if (key === 'stepNotes') return
    
    // Pokusit se identifikovat krok podle klíče
    const step1Fields = ['companyName', 'ico', 'dic', 'contactPerson', 'phone', 'email', 'address', 'companyAddress', 'customerType']
    const step2Fields = ['hasFveVte', 'fveVtePower', 'hasTransformer', 'transformerPower', 'circuitBreakerType', 'mainCircuitBreaker', 'reservedPower', 'monthlyConsumption']
    
    let stepNumber = 1 // Výchozí
    if (step1Fields.some(field => key.includes(field))) stepNumber = 1
    else if (step2Fields.some(field => key.includes(field))) stepNumber = 2
    else if (key.includes('energy') || key.includes('consumption') || key.includes('curve')) stepNumber = 3
    else if (key.includes('goal') || key.includes('expectation') || key.includes('saving')) stepNumber = 4
    else if (key.includes('space') || key.includes('location') || key.includes('infrastructure')) stepNumber = 5
    else if (key.includes('permit') || key.includes('regulation') || key.includes('standard')) stepNumber = 6
    else if (key.includes('note') || key.includes('approach') || key.includes('timeline')) stepNumber = 7
    else if (key.includes('supplier') || key.includes('tariff') || key.includes('bill')) stepNumber = 8
    
    if (!processedSteps[stepNumber]) {
      processedSteps[stepNumber] = {}
    }
    processedSteps[stepNumber][key] = value
  })

  return (
    <div className="max-w-6xl mx-auto p-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
      {/* Header s gradientem */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 shadow-xl rounded-2xl mb-8 text-white">
        <div className="px-8 py-6">
          <div className="flex justify-between items-start">
            <div>
              <div className="flex items-center mb-3">
                <span className="text-3xl mr-4">📄</span>
                <h1 className="text-3xl font-bold">Detail formuláře #{form.id}</h1>
              </div>
              <div className="flex flex-wrap gap-4 text-sm opacity-90">
                <div className="flex items-center">
                  <span className="mr-2">📅</span>
                  <span>Vytvořen: {new Date(form.created_at).toLocaleString('cs-CZ')}</span>
                </div>
                {form.updated_at && (
                  <div className="flex items-center">
                    <span className="mr-2">🔄</span>
                    <span>Aktualizován: {new Date(form.updated_at).toLocaleString('cs-CZ')}</span>
                  </div>
                )}
              </div>
            </div>
            <div className="flex flex-col items-end gap-3">
              <div className="px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold">
                <span className="mr-2">📊</span>
                {form.status === 'completed' ? 'Dokončený' : 
                 form.status === 'submitted' ? 'Odeslaný' : 
                 form.status === 'draft' ? 'Rozpracovaný' : form.status}
              </div>
              <button
                onClick={onBack}
                className="px-6 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors backdrop-blur-sm flex items-center gap-2"
              >
                <ArrowLeft className="h-4 w-4" />
                Zpět
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Zákazník info */}
      <div className="bg-white shadow-lg rounded-2xl mb-8 overflow-hidden">
        <div className="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4">
          <h2 className="text-xl font-bold text-white flex items-center">
            <span className="text-2xl mr-3">👤</span>
            Informace o zákazníkovi
          </h2>
        </div>
        <div className="px-6 py-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="flex items-start space-x-4">
              <div className="bg-emerald-100 p-3 rounded-xl">
                <span className="text-emerald-600 text-lg">👤</span>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-500 mb-1">Jméno zákazníka</label>
                <p className="text-lg font-semibold text-gray-900">{formData.contactPerson || 'Neznámý'}</p>
              </div>
            </div>
            <div className="flex items-start space-x-4">
              <div className="bg-blue-100 p-3 rounded-xl">
                <span className="text-blue-600 text-lg">📧</span>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-500 mb-1">Email</label>
                <p className="text-lg font-semibold text-gray-900">{formData.email || 'Neznámý'}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Form Steps Data */}
      <div className="space-y-8">
        {Object.entries(processedSteps)
          .sort(([a], [b]) => parseInt(a) - parseInt(b))
          .map(([stepNumber, stepData]) => (
          <div key={stepNumber} className="bg-white shadow-lg rounded-2xl overflow-hidden">
            {/* Step Header */}
            <div className={`bg-gradient-to-r ${getStepGradient(parseInt(stepNumber))} px-6 py-4`}>
              <h3 className="text-xl font-bold text-white flex items-center">
                <div className="bg-white/20 backdrop-blur-sm rounded-xl px-3 py-2 mr-4">
                  <span className="text-lg font-black">{stepNumber}</span>
                </div>
                <span className="text-2xl mr-3">{getStepIcon(parseInt(stepNumber))}</span>
                {stepNames[parseInt(stepNumber)] || `Krok ${stepNumber}`}
              </h3>
            </div>
            
            {/* Step Content */}
            <div className="px-6 py-6">
              {Object.keys(stepData).length > 0 ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  {Object.entries(stepData).map(([fieldKey, fieldValue]) => {
                    if (!fieldValue || fieldValue === '' || fieldValue === false) return null
                    
                    return (
                      <div key={fieldKey} className="bg-gray-50 rounded-xl p-4 border border-gray-100 hover:shadow-md transition-shadow">
                        <div className="flex items-start space-x-3">
                          <div className="bg-blue-100 p-2 rounded-lg flex-shrink-0">
                            <span className="text-blue-600">{getFieldIcon(fieldKey)}</span>
                          </div>
                          <div className="flex-1 min-w-0">
                            <label className="block text-sm font-medium text-gray-600 mb-1">
                              {getFieldLabel(fieldKey)}
                            </label>
                            <div className="text-gray-900 font-medium">
                              {formatFieldValue(fieldKey, fieldValue)}
                            </div>
                          </div>
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <div className="text-center py-8 text-gray-500">
                  <span className="text-3xl mb-3 block">📭</span>
                  <p>Žádná data pro tento krok nejsou k dispozici</p>
                </div>
              )}
              
              {/* Poznámka ke kroku */}
              {stepNotes[parseInt(stepNumber)] && stepNotes[parseInt(stepNumber)].trim() && (
                <div className="mt-6 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-xl p-6">
                  <div className="flex items-start space-x-3">
                    <div className="bg-amber-100 p-3 rounded-xl flex-shrink-0">
                      <span className="text-amber-600 text-lg">📝</span>
                    </div>
                    <div className="flex-1">
                      <h4 className="font-semibold text-amber-800 mb-2 flex items-center">
                        <span>{stepNames[parseInt(stepNumber)]} - Poznámka</span>
                      </h4>
                      <div className="text-amber-700 whitespace-pre-wrap leading-relaxed">
                        {stepNotes[parseInt(stepNumber)]}
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Floating Action Button pro tisk */}
      <div className="fixed bottom-6 right-6">
        <button 
          onClick={() => window.print()} 
          className="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105"
        >
          <span className="text-xl">🖨️</span>
        </button>
      </div>
    </div>
  )
}

export default FormHistory
