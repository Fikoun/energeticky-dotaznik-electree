import { useState, useCallback, useEffect, useRef, useMemo } from 'react'

// Generate a stable session ID for temp file uploads
const getSessionTempId = () => {
  const key = 'batteryForm_tempFormId'
  let tempId = sessionStorage.getItem(key)
  if (!tempId) {
    tempId = `temp_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
    sessionStorage.setItem(key, tempId)
  }
  return tempId
}

// Clear session temp ID (call this when form is submitted or reset)
export const clearSessionTempId = () => {
  sessionStorage.removeItem('batteryForm_tempFormId')
}

export const useFileUpload = (formId, fieldName) => {
  const [uploadedFiles, setUploadedFiles] = useState([])
  const [isUploading, setIsUploading] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [uploadError, setUploadError] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  
  // Track the actual formId used for uploads (may be temp if formId is null)
  const effectiveFormIdRef = useRef(null)
  
  // Compute the effective formId - use real formId if available, otherwise session temp ID
  const effectiveFormId = useMemo(() => {
    if (formId && formId !== '' && !formId.startsWith('temp_')) {
      return formId
    }
    // Use stable session-based temp ID
    return getSessionTempId()
  }, [formId])
  
  // Store the effective formId for reference
  effectiveFormIdRef.current = effectiveFormId

  // Sync with backend on mount and when formId changes - fetch existing files
  useEffect(() => {
    // Fetch files for any valid formId (including temp)
    if (effectiveFormId) {
      fetchExistingFiles()
    }
  }, [effectiveFormId, fieldName])

  // Fetch existing files from backend
  const fetchExistingFiles = useCallback(async () => {
    if (!effectiveFormId) return

    setIsLoading(true)
    try {
      const params = new URLSearchParams({
        formId: effectiveFormId,
        fieldName
      })
      
      const response = await fetch(`/public/get-files.php?${params}`)
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()
      
      if (result.success && result.files) {
        setUploadedFiles(result.files)
      }
    } catch (error) {
      console.error('Error fetching existing files:', error)
      // Don't set error - this is a background sync
    } finally {
      setIsLoading(false)
    }
  }, [effectiveFormId, fieldName])

  const uploadFiles = useCallback(async (files) => {
    if (!files || files.length === 0) return

    setIsUploading(true)
    setUploadError(null)

    try {
      const formData = new FormData()
      // Always use effectiveFormId - this is either the real formId or the session temp ID
      formData.append('formId', effectiveFormId)
      formData.append('fieldName', fieldName)

      // Add all files to FormData
      for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i])
      }

      // Use the new unified upload endpoint
      const response = await fetch('/public/unified-upload.php', {
        method: 'POST',
        body: formData
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()

      if (!result.success) {
        throw new Error(result.error || 'Neznámá chyba při nahrávání')
      }

      // Show warnings if some files had errors
      if (result.errors && result.errors.length > 0) {
        console.warn('Some files had errors:', result.errors)
      }

      // Update uploaded files list
      setUploadedFiles(prev => [...prev, ...result.files])
      
      return result

    } catch (error) {
      console.error('File upload error:', error)
      setUploadError(error.message)
      throw error
    } finally {
      setIsUploading(false)
    }
  }, [effectiveFormId, fieldName])

  // Delete file from both frontend and backend
  const removeFile = useCallback(async (fileId) => {
    setIsDeleting(true)
    
    try {
      // Call backend to delete
      const response = await fetch('/public/delete-file.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          fileId,
          formId: effectiveFormId
        })
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()
      
      if (!result.success) {
        throw new Error(result.error || 'Chyba při mazání souboru')
      }

      // Remove from local state
      setUploadedFiles(prev => prev.filter(file => file.id !== fileId))
      
    } catch (error) {
      console.error('File deletion error:', error)
      // Still remove from local state if backend fails
      setUploadedFiles(prev => prev.filter(file => file.id !== fileId))
    } finally {
      setIsDeleting(false)
    }
  }, [effectiveFormId])

  const clearFiles = useCallback(() => {
    setUploadedFiles([])
    setUploadError(null)
  }, [])

  const getFileNames = useCallback(() => {
    return uploadedFiles.map(file => file.originalName).join(', ')
  }, [uploadedFiles])

  const getTotalSize = useCallback(() => {
    const totalBytes = uploadedFiles.reduce((sum, file) => sum + file.size, 0)
    return formatFileSize(totalBytes)
  }, [uploadedFiles])

  // Refresh files from backend
  const refreshFiles = useCallback(() => {
    fetchExistingFiles()
  }, [fetchExistingFiles])
  
  // Get the temp form ID that's being used (for migration purposes)
  const getTempFormId = useCallback(() => {
    return getSessionTempId()
  }, [])

  return {
    uploadedFiles,
    isUploading,
    isLoading,
    isDeleting,
    uploadError,
    uploadFiles,
    removeFile,
    clearFiles,
    getFileNames,
    getTotalSize,
    refreshFiles,
    hasFiles: uploadedFiles.length > 0,
    effectiveFormId,
    getTempFormId
  }
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 B'
  
  const units = ['B', 'KB', 'MB', 'GB']
  const factor = Math.floor(Math.log(bytes) / Math.log(1024))
  
  return Math.round(bytes / Math.pow(1024, factor) * 100) / 100 + ' ' + units[factor]
}
