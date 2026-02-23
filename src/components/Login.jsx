import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { User, Lock, LogIn, Battery } from 'lucide-react'

const Login = ({ onLogin }) => {
  const [isLoading, setIsLoading] = useState(false)
  const [loginError, setLoginError] = useState('')
  
  const { register, handleSubmit, formState: { errors } } = useForm()

  const onSubmit = async (data) => {
    setIsLoading(true)
    setLoginError('')

    try {
      console.log('Login attempt with:', { username: data.username })
      
      // Call auth API
      const response = await fetch('auth.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'login',
          username: data.username,
          password: data.password,
          rememberMe: data.rememberMe || false
        })
      })

      console.log('Response status:', response.status)
      console.log('Response headers:', response.headers)

      // Získej odpověď jako text nejprve pro debug
      const responseText = await response.text()
      console.log('=== LOGIN DEBUG START ===')
      console.log('Response status:', response.status)
      console.log('Response headers:', Object.fromEntries(response.headers.entries()))
      console.log('Response text length:', responseText.length)
      console.log('Response text:', responseText)
      
      if (response.ok) {
        try {
          const result = JSON.parse(responseText)
          console.log('Parsed JSON result:', result)
          
          // Zobrazit debug informace pokud jsou k dispozici
          if (result.debug) {
            console.log('=== SERVER DEBUG LOG ===')
            result.debug.forEach((log, index) => {
              console.log(`${index + 1}. ${log}`)
            })
            console.log('=== END SERVER DEBUG ===')
          }
          
          if (result.debug_summary) {
            console.log('Debug summary:', result.debug_summary)
          }
          
          if (result.success && result.user) {
            console.log('✅ Login successful, user data:', result.user)
            
            // Úspěšné přihlášení - předáme data stejným způsobem
            onLogin({
              id: result.user.id,
              username: result.user.name, // Pro zpětnou kompatibilitu
              name: result.user.name,
              email: result.user.email,
              role: result.user.role,
              firstName: result.user.name.split(' ')[0] || result.user.name,
              lastName: result.user.name.split(' ').slice(1).join(' ') || '',
              fullName: result.user.name
            })
          } else {
            console.log('❌ Login failed:', result.error)
            
            // Pokud máme debug informace, ukážeme je v chybě
            if (result.debug) {
              const debugSummary = result.debug.slice(-5).join('; ')
              setLoginError(`${result.error || 'Nesprávné přihlašovací údaje'} (Debug: ${debugSummary})`)
            } else {
              setLoginError(result.error || 'Nesprávné přihlašovací údaje')
            }
          }
        } catch (jsonError) {
          console.error('❌ JSON parse error:', jsonError)
          console.error('Raw response was:', responseText)
          console.error('Response length:', responseText.length)
          console.error('First 100 chars:', responseText.substring(0, 100))
          console.error('Last 100 chars:', responseText.substring(responseText.length - 100))
          
          setLoginError(`Chyba parsování odpovědi serveru: ${jsonError.message} (Délka odpovědi: ${responseText.length})`)
        }
      } else {
        console.error('❌ HTTP error response:', responseText)
        
        // Pokusíme se parsovat i chybovou odpověď pro debug
        try {
          const errorResult = JSON.parse(responseText)
          if (errorResult.debug) {
            console.log('=== ERROR DEBUG LOG ===')
            errorResult.debug.forEach((log, index) => {
              console.log(`${index + 1}. ${log}`)
            })
            console.log('=== END ERROR DEBUG ===')
          }
          
          setLoginError(`Chyba serveru (${response.status}): ${errorResult.error || responseText}`)
        } catch {
          setLoginError(`Chyba serveru při přihlašování (${response.status}): ${responseText}`)
        }
      }
      
      console.log('=== LOGIN DEBUG END ===')
    } catch (error) {
      console.error('Login error:', error)
      setLoginError(`Chyba připojení k serveru: ${error.message}`)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-50 to-primary-100 flex items-center justify-center px-4">
      <div className="max-w-md w-full">
        {/* Logo a nadpis */}
        <div className="text-center mb-8">
          <div className="flex items-center justify-center mb-4">
            <div className="bg-primary-600 p-3 rounded-full">
              <Battery className="h-8 w-8 text-white" />
            </div>
          </div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Bateriové úložiště
          </h1>
          <p className="text-gray-600">
            Dotazník pro návrh bateriového úložiště
          </p>
        </div>

        {/* Přihlašovací formulář */}
        <div className="bg-white rounded-xl shadow-lg p-8">
          <div className="flex items-center justify-center mb-6">
            <LogIn className="h-6 w-6 text-primary-600 mr-2" />
            <h2 className="text-xl font-semibold text-gray-900">
              Přihlášení
            </h2>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Uživatelské jméno */}
            <div>
              <label className="form-label">
                <User className="inline h-4 w-4 mr-2" />
                Nickname / Email
              </label>
              <input
                type="text"
                className={`form-input ${errors.username ? 'border-red-500' : ''}`}
                placeholder="Zadejte nickname nebo email"
                {...register('username', { 
                  required: 'Nickname nebo email je povinný' 
                })}
              />
              {errors.username && (
                <p className="text-red-500 text-sm mt-1">{errors.username.message}</p>
              )}
            </div>

            {/* Heslo */}
            <div>
              <label className="form-label">
                <Lock className="inline h-4 w-4 mr-2" />
                Heslo
              </label>
              <input
                type="password"
                className={`form-input ${errors.password ? 'border-red-500' : ''}`}
                placeholder="Zadejte heslo"
                {...register('password', { 
                  required: 'Heslo je povinné' 
                })}
              />
              {errors.password && (
                <p className="text-red-500 text-sm mt-1">{errors.password.message}</p>
              )}
            </div>

            {/* Zapamatovat si mě */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="rememberMe"
                className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                {...register('rememberMe')}
              />
              <label htmlFor="rememberMe" className="ml-2 block text-sm text-gray-900">
                Zapamatovat si mě (30 dní)
              </label>
            </div>

            {/* Chybová hláška */}
            {loginError && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                <p className="text-red-800 text-sm">{loginError}</p>
              </div>
            )}

            {/* Tlačítko pro přihlášení */}
            <button
              type="submit"
              disabled={isLoading}
              className="w-full btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? 'Přihlašuji...' : 'Přihlásit se'}
            </button>
          </form>

          {/* Demo účty */}
          <div className="mt-8 pt-6 border-t border-gray-200">
            <h3 className="text-sm font-medium text-gray-900 mb-3">
              Testovací účty:
            </h3>
            <div className="space-y-2 text-xs text-gray-600">
              <div className="bg-gray-50 p-2 rounded">
                <strong>admin</strong> / admin123 (Administrator)
              </div>
              <div className="bg-gray-50 p-2 rounded">
                <strong>partner</strong> / partner123 (Partner)
              </div>
              <div className="bg-gray-50 p-2 rounded">
                <strong>obchodnik</strong> / sales123 (Obchodník)
              </div>
              <div className="bg-gray-50 p-2 rounded">
                <strong>Demo User</strong> / demo123 (Běžný uživatel)
              </div>
            </div>
            <p className="text-xs text-gray-500 mt-2">
              Můžete použít nickname nebo email pro přihlášení
            </p>
            
            {/* Debug tlačítka */}
            <div className="mt-4 pt-4 border-t border-gray-100">
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={async () => {
                    try {
                      console.log('=== GET DEBUG TEST ===');
                      const response = await fetch('auth.php', { credentials: 'same-origin' });
                      console.log('GET Status:', response.status);
                      console.log('GET Headers:', Object.fromEntries(response.headers));
                      const text = await response.text();
                      console.log('GET Response length:', text.length);
                      console.log('GET Response:', text);
                      
                      if (response.status === 404) {
                        alert('❌ 404: auth.php neexistuje nebo není dostupný!');
                      } else if (text.trim() === '') {
                        alert('❌ Auth.php existuje, ale vrací prázdnou odpověď!');
                      } else {
                        alert('✅ Auth.php odpověděl! Zkontrolujte console (F12).');
                      }
                    } catch (error) {
                      console.error('GET test error:', error);
                      alert('❌ GET test selhal: ' + error.message);
                    }
                  }}
                  className="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 py-2 px-3 rounded transition-colors"
                >
                  🌐 GET Test
                </button>
                
                <button
                  type="button"
                  onClick={async () => {
                    try {
                      console.log('=== POST DEBUG TEST ===');
                      const response = await fetch('auth.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'debug' })
                      });
                      console.log('POST Status:', response.status);
                      console.log('POST Headers:', Object.fromEntries(response.headers));
                      const text = await response.text();
                      console.log('POST Response length:', text.length);
                      console.log('POST Response:', text);
                      
                      if (text.trim() === '') {
                        alert('❌ PROBLÉM: Server vrací prázdnou odpověď při POST!');
                      } else {
                        try {
                          const data = JSON.parse(text);
                          console.log('Parsed JSON:', data);
                          alert('✅ POST funguje! JSON je validní. Zkontrolujte console.');
                        } catch (e) {
                          alert('⚠️ POST odpověděl, ale JSON není validní. Zkontrolujte console.');
                        }
                      }
                    } catch (error) {
                      console.error('POST test error:', error);
                      alert('❌ POST test selhal: ' + error.message);
                    }
                  }}
                  className="text-xs bg-yellow-100 hover:bg-yellow-200 text-yellow-800 py-2 px-3 rounded transition-colors"
                >
                  � POST Debug
                </button>
              </div>
              
              <button
                type="button"
                onClick={async () => {
                  try {
                    console.log('=== LOGIN TEST ===');
                    const response = await fetch('auth.php', {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ 
                        action: 'login', 
                        username: 'admin', 
                        password: 'admin123' 
                      })
                    });
                    console.log('Login Status:', response.status);
                    const text = await response.text();
                    console.log('Login Response length:', text.length);
                    console.log('Login Response:', text);
                    
                    if (text.trim() === '') {
                      alert('❌ KRITICKÉ: Login vrací prázdnou odpověď! Toto je váš hlavní problém.');
                    } else {
                      try {
                        const data = JSON.parse(text);
                        if (data.success) {
                          alert(`✅ Login test úspěšný! Uživatel: ${data.user.name}`);
                        } else {
                          alert(`❌ Login selhal: ${data.error}`);
                        }
                      } catch (e) {
                        alert('⚠️ Login odpověděl, ale JSON není validní.');
                      }
                    }
                  } catch (error) {
                    console.error('Login test error:', error);
                    alert('❌ Login test selhal: ' + error.message);
                  }
                }}
                className="w-full text-xs bg-green-100 hover:bg-green-200 text-green-800 py-2 px-3 rounded transition-colors mt-2"
              >
                🔐 Test Login (admin/admin123)
              </button>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center mt-6">
          <p className="text-sm text-gray-600">
            © 2025 Electree - Bateriová úložiště
          </p>
        </div>
      </div>
    </div>
  )
}

export default Login
