// Utility functions for offline form data storage

const STORAGE_KEY = 'batteryFormData';
const QUEUE_KEY = 'formSubmissionQueue';

export const saveFormData = (data) => {
  try {
    // Add timestamp to track when data was saved
    const dataWithMeta = {
      ...data,
      _lastSaved: new Date().toISOString(),
      _version: '1.0'
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(dataWithMeta));
  } catch (error) {
    console.error('Error saving form data:', error);
  }
};

export const loadFormData = () => {
  try {
    const data = localStorage.getItem(STORAGE_KEY);
    if (!data) return null;
    
    const parsed = JSON.parse(data);
    
    // Check if data is not too old (24 hours)
    if (parsed._lastSaved) {
      const lastSaved = new Date(parsed._lastSaved);
      const now = new Date();
      const hoursDiff = (now - lastSaved) / (1000 * 60 * 60);
      
      if (hoursDiff > 24) {
        console.log('Form data is older than 24 hours, clearing...');
        clearFormData();
        return null;
      }
    }
    
    return parsed;
  } catch (error) {
    console.error('Error loading form data:', error);
    return null;
  }
};

export const clearFormData = () => {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (error) {
    console.error('Error clearing form data:', error);
  }
};

export const isOffline = () => {
  return !navigator.onLine;
};

export const getSubmissionQueue = () => {
  try {
    const queue = localStorage.getItem(QUEUE_KEY);
    return queue ? JSON.parse(queue) : [];
  } catch (error) {
    console.error('Error getting submission queue:', error);
    return [];
  }
};

export const addToSubmissionQueue = (data) => {
  try {
    const queue = getSubmissionQueue();
    const queueItem = {
      ...data,
      timestamp: new Date().toISOString(),
      status: 'pending',
      retryCount: 0,
      id: generateQueueId()
    };
    queue.push(queueItem);
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    
    // Trigger custom event for UI updates
    window.dispatchEvent(new CustomEvent('queueUpdated', { 
      detail: { queueLength: queue.length } 
    }));
    
    return queueItem.id;
  } catch (error) {
    console.error('Error adding to submission queue:', error);
    return null;
  }
};

export const removeFromSubmissionQueue = (index) => {
  try {
    const queue = getSubmissionQueue();
    queue.splice(index, 1);
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    
    window.dispatchEvent(new CustomEvent('queueUpdated', { 
      detail: { queueLength: queue.length } 
    }));
  } catch (error) {
    console.error('Error removing from submission queue:', error);
  }
};

export const updateQueueItemStatus = (id, status, error = null) => {
  try {
    const queue = getSubmissionQueue();
    const itemIndex = queue.findIndex(item => item.id === id);
    
    if (itemIndex !== -1) {
      queue[itemIndex].status = status;
      queue[itemIndex].lastAttempt = new Date().toISOString();
      
      if (error) {
        queue[itemIndex].error = error;
        queue[itemIndex].retryCount = (queue[itemIndex].retryCount || 0) + 1;
      }
      
      if (status === 'synced') {
        queue[itemIndex].syncedAt = new Date().toISOString();
      }
      
      localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
      
      window.dispatchEvent(new CustomEvent('queueUpdated', { 
        detail: { queueLength: queue.filter(item => item.status === 'pending').length } 
      }));
    }
  } catch (error) {
    console.error('Error updating queue item status:', error);
  }
};

export const processSubmissionQueue = async () => {
  if (isOffline()) {
    console.log('Still offline, skipping queue processing');
    return 0;
  }

  const queue = getSubmissionQueue();
  const pendingItems = queue.filter(item => item.status === 'pending');
  
  if (pendingItems.length === 0) {
    return 0;
  }

  console.log(`Processing ${pendingItems.length} pending form submissions...`);
  let processedCount = 0;

  for (const item of pendingItems) {
    // Skip items that have failed too many times
    if (item.retryCount >= 3) {
      updateQueueItemStatus(item.id, 'failed', 'Max retry attempts exceeded');
      continue;
    }

    try {
      const response = await fetch('/public/submit-form.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(item),
      });

      if (response.ok) {
        const result = await response.json();
        updateQueueItemStatus(item.id, 'synced');
        processedCount++;
        
        console.log(`Successfully processed form submission ${item.id}`);
      } else {
        const errorText = await response.text();
        updateQueueItemStatus(item.id, 'pending', `Server error: ${response.status} - ${errorText}`);
      }
    } catch (error) {
      console.error('Error processing queued submission:', error);
      updateQueueItemStatus(item.id, 'pending', error.message);
    }
    
    // Add delay between requests to avoid overwhelming server
    await new Promise(resolve => setTimeout(resolve, 1000));
  }

  return processedCount;
};

// Auto-process queue when going online
export const initializeOfflineSupport = () => {
  // Process queue immediately if online
  if (!isOffline()) {
    setTimeout(processSubmissionQueue, 2000);
  }

  // Listen for online/offline events
  window.addEventListener('online', () => {
    console.log('Connection restored, processing submission queue...');
    setTimeout(processSubmissionQueue, 2000);
  });

  window.addEventListener('offline', () => {
    console.log('Connection lost, enabling offline mode...');
  });

  // Clean up old queue items (older than 7 days)
  cleanupOldQueueItems();
};

// Helper functions
const generateQueueId = () => {
  return Date.now().toString(36) + Math.random().toString(36).substr(2);
};

const cleanupOldQueueItems = () => {
  try {
    const queue = getSubmissionQueue();
    const oneWeekAgo = new Date();
    oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);

    const filteredQueue = queue.filter(item => {
      const itemDate = new Date(item.timestamp);
      return itemDate > oneWeekAgo;
    });

    if (filteredQueue.length !== queue.length) {
      localStorage.setItem(QUEUE_KEY, JSON.stringify(filteredQueue));
      console.log(`Cleaned up ${queue.length - filteredQueue.length} old queue items`);
    }
  } catch (error) {
    console.error('Error cleaning up old queue items:', error);
  }
};

// Export for debugging
export const getQueueStats = () => {
  const queue = getSubmissionQueue();
  return {
    total: queue.length,
    pending: queue.filter(item => item.status === 'pending').length,
    synced: queue.filter(item => item.status === 'synced').length,
    failed: queue.filter(item => item.status === 'failed').length
  };
};
