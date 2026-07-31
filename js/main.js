// Affiche une notification stylisée qui disparaît automatiquement après 3s
function showNotif(msg, isError = false) {
    const n = document.createElement('div');
    n.className = 'notification' + (isError ? ' error' : '');
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 3000);
}

// Fonction pour copier dans le presse-papier
function copyToClipboard(text, msg = 'Copié dans le presse-papier !') {
    navigator.clipboard.writeText(text).then(() => {
        showNotif(msg);
    }).catch(err => {
        console.error('Erreur lors de la copie :', err);
        showNotif('Erreur lors de la copie', true);
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