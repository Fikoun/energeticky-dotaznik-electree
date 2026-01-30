import React, { useState, useEffect } from 'react';
import { WifiOff, Wifi, Send, AlertCircle, CheckCircle } from 'lucide-react';

const OfflineNotification = ({ isOnline }) => {
  const [pendingForms, setPendingForms] = useState([]);
  const [lastSyncAttempt, setLastSyncAttempt] = useState(null);
  const [isSyncing, setIsSyncing] = useState(false);

  // Check for pending forms on component mount and when going online
  useEffect(() => {
    checkPendingForms();
  }, []);

  useEffect(() => {
    if (isOnline && pendingForms.length > 0) {
      // Auto-sync when going online
      setTimeout(() => {
        syncPendingForms();
      }, 2000);
    }
  }, [isOnline, pendingForms.length]);

  const checkPendingForms = () => {
    try {
      const queue = JSON.parse(localStorage.getItem('formSubmissionQueue') || '[]');
      setPendingForms(queue.filter(item => item.status === 'pending'));
    } catch (error) {
      console.error('Error checking pending forms:', error);
    }
  };

  const syncPendingForms = async () => {
    if (!isOnline || isSyncing) return;

    setIsSyncing(true);
    setLastSyncAttempt(new Date());

    try {
      const queue = JSON.parse(localStorage.getItem('formSubmissionQueue') || '[]');
      const pendingItems = queue.filter(item => item.status === 'pending');
      
      let successCount = 0;
      const updatedQueue = [...queue];

      for (let i = 0; i < pendingItems.length; i++) {
        const item = pendingItems[i];
        
        try {
          const response = await fetch('/public/submit-form.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(item),
          });

          if (response.ok) {
            // Mark as synced
            const queueIndex = updatedQueue.findIndex(queueItem => 
              queueItem.timestamp === item.timestamp
            );
            if (queueIndex !== -1) {
              updatedQueue[queueIndex].status = 'synced';
              updatedQueue[queueIndex].syncedAt = new Date().toISOString();
            }
            successCount++;
          } else {
            throw new Error(`Server responded with status ${response.status}`);
          }
        } catch (error) {
          console.error('Error syncing form:', error);
          // Mark as failed
          const queueIndex = updatedQueue.findIndex(queueItem => 
            queueItem.timestamp === item.timestamp
          );
          if (queueIndex !== -1) {
            updatedQueue[queueIndex].status = 'failed';
            updatedQueue[queueIndex].error = error.message;
          }
        }
      }

      // Update localStorage
      localStorage.setItem('formSubmissionQueue', JSON.stringify(updatedQueue));
      
      // Update pending forms
      checkPendingForms();

      if (successCount > 0) {
        // Show success notification
        console.log(`Successfully synced ${successCount} forms`);
      }

    } catch (error) {
      console.error('Error during sync:', error);
    } finally {
      setIsSyncing(false);
    }
  };

  const clearSyncedForms = () => {
    try {
      const queue = JSON.parse(localStorage.getItem('formSubmissionQueue') || '[]');
      const filteredQueue = queue.filter(item => item.status !== 'synced');
      localStorage.setItem('formSubmissionQueue', JSON.stringify(filteredQueue));
      checkPendingForms();
    } catch (error) {
      console.error('Error clearing synced forms:', error);
    }
  };

  if (isOnline && pendingForms.length === 0) {
    return null; // Don't show anything when online and no pending forms
  }

  return (
    <div className="mb-6">
      {/* Offline Status */}
      {!isOnline && (
        <div className="bg-orange-100 border border-orange-400 text-orange-800 px-4 py-3 rounded-lg mb-4">
          <div className="flex items-center">
            <WifiOff className="h-5 w-5 mr-2" />
            <div className="flex-1">
              <p className="font-medium">Offline režim</p>
              <p className="text-sm">
                Nejste připojeni k internetu. Formulář bude uložen lokálně a odeslán po obnovení připojení.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Online Status with Pending Forms */}
      {isOnline && pendingForms.length > 0 && (
        <div className="bg-blue-100 border border-blue-400 text-blue-800 px-4 py-3 rounded-lg mb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center flex-1">
              <Wifi className="h-5 w-5 mr-2" />
              <div>
                <p className="font-medium">Připojení obnoveno</p>
                <p className="text-sm">
                  Máte {pendingForms.length} neodeslané{pendingForms.length === 1 ? 'ý' : pendingForms.length < 5 ? 'é' : 'ých'} formulář{pendingForms.length === 1 ? '' : pendingForms.length < 5 ? 'e' : 'ů'} čekající{pendingForms.length === 1 ? 'cí' : 'ch'} na odeslání.
                </p>
              </div>
            </div>
            <button
              onClick={syncPendingForms}
              disabled={isSyncing}
              className="ml-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
            >
              {isSyncing ? (
                <>
                  <div className="animate-spin h-4 w-4 mr-2 border-2 border-white border-t-transparent rounded-full"></div>
                  Odesílám...
                </>
              ) : (
                <>
                  <Send className="h-4 w-4 mr-2" />
                  Odeslat nyní
                </>
              )}
            </button>
          </div>
        </div>
      )}

      {/* Sync Status */}
      {lastSyncAttempt && (
        <div className="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">
          <div className="flex items-center justify-between">
            <span>
              Poslední pokus o synchronizaci: {lastSyncAttempt.toLocaleTimeString('cs-CZ')}
            </span>
            {pendingForms.length === 0 ? (
              <div className="flex items-center text-green-600">
                <CheckCircle className="h-4 w-4 mr-1" />
                Vše odesláno
              </div>
            ) : (
              <button
                onClick={clearSyncedForms}
                className="text-blue-600 hover:text-blue-800 underline"
              >
                Vymazat odeslané
              </button>
            )}
          </div>
        </div>
      )}

      {/* Detailed Pending Forms List (for development/debugging) */}
      {pendingForms.length > 0 && process.env.NODE_ENV === 'development' && (
        <details className="mt-2">
          <summary className="cursor-pointer text-sm text-gray-600 hover:text-gray-800">
            Zobrazit detail čekajících formulářů ({pendingForms.length})
          </summary>
          <div className="mt-2 bg-gray-50 border border-gray-200 rounded p-3 text-xs">
            {pendingForms.map((form, index) => (
              <div key={index} className="mb-2 p-2 bg-white rounded border">
                <div><strong>Časové razítko:</strong> {new Date(form.timestamp).toLocaleString('cs-CZ')}</div>
                <div><strong>Status:</strong> {form.status}</div>
                {form.contactInfo?.email && (
                  <div><strong>Email:</strong> {form.contactInfo.email}</div>
                )}
              </div>
            ))}
          </div>
        </details>
      )}
    </div>
  );
};

export default OfflineNotification;
