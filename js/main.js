// Fonction pour copier dans le presse-papier
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copié dans le presse-papier !');
    }).catch(err => {
        console.error('Erreur lors de la copie :', err);
        alert('Erreur lors de la copie');
    });
}

// Fonction pour prévisualiser un fichier avant l'upload
function previewFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Taille en Mo
        
        // Afficher les informations du fichier
        document.getElementById('fileInfo').textContent = 
            `${file.name} (${fileSize} Mo)`;
    }
}