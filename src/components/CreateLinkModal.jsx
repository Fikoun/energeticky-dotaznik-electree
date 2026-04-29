import { useState } from 'react'
import { Plus, Copy, Check, X, Lock, Mail, Calendar, User } from 'lucide-react'

/**
 * Standalone modal for creating a public form link.
 * Used from both TopBar and PublicLinksManager.
 *
 * Props:
 *   onClose()             – called when modal should close
 *   onCreated(link)?      – called after successful creation (optional)
 */
const CreateLinkModal = ({ onClose, onCreated }) => {
  const [email, setEmail] = useState('')
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [description, setDescription] = useState('')
  const [expiresInDays, setExpiresInDays] = useState(30)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [createdLink, setCreatedLink] = useState(null)
  const [copied, setCopied] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setError('')

    try {
      const res = await fetch('/public/public-links-api.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create',
          email,
          name,
          password: password || undefined,
          description: description || undefined,
          expires_in_days: expiresInDays,
        }),
      })
      const result = await res.json()
      if (result.success) {
        setCreatedLink(result.data)
        onCreated?.(result.data)
      } else {
        setError(result.error || 'Chyba při vytváření odkazu')
      }
    } catch (err) {
      setError('Chyba připojení k serveru')
    } finally {
      setIsSubmitting(false)
    }
  }

  const copyUrl = () => {
    if (createdLink?.url) {
      navigator.clipboard.writeText(createdLink.url).then(() => {
        setCopied(true)
        setTimeout(() => setCopied(false), 2000)
      })
    }
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">
            {createdLink ? 'Odkaz vytvořen' : 'Vygenerovat odkaz pro zákazníka'}
          </h3>
          <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded-lg">
            <X className="h-5 w-5 text-gray-500" />
          </button>
        </div>

        {createdLink ? (
          <div className="p-6 space-y-4">
            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
              <p className="text-green-800 font-medium mb-2">Odkaz byl úspěšně vytvořen!</p>
              <p className="text-sm text-green-700">
                Pošlete tento odkaz zákazníkovi <strong>{createdLink.email}</strong>. Po vyplnění formuláře mu přijde GDPR souhlas na email.
              </p>
            </div>

            <div>
              <label className="form-label">URL odkazu</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={createdLink.url}
                  readOnly
                  className="form-input flex-1 bg-gray-50 text-sm"
                />
                <button
                  onClick={copyUrl}
                  className="btn-primary flex items-center gap-1 text-sm whitespace-nowrap"
                >
                  {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                  {copied ? 'Zkopírováno' : 'Kopírovat'}
                </button>
              </div>
            </div>

            {password && (
              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p className="text-sm text-amber-800">
                  <Lock className="inline h-4 w-4 mr-1" />
                  Heslo: <strong>{password}</strong>
                </p>
                <p className="text-xs text-amber-600 mt-1">Sdílejte heslo bezpečně – SMS nebo osobně, ne emailem.</p>
              </div>
            )}

            <div className="flex justify-end pt-2">
              <button onClick={onClose} className="btn-primary">
                Zavřít
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="p-6 space-y-4">
            <div>
              <label className="form-label">
                <Mail className="inline h-4 w-4 mr-1" />
                Email zákazníka *
              </label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="form-input w-full"
                placeholder="jan.novak@example.cz"
                required
                autoFocus
              />
              <p className="text-xs text-gray-500 mt-1">Na tento email přijde zákazníkovi GDPR potvrzení po odeslání formuláře.</p>
            </div>

            <div>
              <label className="form-label">
                <User className="inline h-4 w-4 mr-1" />
                Jméno zákazníka
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="form-input w-full"
                placeholder="Jan Novák"
              />
              <p className="text-xs text-gray-500 mt-1">Předvyplní se v kontaktní osobě formuláře.</p>
            </div>

            <div>
              <label className="form-label">
                <Lock className="inline h-4 w-4 mr-1" />
                Heslo (volitelné)
              </label>
              <input
                type="text"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="form-input w-full"
                placeholder="Např. Electree2026"
              />
              <p className="text-xs text-gray-500 mt-1">Zákazník ho zadá při otevření odkazu. Sdílejte ho jiným kanálem než odkaz.</p>
            </div>

            <div>
              <label className="form-label">Poznámka (interní)</label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="form-input w-full"
                placeholder="Např. schůzka 5.5., přišel přes LinkedIn"
              />
            </div>

            <div>
              <label className="form-label">
                <Calendar className="inline h-4 w-4 mr-1" />
                Platnost odkazu
              </label>
              <select
                value={expiresInDays}
                onChange={(e) => setExpiresInDays(Number(e.target.value))}
                className="form-input w-full"
              >
                <option value={7}>7 dní</option>
                <option value={14}>14 dní</option>
                <option value={30}>30 dní</option>
                <option value={60}>60 dní</option>
                <option value={90}>90 dní</option>
              </select>
            </div>

            {error && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                {error}
              </div>
            )}

            <div className="flex justify-end gap-3 pt-2">
              <button type="button" onClick={onClose} className="btn-secondary">
                Zrušit
              </button>
              <button type="submit" disabled={isSubmitting} className="btn-primary flex items-center gap-1">
                {isSubmitting ? 'Vytvářím...' : (
                  <>
                    <Plus className="h-4 w-4" />
                    Vygenerovat odkaz
                  </>
                )}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}

export default CreateLinkModal
