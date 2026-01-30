import { User, FileText, LogOut, Save, Clock, AlertCircle, Shield, Settings, Plus } from 'lucide-react'

const TopBar = ({ 
  user, 
  currentView, 
  onViewChange, 
  onLogout, 
  autoSaveStatus,
  onNewForm 
}) => {
  const { isSaving, lastSaved, saveError } = autoSaveStatus || {}

  const getRoleBadge = (role) => {
    const roleConfig = {
      admin: { label: 'Admin', color: 'bg-red-500 text-white', icon: Shield },
      partner: { label: 'Partner', color: 'bg-purple-500 text-white', icon: Settings },
      salesman: { label: 'Obchodník', color: 'bg-blue-500 text-white', icon: User },
      user: { label: 'Uživatel', color: 'bg-gray-500 text-white', icon: User }
    }
    
    const config = roleConfig[role] || roleConfig.user
    const Icon = config.icon
    
    return (
      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${config.color}`}>
        <Icon className="h-3 w-3 mr-1" />
        {config.label}
      </span>
    )
  }

  const handleAdminPanel = () => {
    window.open('http://localhost:8080/public/admin-dashboard.php', '_blank')
  }

  return (
    <div className="bg-white border-b border-gray-200 shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Logo/Brand */}
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <h1 className="text-xl font-bold text-gray-900">Electree</h1>
            </div>
          </div>

          {/* User info and navigation */}
          <div className="flex items-center space-x-4">
            {/* Auto-save status */}
            {user && currentView === 'form' && (
              <div className="hidden sm:flex items-center space-x-2 text-sm">
                {saveError ? (
                  <div className="flex items-center text-red-600" title={saveError}>
                    <AlertCircle className="h-4 w-4 mr-1" />
                    <span>Chyba ukládání</span>
                  </div>
                ) : isSaving ? (
                  <div className="flex items-center text-blue-600">
                    <Save className="h-4 w-4 mr-1 animate-pulse" />
                    <span>Ukládám...</span>
                  </div>
                ) : lastSaved ? (
                  <div className="flex items-center text-green-600">
                    <Clock className="h-4 w-4 mr-1" />
                    <span>Uloženo {lastSaved.toLocaleTimeString('cs-CZ', { 
                      hour: '2-digit', 
                      minute: '2-digit' 
                    })}</span>
                  </div>
                ) : null}
              </div>
            )}

            {/* User info */}
            <div className="flex items-center space-x-3 text-sm text-gray-700">
              <User className="h-4 w-4" />
              <div className="flex flex-col items-end">
                <span className="font-medium">{user?.fullName || user?.name}</span>
                <div className="flex items-center space-x-2">
                  {user?.role && getRoleBadge(user.role)}
                  {user?.email && (
                    <span className="text-xs text-gray-500">{user.email}</span>
                  )}
                </div>
              </div>
            </div>

            {/* Navigation */}
            <div className="flex items-center space-x-1">
              {/* Nový formulář */}
              <button
                onClick={onNewForm}
                className="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md transition-colors"
                title="Vytvořit nový formulář"
              >
                <Plus className="h-4 w-4 mr-2" />
                <span className="hidden sm:inline">Nový formulář</span>
              </button>

              {/* Admin Panel Button */}
              {user && user.role === 'admin' && (
                <button
                  onClick={handleAdminPanel}
                  className="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors"
                  title="Admin Panel"
                >
                  <Shield className="h-4 w-4 mr-2" />
                  <span className="hidden sm:inline">Admin</span>
                </button>
              )}

              <button
                onClick={() => onViewChange(currentView === 'history' ? 'form' : 'history')}
                className={`inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                  currentView === 'history' 
                    ? 'bg-primary-100 text-primary-700 hover:bg-primary-200' 
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                }`}
                title={currentView === 'history' ? 'Zpět na formulář' : 'Zobrazit moje formuláře'}
              >
                <FileText className="h-4 w-4 mr-2" />
                {currentView === 'history' ? 'Formulář' : 'Historie'}
              </button>

              <button
                onClick={onLogout}
                className="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                title="Odhlásit se"
              >
                <LogOut className="h-4 w-4 mr-2" />
                Odhlásit
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default TopBar
