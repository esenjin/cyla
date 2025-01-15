import React, { useState, useRef } from 'react';
import { X, Upload, CheckCircle, AlertCircle } from 'lucide-react';

const UploadZone = () => {
  const [isDragging, setIsDragging] = useState(false);
  const [files, setFiles] = useState([]);
  const fileInputRef = useRef(null);

  const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 Mo
  const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webm', 'mp4', 'wmv', 'mp3', 'flac', 'ogg', 'zip', 'css', 'pdf', 'rar', 'm3u', 'm3u8', 'txt'];

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const validateFile = (file) => {
    const extension = file.name.split('.').pop().toLowerCase();
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
      return { valid: false, error: `Extension .${extension} non autorisée` };
    }
    if (file.size > MAX_FILE_SIZE) {
      return { valid: false, error: 'Fichier trop volumineux (max 100 Mo)' };
    }
    return { valid: true, error: null };
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    
    const droppedFiles = Array.from(e.dataTransfer.files);
    addFiles(droppedFiles);
  };

  const handleFileSelect = (e) => {
    const selectedFiles = Array.from(e.target.files);
    addFiles(selectedFiles);
  };

  const addFiles = (newFiles) => {
    const processedFiles = newFiles.map(file => {
      const validation = validateFile(file);
      return {
        file,
        id: Math.random().toString(36).substring(7),
        status: validation.valid ? 'pending' : 'error',
        error: validation.error,
        progress: 0
      };
    });

    setFiles(currentFiles => [...currentFiles, ...processedFiles]);
  };

  const removeFile = (fileId) => {
    setFiles(files => files.filter(f => f.id !== fileId));
  };

  const uploadFile = async (fileInfo) => {
    const formData = new FormData();
    formData.append('file', fileInfo.file);
    formData.append('action', 'upload');
    
    try {
      // Simuler un upload progressif pour la démo
      await new Promise(resolve => {
        let progress = 0;
        const interval = setInterval(() => {
          progress += 10;
          setFiles(files => 
            files.map(f => 
              f.id === fileInfo.id 
                ? { ...f, progress, status: progress === 100 ? 'complete' : 'uploading' }
                : f
            )
          );
          if (progress >= 100) {
            clearInterval(interval);
            resolve();
          }
        }, 500);
      });
    } catch (error) {
      setFiles(files =>
        files.map(f =>
          f.id === fileInfo.id
            ? { ...f, status: 'error', error: 'Erreur lors du téléversement' }
            : f
        )
      );
    }
  };

  const uploadAllFiles = () => {
    const pendingFiles = files.filter(f => f.status === 'pending');
    pendingFiles.forEach(uploadFile);
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'complete': return 'text-green-500';
      case 'error': return 'text-red-500';
      case 'uploading': return 'text-blue-500';
      default: return 'text-gray-500';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'complete': return <CheckCircle className="w-5 h-5" />;
      case 'error': return <AlertCircle className="w-5 h-5" />;
      default: return null;
    }
  };

  return (
    <div className="w-full">
      <div
        className={`relative border-2 border-dashed rounded-lg p-8 text-center mb-4 transition-colors
          ${isDragging ? 'border-primary-500 bg-primary-50' : 'border-gray-600'}
          hover:border-primary-500`}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
      >
        <input
          ref={fileInputRef}
          type="file"
          multiple
          onChange={handleFileSelect}
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
          Max 100 Mo par fichier · {ALLOWED_EXTENSIONS.join(', ')}
        </p>
      </div>

      {files.length > 0 && (
        <div className="border border-gray-700 rounded-lg overflow-hidden">
          <div className="p-4 bg-gray-800 border-b border-gray-700 flex justify-between items-center">
            <h3 className="font-medium">Files ({files.length})</h3>
            <button
              onClick={uploadAllFiles}
              className="btn btn-sm bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded"
            >
              Tout téléverser
            </button>
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

                {fileInfo.status === 'uploading' && (
                  <div className="w-32 h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-blue-500 transition-all duration-300"
                      style={{ width: `${fileInfo.progress}%` }}
                    />
                  </div>
                )}

                <div className={getStatusColor(fileInfo.status)}>
                  {getStatusIcon(fileInfo.status)}
                </div>

                <button
                  onClick={() => removeFile(fileInfo.id)}
                  className="p-1 hover:bg-gray-700 rounded"
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