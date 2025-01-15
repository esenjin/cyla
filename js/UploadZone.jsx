import React, { useState, useRef, useCallback } from 'react';
import { X, Upload, CheckCircle, AlertCircle, Loader } from 'lucide-react';

const UploadZone = () => {
  const [isDragging, setIsDragging] = useState(false);
  const [files, setFiles] = useState([]);
  const fileInputRef = useRef(null);

  const MAX_FILES = 10; // Limite stricte à 10 fichiers
  const MAX_FILE_SIZE = 100 * 1024 * 1024;
  const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'webm', 'mp4', 'wmv', 'mp3', 'flac', 'ogg', 'zip', 'css', 'pdf', 'rar', 'm3u', 'm3u8', 'txt'];

  const validateFile = useCallback((file) => {
    const extension = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
      return { valid: false, error: `Extension .${extension} non autorisée` };
    }
    if (file.size > MAX_FILE_SIZE) {
      return { valid: false, error: 'Fichier trop volumineux (max 100 Mo)' };
    }
    return { valid: true, error: null };
  }, []);

  const uploadFile = async (fileInfo) => {
    try {
      const formData = new FormData();
      formData.append('file', fileInfo.file);
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      // Mettre le statut en uploading
      setFiles(files =>
        files.map(f =>
          f.id === fileInfo.id ? { ...f, status: 'uploading' } : f
        )
      );

      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Erreur lors du téléversement');
      }

      setFiles(files => 
        files.map(f => 
          f.id === fileInfo.id ? { ...f, status: 'complete' } : f
        )
      );
    } catch (error) {
      setFiles(files =>
        files.map(f =>
          f.id === fileInfo.id
            ? { ...f, status: 'error', error: error.message }
            : f
        )
      );
    }
  };

  const handleFiles = (newFiles) => {
    // Vérifier si on n'a pas déjà atteint la limite
    if (files.length >= MAX_FILES) {
      alert(`Vous pouvez téléverser uniquement ${MAX_FILES} fichiers à la fois`);
      return;
    }

    // Calculer combien de fichiers on peut encore ajouter
    const remainingSlots = MAX_FILES - files.length;
    const filesToAdd = Array.from(newFiles).slice(0, remainingSlots);

    if (filesToAdd.length < newFiles.length) {
      alert(`Seuls les ${remainingSlots} premiers fichiers seront traités. Maximum ${MAX_FILES} fichiers à la fois.`);
    }

    const processedFiles = filesToAdd.map(file => ({
      file,
      id: Math.random().toString(36).substring(7),
      status: validateFile(file).valid ? 'pending' : 'error',
      error: validateFile(file).error
    }));

    setFiles(current => [...current, ...processedFiles]);

    // Lancer l'upload pour chaque fichier valide
    processedFiles
      .filter(f => f.status === 'pending')
      .forEach(uploadFile);
  };

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, []);

  const removeFile = (id) => {
    setFiles(files => files.filter(f => f.id !== id));
  };

  return (
    <div className="w-full">
      <div
        className={`relative border-2 border-dashed rounded-lg p-8 text-center mb-4 transition-colors
          ${isDragging ? 'border-primary-500 bg-primary-50' : 'border-gray-600'}
          hover:border-primary-500`}
        onDragOver={e => {
          e.preventDefault();
          setIsDragging(true);
        }}
        onDragLeave={e => {
          e.preventDefault();
          setIsDragging(false);
        }}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
      >
        <input
          ref={fileInputRef}
          type="file"
          multiple
          onChange={e => handleFiles(e.target.files)}
          className="hidden"
          accept={ALLOWED_EXTENSIONS.map(ext => `.${ext}`).join(',')}
        />
        
        <Upload className="w-12 h-12 mx-auto mb-4 text-gray-400" />
        <p className="text-lg mb-2">
          Glissez-déposez vos fichiers ici
        </p>
        <p className="text-sm text-gray-400">
          ou cliquez pour sélectionner des fichiers
        </p>
        <p className="text-xs text-gray-400 mt-2">
          Maximum {MAX_FILES} fichiers à la fois · 100 Mo par fichier
          <br />
          Extensions : {ALLOWED_EXTENSIONS.join(', ')}
        </p>
      </div>

      {files.length > 0 && (
        <div className="border border-gray-700 rounded-lg overflow-hidden">
          <div className="p-4 bg-gray-800 border-b border-gray-700">
            <h3 className="font-medium">Fichiers ({files.length}/{MAX_FILES})</h3>
          </div>

          <div className="divide-y divide-gray-700">
            {files.map((fileInfo) => (
              <div key={fileInfo.id} className="p-4 flex items-center gap-4">
                <div className="flex-1 min-w-0">
                  <p className="truncate font-medium">{fileInfo.file.name}</p>
                  <p className="text-sm text-gray-400">
                    {(fileInfo.file.size / 1024 / 1024).toFixed(2)} Mo
                  </p>
                  {fileInfo.error && (
                    <p className="text-sm text-red-500">{fileInfo.error}</p>
                  )}
                </div>

                <div className={`
                  ${fileInfo.status === 'complete' ? 'text-green-500' : ''}
                  ${fileInfo.status === 'error' ? 'text-red-500' : ''}
                  ${fileInfo.status === 'uploading' ? 'text-blue-500' : ''}
                `}>
                  {fileInfo.status === 'complete' && <CheckCircle className="w-5 h-5" />}
                  {fileInfo.status === 'error' && <AlertCircle className="w-5 h-5" />}
                  {fileInfo.status === 'uploading' && <Loader className="w-5 h-5 animate-spin" />}
                </div>

                <button
                  onClick={() => removeFile(fileInfo.id)}
                  className="p-1 hover:bg-gray-700 rounded"
                  title={fileInfo.status === 'uploading' ? 'Annuler' : 'Supprimer'}
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default UploadZone;