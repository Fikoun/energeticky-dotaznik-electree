import { useRef, useState } from 'react'
import { Upload, X, File, Image, Loader2, RefreshCw, ZoomIn } from 'lucide-react'
import { useFileUpload } from '../hooks/useFileUpload'

// Image preview modal component
const ImagePreviewModal = ({ imageUrl, fileName, onClose }) => {
  if (!imageUrl) return null
  
  return (
    <div 
      className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4"
      onClick={onClose}
    >
      <div className="relative max-w-4xl max-h-full">
        <button
          onClick={onClose}
          className="absolute -top-10 right-0 text-white hover:text-gray-300"
        >
          <X className="h-8 w-8" />
        </button>
        <img 
          src={imageUrl} 
          alt={fileName}
          className="max-w-full max-h-[80vh] object-contain rounded-lg"
          onClick={(e) => e.stopPropagation()}
        />
        <p className="text-white text-center mt-2 text-sm">{fileName}</p>
      </div>
    </div>
  )
}

const FileUploadField = ({ 
  name, 
  label, 
  accept, 
  multiple = true, 
  formId, 
  register, 
  setValue,
  watch,
  helpText 
}) => {
  const fileInputRef = useRef(null)
  const [previewImage, setPreviewImage] = useState(null)
  
  const { 
    uploadedFiles, 
    isUploading,
    isLoading,
    isDeleting,
    uploadError, 
    uploadFiles, 
    removeFile, 
    refreshFiles,
    hasFiles,
    getTotalSize 
  } = useFileUpload(formId, name)

  const handleFileSelect = async (event) => {
    const files = event.target.files
    if (files && files.length > 0) {
      try {
        const result = await uploadFiles(files)
        
        // Update form data with uploaded file information
        setValue(name, uploadedFiles.map(f => ({
          id: f.id,
          name: f.originalName,
          size: f.size,
          uploaded: true
        })))
        
        // Clear the input so the same file can be selected again if needed
        if (fileInputRef.current) {
          fileInputRef.current.value = ''
        }
      } catch (error) {
        console.error('Upload failed:', error)
      }
    }
  }

  const handleRemoveFile = async (fileId) => {
    await removeFile(fileId)
    // Update form data
    const remainingFiles = uploadedFiles.filter(f => f.id !== fileId)
    setValue(name, remainingFiles.map(f => ({
      id: f.id,
      name: f.originalName,
      size: f.size,
      uploaded: true
    })))
  }

  const isImageFile = (file) => {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic']
    const extension = file.originalName?.split('.').pop()?.toLowerCase()
    return imageExtensions.includes(extension) || file.mimeType?.startsWith('image/')
  }

  const getFileIcon = (file) => {
    return isImageFile(file) ? Image : File
  }

  const handleImageClick = (file) => {
    if (isImageFile(file)) {
      // Use full URL if available, otherwise use thumbnail
      const imageUrl = file.url || file.thumbnailUrl
      if (imageUrl) {
        setPreviewImage({ url: imageUrl, name: file.originalName })
      }
    }
  }

  return (
    <div className="space-y-3">
      <label className="form-label">{label}</label>
      
      {/* File Input */}
      <div className="relative">
        <input
          ref={fileInputRef}
          type="file"
          accept={accept}
          multiple={multiple}
          onChange={handleFileSelect}
          className="hidden"
        />
        
        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isUploading || isLoading}
          className={`
            w-full border-2 border-dashed rounded-lg p-6 text-center transition-colors
            ${isUploading || isLoading
              ? 'border-blue-300 bg-blue-50 cursor-not-allowed' 
              : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50 cursor-pointer'
            }
          `}
        >
          {isLoading ? (
            <Loader2 className="h-8 w-8 mx-auto mb-2 text-blue-400 animate-spin" />
          ) : (
            <Upload className={`h-8 w-8 mx-auto mb-2 ${isUploading ? 'text-blue-400' : 'text-gray-400'}`} />
          )}
          <p className={`text-sm ${isUploading || isLoading ? 'text-blue-600' : 'text-gray-600'}`}>
            {isLoading ? 'Načítání souborů...' : isUploading ? 'Nahrávání...' : 'Klikněte nebo přetáhněte soubory'}
          </p>
          {!isUploading && !isLoading && (
            <p className="text-xs text-gray-500 mt-1">
              {accept ? `Podporované formáty: ${accept}` : 'Všechny formáty'}
            </p>
          )}
        </button>
      </div>

      {/* Error Message */}
      {uploadError && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-3">
          <p className="text-red-700 text-sm">Chyba: {uploadError}</p>
        </div>
      )}

      {/* Uploaded Files List */}
      {hasFiles && (
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <div className="flex items-center justify-between mb-3">
            <h4 className="font-medium text-gray-900">Nahrané soubory ({uploadedFiles.length})</h4>
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-600">Celkem: {getTotalSize()}</span>
              <button
                type="button"
                onClick={refreshFiles}
                className="text-gray-500 hover:text-gray-700 p-1"
                title="Obnovit seznam souborů"
              >
                <RefreshCw className="h-4 w-4" />
              </button>
            </div>
          </div>
          
          <div className="space-y-2">
            {uploadedFiles.map((file) => {
              const FileIcon = getFileIcon(file)
              const hasThumb = isImageFile(file) && file.thumbnailUrl
              
              return (
                <div key={file.id} className="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-3">
                  <div className="flex items-center space-x-3">
                    {/* Thumbnail or Icon */}
                    {hasThumb ? (
                      <div 
                        className="relative w-12 h-12 rounded overflow-hidden cursor-pointer group"
                        onClick={() => handleImageClick(file)}
                      >
                        <img 
                          src={file.thumbnailUrl} 
                          alt={file.originalName}
                          className="w-full h-full object-cover"
                        />
                        <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 flex items-center justify-center transition-all">
                          <ZoomIn className="h-5 w-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                      </div>
                    ) : (
                      <div 
                        className={`w-12 h-12 rounded bg-gray-100 flex items-center justify-center ${isImageFile(file) && file.url ? 'cursor-pointer' : ''}`}
                        onClick={() => handleImageClick(file)}
                      >
                        <FileIcon className="h-6 w-6 text-gray-500" />
                      </div>
                    )}
                    
                    <div>
                      <p className="text-sm font-medium text-gray-900">{file.originalName}</p>
                      <p className="text-xs text-gray-500">{file.formattedSize}</p>
                    </div>
                  </div>
                  
                  <button
                    type="button"
                    onClick={() => handleRemoveFile(file.id)}
                    disabled={isDeleting}
                    className={`p-1 ${isDeleting ? 'text-gray-400' : 'text-red-500 hover:text-red-700'}`}
                    title="Odstranit soubor"
                  >
                    {isDeleting ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <X className="h-4 w-4" />
                    )}
                  </button>
                </div>
              )
            })}
          </div>
        </div>
      )}

      {/* Help Text */}
      {helpText && (
        <p className="text-xs text-gray-500">{helpText}</p>
      )}

      {/* Image Preview Modal */}
      {previewImage && (
        <ImagePreviewModal
          imageUrl={previewImage.url}
          fileName={previewImage.name}
          onClose={() => setPreviewImage(null)}
        />
      )}
    </div>
  )
}

export default FileUploadField
