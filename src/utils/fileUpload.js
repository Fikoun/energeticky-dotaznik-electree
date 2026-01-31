// File upload utility for battery form
export const uploadFiles = async (formData, formId) => {
  try {
    // Extract all file fields from form data
    const fileFields = [
      'sitePhotos',
      'visualizations', 
      'projectDocumentationFiles',
      'distributionCurvesFile',
      'billingDocuments',
      'cogenerationPhotos'
    ];
    
    const formDataToUpload = new FormData();
    formDataToUpload.append('formId', formId);
    
    let hasFiles = false;
    
    // Process each file field
    fileFields.forEach(fieldName => {
      const files = formData[fieldName];
      
      if (files && files instanceof FileList && files.length > 0) {
        hasFiles = true;
        
        // Add each file to FormData
        for (let i = 0; i < files.length; i++) {
          formDataToUpload.append(`${fieldName}[]`, files[i]);
        }
      } else if (files && files instanceof File) {
        hasFiles = true;
        formDataToUpload.append(fieldName, files);
      }
    });
    
    // If no files to upload, return success
    if (!hasFiles) {
      return {
        success: true,
        message: 'Žádné soubory k nahrání',
        uploadedFiles: {}
      };
    }
    
    console.log('Uploading files for form:', formId);
    
    // Upload files to server
    const response = await fetch('/public/upload-handler.php', {
      method: 'POST',
      body: formDataToUpload
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.error || 'Neznámá chyba při nahrávání souborů');
    }
    
    console.log('Files uploaded successfully:', result);
    return result;
    
  } catch (error) {
    console.error('File upload error:', error);
    throw new Error(`Chyba při nahrávání souborů: ${error.message}`);
  }
};

// Get uploaded file names for display
export const getUploadedFileNames = (uploadResult, fieldName) => {
  if (!uploadResult?.uploadedFiles?.[fieldName]) {
    return 'Žádné soubory';
  }
  
  const files = uploadResult.uploadedFiles[fieldName];
  
  if (Array.isArray(files)) {
    return files.map(f => f.originalName).join(', ');
  } else {
    return files.originalName;
  }
};
