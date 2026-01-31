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
    
    const allUploadedFiles = {};
    let hasAnyFiles = false;
    
    // Process each file field separately for better error handling
    for (const fieldName of fileFields) {
      const files = formData[fieldName];
      
      if (!files) continue;
      
      let filesToUpload = [];
      
      if (files instanceof FileList && files.length > 0) {
        filesToUpload = Array.from(files);
      } else if (files instanceof File) {
        filesToUpload = [files];
      } else if (Array.isArray(files) && files.length > 0) {
        // Already uploaded files (stored as objects with id, name, etc.)
        filesToUpload = files.filter(f => f instanceof File);
      }
      
      if (filesToUpload.length === 0) continue;
      
      hasAnyFiles = true;
      
      const formDataToUpload = new FormData();
      formDataToUpload.append('formId', formId);
      formDataToUpload.append('fieldName', fieldName);
      
      for (const file of filesToUpload) {
        formDataToUpload.append('files[]', file);
      }
      
      // Upload using the unified endpoint
      const response = await fetch('/public/unified-upload.php', {
        method: 'POST',
        body: formDataToUpload
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.error || `Chyba při nahrávání ${fieldName}`);
      }
      
      if (result.files && result.files.length > 0) {
        allUploadedFiles[fieldName] = result.files;
      }
    }
    
    // If no files to upload, return success
    if (!hasAnyFiles) {
      return {
        success: true,
        message: 'Žádné soubory k nahrání',
        uploadedFiles: {}
      };
    }
    
    console.log('Files uploaded successfully:', allUploadedFiles);
    return {
      success: true,
      uploadedFiles: allUploadedFiles,
      message: 'Soubory byly úspěšně nahrány'
    };
    
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
