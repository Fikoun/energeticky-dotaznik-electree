import { useState, useEffect, useRef } from 'react'

// Storage key for persisting form ID across page reloads
const FORM_ID_STORAGE_KEY = 'batteryForm_currentFormId'

// Get the session temp form ID (for file upload migration)
const getSessionTempFormId = () => {
  return sessionStorage.getItem('batteryForm_tempFormId') || null
}

// Get persisted form ID from localStorage
const getPersistedFormId = () => {
  try {
    const stored = localStorage.getItem(FORM_ID_STORAGE_KEY)
    if (stored) {
      const parsed = JSON.parse(stored)
      // Check if the stored formId is still valid (not older than 24 hours)
      if (parsed.timestamp) {
        const hoursDiff = (Date.now() - parsed.timestamp) / (1000 * 60 * 60)
        if (hoursDiff < 24) {
          return parsed.formId
        }
        // Expired, clear it
        localStorage.removeItem(FORM_ID_STORAGE_KEY)
      }
    }
  } catch (error) {
    console.error('Error loading persisted formId:', error)
  }
  return null
}

// Persist form ID to localStorage
const persistFormId = (formId) => {
  try {
    if (formId) {
      localStorage.setItem(FORM_ID_STORAGE_KEY, JSON.stringify({
        formId,
        timestamp: Date.now()
      }))
    }
  } catch (error) {
    console.error('Error persisting formId:', error)
  }
}

// Clear persisted form ID (called on form submission or new form)
export const clearPersistedFormId = () => {
  try {
    localStorage.removeItem(FORM_ID_STORAGE_KEY)
  } catch (error) {
    console.error('Error clearing persisted formId:', error)
  }
}

const useAutoSave = (formMethods, user, currentStep, delay = 3000) => {
  const [isSaving, setIsSaving] = useState(false)
  const [lastSaved, setLastSaved] = useState(null)
  // Initialize formId from localStorage to persist across page reloads
  const [formId, setFormIdState] = useState(() => getPersistedFormId())
  const [saveError, setSaveError] = useState(null)
  const [isDisabled, setIsDisabled] = useState(false) // Flag to disable auto-save after submission
  const saveTimeoutRef = useRef(null)
  
  // Wrapper to persist formId when it changes
  const setFormId = (newFormId) => {
    setFormIdState(newFormId)
    if (newFormId) {
      persistFormId(newFormId)
    }
  }

  // Function to disable auto-save and clear pending saves
  const disableAutoSave = () => {
    console.log('AutoSave: Disabling auto-save')
    setIsDisabled(true)
    if (saveTimeoutRef.current) {
      clearTimeout(saveTimeoutRef.current)
      saveTimeoutRef.current = null
    }
  }

  // Function to re-enable auto-save (for new forms)
  const enableAutoSave = () => {
    console.log('AutoSave: Re-enabling auto-save')
    setIsDisabled(false)
  }

  useEffect(() => {
    if (!user || !formMethods || isDisabled) {
      console.log('AutoSave: Missing user, formMethods, or disabled', { 
        user: !!user, 
        formMethods: !!formMethods,
        isDisabled 
      })
      return
    }

    console.log('AutoSave: Setting up watch for user:', user.id)

    const subscription = formMethods.watch((data, { name, type }) => {
      // Don't auto-save if disabled
      if (isDisabled) {
        console.log('AutoSave: Skipping - auto-save is disabled')
        return
      }
      
      console.log('AutoSave: Form changed', { field: name, type, hasData: !!data })
      
      // Clear existing timeout
      if (saveTimeoutRef.current) {
        clearTimeout(saveTimeoutRef.current)
      }

      // Set new timeout for saving
      saveTimeoutRef.current = setTimeout(async () => {
        console.log('AutoSave: Triggering save after delay')
        await saveFormDraft(data)
      }, delay)
    })

    return () => {
      console.log('AutoSave: Cleaning up subscription')
      subscription.unsubscribe()
      if (saveTimeoutRef.current) {
        clearTimeout(saveTimeoutRef.current)
      }
    }
  }, [formMethods, user, currentStep, delay, isDisabled])

  const saveFormDraft = async (data) => {
    if (!user || isSaving || isDisabled) {
      console.log('AutoSave: Skipping save', { hasUser: !!user, isSaving, isDisabled })
      return
    }

    console.log('AutoSave: Starting save process')
    setIsSaving(true)
    setSaveError(null)
    
    try {
      // Get temp form ID for file migration (if files were uploaded before first save)
      const tempFormId = getSessionTempFormId()
      
      const submissionData = {
        ...data,
        user: {
          id: user.id,
          name: user.fullName || user.name,
          email: user.email
        },
        isDraft: true,
        formId: formId,
        tempFormId: tempFormId, // Pass temp ID for file migration
        currentStep: currentStep,
        lastModified: new Date().toISOString()
      }

      console.log('AutoSave: Sending data to server', { 
        hasFormId: !!formId,
        tempFormId: tempFormId,
        userId: user.id, 
        currentStep,
        dataKeys: Object.keys(data).length 
      })

      const response = await fetch('/public/submit-form.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(submissionData)
      })

      console.log('AutoSave: Server response status:', response.status)

      if (response.ok) {
        const result = await response.json()
        console.log('AutoSave: Server response:', result)
        
        if (result.success && result.formId) {
          setFormId(result.formId)
          setLastSaved(new Date())
          console.log('AutoSave: Successfully saved with formId:', result.formId)
        } else {
          throw new Error(result.error || 'Neznámá chyba při ukládání')
        }
      } else {
        const errorText = await response.text()
        console.error('AutoSave: Server error response:', errorText)
        throw new Error(`Server error: ${response.status} - ${errorText}`)
      }
    } catch (error) {
      console.error('AutoSave: Failed to save draft:', error)
      setSaveError(error.message)
      
      // Show user-friendly error message
      if (!window.autoSaveErrorShown) {
        alert(`Chyba při automatickém ukládání: ${error.message}`)
        window.autoSaveErrorShown = true
        // Reset flag after 5 minutes
        setTimeout(() => { window.autoSaveErrorShown = false }, 300000)
      }
    } finally {
      setIsSaving(false)
    }
  }

  const saveManually = async () => {
    console.log('AutoSave: Manual save triggered')
    const data = formMethods.getValues()
    await saveFormDraft(data)
  }

  return {
    isSaving,
    lastSaved,
    formId,
    setFormId,
    saveManually,
    saveError,
    disableAutoSave,
    enableAutoSave,
    isDisabled
  }
}

export default useAutoSave
