const ChangePasswordModal = ({ isOpen, onClose, onSubmit, csrfToken }) => {
    if (!isOpen) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        onSubmit(data);
    };

    return React.createElement('div', {
        className: 'modal-overlay',
        onClick: (e) => {
            if (e.target === e.currentTarget) onClose();
        }
    }, 
        React.createElement('div', {
            className: 'modal-container'
        }, [
            React.createElement('div', {
                key: 'header',
                className: 'flex justify-between items-center p-4 border-b border-neutral-700'
            }, [
                React.createElement('h2', {
                    className: 'text-xl font-semibold text-rose-400'
                }, 'Changer le mot de passe'),
                React.createElement('button', {
                    onClick: onClose,
                    className: 'btn btn-secondary'
                }, '×')
            ]),
            React.createElement('form', {
                key: 'form',
                onSubmit: handleSubmit,
                className: 'p-4 space-y-4'
            }, [
                React.createElement('input', {
                    type: 'hidden',
                    name: 'csrf_token',
                    value: csrfToken
                }),
                React.createElement('input', {
                    type: 'hidden',
                    name: 'action',
                    value: 'change_password'
                }),
                React.createElement('div', {
                    className: 'space-y-2'
                }, [
                    React.createElement('label', {
                        className: 'block text-sm text-neutral-400'
                    }, 'Mot de passe actuel'),
                    React.createElement('input', {
                        type: 'password',
                        name: 'current_password',
                        required: true,
                        className: 'w-full p-2 bg-neutral-800 border border-neutral-700 rounded focus:border-rose-500 focus:outline-none'
                    })
                ]),
                React.createElement('div', {
                    className: 'space-y-2'
                }, [
                    React.createElement('label', {
                        className: 'block text-sm text-neutral-400'
                    }, 'Nouveau mot de passe'),
                    React.createElement('input', {
                        type: 'password',
                        name: 'new_password',
                        required: true,
                        className: 'w-full p-2 bg-neutral-800 border border-neutral-700 rounded focus:border-rose-500 focus:outline-none'
                    })
                ]),
                React.createElement('div', {
                    className: 'space-y-2'
                }, [
                    React.createElement('label', {
                        className: 'block text-sm text-neutral-400'
                    }, 'Confirmer le nouveau mot de passe'),
                    React.createElement('input', {
                        type: 'password',
                        name: 'confirm_password',
                        required: true,
                        className: 'w-full p-2 bg-neutral-800 border border-neutral-700 rounded focus:border-rose-500 focus:outline-none'
                    })
                ]),
                React.createElement('div', {
                    className: 'flex justify-end gap-2 pt-4'
                }, [
                    React.createElement('button', {
                        type: 'button',
                        onClick: onClose,
                        className: 'btn btn-secondary'
                    }, 'Annuler'),
                    React.createElement('button', {
                        type: 'submit',
                        className: 'btn'
                    }, 'Changer le mot de passe')
                ])
            ])
        ])
    );
};

// App wrapper
function PasswordModalApp({ csrfToken }) {
    const [isOpen, setIsOpen] = React.useState(false);
    
    React.useEffect(() => {
        const btn = document.getElementById('changePasswordBtn');
        if (btn) {
            btn.addEventListener('click', () => {
                setIsOpen(true);
                document.body.classList.add('modal-open');
            });
        }
    }, []);
    
    const handleClose = () => {
        setIsOpen(false);
        document.body.classList.remove('modal-open');
    };

    // Gérer la fermeture avec la touche Echap
    React.useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape' && isOpen) {
                handleClose();
            }
        };
        
        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [isOpen]);

    // Ajouter la gestion du clic en dehors de la modale
    const handleOutsideClick = (e) => {
        if (e.target === e.currentTarget) {
            handleClose();
        }
    };

    return React.createElement(ChangePasswordModal, {
        isOpen,
        onClose: handleClose,
        onSubmit: handleSubmit,
        csrfToken: csrfToken,
        onOverlayClick: handleOutsideClick
    });
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    const root = ReactDOM.createRoot(document.getElementById('passwordModalRoot'));
    root.render(React.createElement(PasswordModalApp, {
        csrfToken: window.CSRF_TOKEN
    }));
});

// Modifier la fonction PasswordModalApp pour accepter le token en props
function PasswordModalApp({ csrfToken }) {
    const [isOpen, setIsOpen] = React.useState(false);
    
    React.useEffect(() => {
        const btn = document.getElementById('changePasswordBtn');
        if (btn) {
            btn.addEventListener('click', () => setIsOpen(true));
        }
    }, []);
    
    const handleSubmit = async (formData) => {
        try {
            const form = new FormData();
            Object.entries(formData).forEach(([key, value]) => {
                form.append(key, value);
            });
            
            const response = await fetch('admin.php', {
                method: 'POST',
                body: form
            });
            
            if (response.ok) {
                alert('Mot de passe modifié avec succès');
                setIsOpen(false);
            } else {
                alert('Erreur lors du changement de mot de passe');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Erreur lors du changement de mot de passe');
        }
    };

    return React.createElement(ChangePasswordModal, {
        isOpen,
        onClose: () => setIsOpen(false),
        onSubmit: handleSubmit,
        csrfToken: csrfToken
    });
}