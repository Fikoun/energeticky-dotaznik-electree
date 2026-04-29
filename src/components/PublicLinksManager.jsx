import { useState, useEffect } from 'react'
import { Link2, Plus, Copy, Check, Trash2, X, Lock, Mail, Calendar } from 'lucide-react'
import CreateLinkModal from './CreateLinkModal'

const PublicLinksManager = ({ user }) => {
  const [links, setLinks] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [copiedToken, setCopiedToken] = useState(null)

  useEffect(() => {
    loadLinks()
  }, [])

  const loadLinks = async () => {
    setIsLoading(true)
    try {
      const res = await fetch('/public/public-links-api.php?action=list', { credentials: 'include' })
      const result = await res.json()
      if (result.success) {
        setLinks(result.data)
      }
    } catch (err) {
      console.error('Failed to load links:', err)
    } finally {
      setIsLoading(false)
    }
  }

  const handleRevoke = async (linkId) => {
    if (!confirm('Opravdu chcete zneplatnit tento odkaz?')) return
    try {
      const res = await fetch('/public/public-links-api.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'revoke', link_id: linkId }),
      })
      const result = await res.json()
      if (result.success) {
        loadLinks()
      }
    } catch (err) {
      console.error('Failed to revoke link:', err)
    }
  }

  const copyToClipboard = (text, tokenId) => {
    navigator.clipboard.writeText(text).then(() => {
      setCopiedToken(tokenId)
      setTimeout(() => setCopiedToken(null), 2000)
    })
  }

  const getStatusBadge = (status) => {
    const styles = {
      active: 'bg-green-100 text-green-800',
      used: 'bg-blue-100 text-blue-800',
      expired: 'bg-gray-100 text-gray-800',
      revoked: 'bg-red-100 text-red-800',
    }
    const labels = {
      active: 'Aktivní',
      used: 'Použitý',
      expired: 'Vypršel',
      revoked: 'Zrušen',
    }
    return (
      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${styles[status] || styles.active}`}>
        {labels[status] || status}
      </span>
    )
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-2">
          <Link2 className="h-5 w-5 text-primary-600" />
          <h3 className="text-lg font-semibold text-gray-900">Veřejné odkazy na formulář</h3>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="btn-primary text-sm flex items-center gap-1"
        >
          <Plus className="h-4 w-4" />
          Vytvořit odkaz
        </button>
      </div>

      {isLoading ? (
        <div className="text-center py-8 text-gray-500">Načítám...</div>
      ) : links.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          <Link2 className="h-8 w-8 mx-auto mb-2 text-gray-400" />
          <p>Zatím nemáte žádné veřejné odkazy.</p>
          <p className="text-sm mt-1">Vytvořte odkaz pro externího uživatele, který nemá účet.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {links.map((link) => {
            const url = `${window.location.origin}/?public=${link.token}`
            return (
              <div key={link.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-100">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <Mail className="h-4 w-4 text-gray-400" />
                    <span className="font-medium text-gray-900 truncate">{link.recipient_email}</span>
                    {link.recipient_name && (
                      <span className="text-gray-500 text-sm">({link.recipient_name})</span>
                    )}
                    {getStatusBadge(link.status)}
                    {link.has_password && (
                      <Lock className="h-3.5 w-3.5 text-amber-500" title="Chráněno heslem" />
                    )}
                  </div>
                  {link.description && (
                    <p className="text-sm text-gray-500 truncate">{link.description}</p>
                  )}
                  <div className="flex items-center gap-3 mt-1 text-xs text-gray-400">
                    <span><Calendar className="inline h-3 w-3 mr-1" />Vytvořeno: {new Date(link.created_at).toLocaleDateString('cs-CZ')}</span>
                    {link.expires_at && (
                      <span>Vyprší: {new Date(link.expires_at).toLocaleDateString('cs-CZ')}</span>
                    )}
                    {link.used_at && (
                      <span>Použito: {new Date(link.used_at).toLocaleDateString('cs-CZ')}</span>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2 ml-4">
                  {link.status === 'active' && (
                    <>
                      <button
                        onClick={() => copyToClipboard(url, link.id)}
                        className="p-2 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                        title="Kopírovat odkaz"
                      >
                        {copiedToken === link.id ? (
                          <Check className="h-4 w-4 text-green-500" />
                        ) : (
                          <Copy className="h-4 w-4" />
                        )}
                      </button>
                      <button
                        onClick={() => handleRevoke(link.id)}
                        className="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        title="Zneplatnit odkaz"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}

      {showCreateModal && (
        <CreateLinkModal
          onClose={() => setShowCreateModal(false)}
          onCreated={() => {
            setShowCreateModal(false)
            loadLinks()
          }}
        />
      )}
    </div>
  )
}

export default PublicLinksManager
