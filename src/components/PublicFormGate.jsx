import { useState, useEffect } from 'react'
import { Link2, Lock, ArrowRight, Battery, AlertTriangle } from 'lucide-react'

/**
 * Gate screen for public form links.
 * Validates token and optional password before allowing form access.
 */
const PublicFormGate = ({ token, onAuthenticated }) => {
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')
  const [linkInfo, setLinkInfo] = useState(null) // result of validate
  const [password, setPassword] = useState('')
  const [validated, setValidated] = useState(false)

  // Step 1: Validate token on first render
  useEffect(() => {
    validateToken()
  }, [])

  async function validateToken() {
    setIsLoading(true)
    setError('')
    try {
      const res = await fetch(`/public/public-form-auth.php?action=validate&token=${encodeURIComponent(token)}`)
      const result = await res.json()
      if (result.success) {
        setLinkInfo(result.data)
        setValidated(true)
        // If no password required, auto-authenticate
        if (!result.data.requires_password) {
          await authenticate('')
        }
      } else {
        setError(result.error || 'Neplatný odkaz')
      }
    } catch (err) {
      setError('Chyba připojení k serveru')
    } finally {
      setIsLoading(false)
    }
  }

  async function authenticate(pwd) {
    setIsLoading(true)
    setError('')
    try {
      const res = await fetch('/public/public-form-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'authenticate', token, password: pwd }),
      })
      const result = await res.json()
      if (result.success) {
        onAuthenticated(result.data)
      } else {
        setError(result.error || 'Přístup zamítnut')
      }
    } catch (err) {
      setError('Chyba připojení k serveru')
    } finally {
      setIsLoading(false)
    }
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    authenticate(password)
  }

  // Loading state
  if (isLoading && !validated) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center px-4">
        <div className="max-w-md w-full text-center">
          <div className="bg-white rounded-xl shadow-lg p-8">
            <div className="animate-spin h-8 w-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p className="text-gray-600">Ověřuji odkaz...</p>
          </div>
        </div>
      </div>
    )
  }

  // Error state (invalid/expired token)
  if (error && !validated) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center px-4">
        <div className="max-w-md w-full">
          <div className="bg-white rounded-xl shadow-lg p-8 text-center">
            <div className="flex justify-center mb-4">
              <div className="bg-red-100 p-3 rounded-full">
                <AlertTriangle className="h-8 w-8 text-red-600" />
              </div>
            </div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Neplatný odkaz</h2>
            <p className="text-gray-600">{error}</p>
          </div>
        </div>
      </div>
    )
  }

  // Password gate
  if (validated && linkInfo?.requires_password) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center px-4">
        <div className="max-w-md w-full">
          <div className="text-center mb-8">
            <div className="flex items-center justify-center mb-4">
              <div className="bg-primary-600 p-3 rounded-full">
                <Battery className="h-8 w-8 text-white" />
              </div>
            </div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Dotazník Electree</h1>
            <p className="text-gray-600">Vyplnění formuláře pro bateriové úložiště</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-8">
            <div className="flex items-center justify-center mb-6">
              <Link2 className="h-6 w-6 text-primary-600 mr-2" />
              <h2 className="text-xl font-semibold text-gray-900">Přístup k formuláři</h2>
            </div>

            {linkInfo.recipient_name && (
              <p className="text-center text-gray-600 mb-4">
                Dobrý den, <strong>{linkInfo.recipient_name}</strong>
              </p>
            )}

            <p className="text-center text-sm text-gray-500 mb-6">
              Formulář vám sdílí <strong>{linkInfo.owner_name}</strong>.
              Pro přístup zadejte heslo.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="form-label">
                  <Lock className="inline h-4 w-4 mr-2" />
                  Heslo
                </label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="form-input w-full"
                  placeholder="Zadejte heslo"
                  autoFocus
                  required
                />
              </div>

              {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={isLoading}
                className="btn-primary w-full flex items-center justify-center gap-2"
              >
                {isLoading ? (
                  'Ověřuji...'
                ) : (
                  <>
                    Pokračovat k formuláři
                    <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </button>
            </form>
          </div>
        </div>
      </div>
    )
  }

  // Auto-authenticating (no password required) - show loading
  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center px-4">
      <div className="max-w-md w-full text-center">
        <div className="bg-white rounded-xl shadow-lg p-8">
          <div className="animate-spin h-8 w-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">Připravuji formulář...</p>
        </div>
      </div>
    </div>
  )
}

export default PublicFormGate
