import { useState, useEffect, useRef } from 'react'

const useAutoSave = (formMethods, user, currentStep, delay = 3000) => {
  const [isSaving, setIsSaving] = useState(false)
  const [lastSaved, setLastSaved] = useState(null)
  const [formId, setFormId] = useState(null)
  const [saveError, setSaveError] = useState(null)
  const saveTimeoutRef = useRef(null)

  useEffect(() => {
    if (!user || !formMethods) {
      console.log('AutoSave: Missing user or formMethods', { user: !!user, formMethods: !!formMethods })
      return
    }

    console.log('AutoSave: Setting up watch for user:', user.id)

    const subscription = formMethods.watch((data, { name, type }) => {
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
  }, [formMethods, user, currentStep, delay])

  const saveFormDraft = async (data) => {
    if (!user || isSaving) {
      console.log('AutoSave: Skipping save', { hasUser: !!user, isSaving })
      return
    }

    console.log('AutoSave: Starting save process')
    setIsSaving(true)
    setSaveError(null)
    
    try {
      const submissionData = {
        ...data,
        user: {
          id: user.id,
          name: user.fullName || user.name,
          email: user.email
        },
        isDraft: true,
        formId: formId,
        currentStep: currentStep,
        lastModified: new Date().toISOString()
      }

      console.log('AutoSave: Sending data to server', { 
        hasFormId: !!formId, 
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
    saveError
  }
}

export default useAutoSave
