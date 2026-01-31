import { useState, useCallback } from 'react'

export const useFileUpload = (formId, fieldName) => {
  const [uploadedFiles, setUploadedFiles] = useState([])
  const [isUploading, setIsUploading] = useState(false)
  const [uploadError, setUploadError] = useState(null)

  const uploadFiles = useCallback(async (files) => {
    if (!files || files.length === 0) return

    setIsUploading(true)
    setUploadError(null)

    try {
      const formData = new FormData()
      formData.append('formId', formId || `temp_${Date.now()}`)
      formData.append('fieldName', fieldName)

      // Add all files to FormData
      for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i])
      }

      const response = await fetch('/public/immediate-upload.php', {
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
  }, [formId, fieldName])

  const removeFile = useCallback((fileId) => {
    setUploadedFiles(prev => prev.filter(file => file.id !== fileId))
  }, [])

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

  return {
    uploadedFiles,
    isUploading,
    uploadError,
    uploadFiles,
    removeFile,
    clearFiles,
    getFileNames,
    getTotalSize,
    hasFiles: uploadedFiles.length > 0
  }
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 B'
  
  const units = ['B', 'KB', 'MB', 'GB']
  const factor = Math.floor(Math.log(bytes) / Math.log(1024))
  
  return Math.round(bytes / Math.pow(1024, factor) * 100) / 100 + ' ' + units[factor]
}
