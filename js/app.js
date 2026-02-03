 

 
let currentUser = null;

 
async function initApp() {
     
    currentUser = JSON.parse(localStorage.getItem('user') || 'null');

     
    if (currentUser) {
        try {
            const response = await fetch('check_session.php');
            const data = await response.json();

            if (!data.authenticated) {
                 
                logout(false);
            } else {
                currentUser = data.user;
                localStorage.setItem('user', JSON.stringify(currentUser));

                 
                if (data.pending_invitations && data.pending_invitations.length > 0) {
                    showPendingInvitations(data.pending_invitations);
                }

                 
                if (data.active_games && data.active_games.length > 0) {
                    showActiveGamesNotification(data.active_games);
                }
            }
        } catch (error) {
            console.error('Session check error:', error);
        }
    }

     
    updateNavigation();
}

 
function updateNavigation() {
    const navUserSection = document.getElementById('nav-user-section');
    if (!navUserSection) return;

    if (currentUser) {
        navUserSection.innerHTML = `
            <a href="profile.html" class="nav-user" style="display: flex; align-items: center; gap: 0.5rem;">
                <img src="uploads/profiles/${currentUser.profile_photo || 'default.png'}" 
                     alt="${currentUser.username}" 
                     class="user-avatar" 
                     style="width: 36px; height: 36px;">
                <span style="color: var(--text-primary); font-weight: 500;">${currentUser.username}</span>
            </a>
            <button class="btn btn-ghost btn-sm" onclick="logout()">
                Déconnexion
            </button>
        `;
    } else {
        navUserSection.innerHTML = `
            <a href="auth.html" class="btn btn-primary btn-sm">Connexion</a>
        `;
    }
}

 
async function logout(redirect = true) {
    try {
        await fetch('logout.php');
    } catch (error) {
         
    }

    localStorage.removeItem('user');
    localStorage.removeItem('session_token');
    currentUser = null;

    if (redirect) {
        window.location.href = 'index.html';
    }
}

 
function showPendingInvitations(invitations) {
    const panel = document.getElementById('invitations-panel');
    if (!panel) return;

    panel.innerHTML = invitations.map(inv => `
        <div class="invitation-toast" data-invitation-id="${inv.id}">
            <img src="uploads/profiles/${inv.sender_photo || 'default.png'}" 
                 alt="${inv.sender_username}" 
                 style="width: 40px; height: 40px; border-radius: 50%;">
            <div>
                <strong>${inv.sender_username}</strong>
                <p class="text-muted" style="margin: 0; font-size: 0.875rem;">
                    vous invite à jouer!
                </p>
            </div>
            <div class="invitation-actions">
                <button class="btn btn-danger btn-sm btn-icon" 
                        onclick="respondInvitation(${inv.id}, false)" title="Refuser">
                    ✕
                </button>
                <button class="btn btn-success btn-sm" 
                        onclick="respondInvitation(${inv.id}, true)">
                    Jouer
                </button>
            </div>
        </div>
    `).join('');
}

 
function showActiveGamesNotification(games) {
    const activeGame = games.find(g =>
        g.status === 'active' &&
        ((g.current_turn === 'white' && g.white_player_id == currentUser.id) ||
            (g.current_turn === 'black' && g.black_player_id == currentUser.id))
    );

    if (activeGame) {
        showToast(`C'est votre tour dans une partie!`, 'info');
    }

    const pausedGames = games.filter(g => g.status === 'paused');
    if (pausedGames.length > 0) {
        showToast(`Vous avez ${pausedGames.length} partie(s) en pause`, 'warning');
    }
}

 
async function respondInvitation(invitationId, accept) {
    try {
        const response = await fetch('api/users.php?action=respond_invitation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invitation_id: invitationId, accept: accept })
        });

        const data = await response.json();

         
        const toast = document.querySelector(`[data-invitation-id="${invitationId}"]`);
        if (toast) toast.remove();

        if (data.success) {
            if (accept && data.game_id) {
                showToast('Partie lancée!', 'success');
                setTimeout(() => {
                    window.location.href = `game.html?id=${data.game_id}`;
                }, 1000);
            } else {
                showToast('Invitation refusée', 'info');
            }
        } else {
            showToast(data.error || 'Erreur', 'error');
        }
    } catch (error) {
        showToast('Erreur de connexion', 'error');
    }
}

 
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = {
        success: '🟦',
        error: '🟦',
        warning: '🟦',
        info: '🟦'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${icons[type] || ''}</span>
        <span>${message}</span>
    `;

    container.appendChild(toast);

     
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

 
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

 
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

 
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

 
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

 
function formatRelativeTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'À l\'instant';
    if (diffMins < 60) return `Il y a ${diffMins} min`;
    if (diffHours < 24) return `Il y a ${diffHours}h`;
    if (diffDays === 1) return 'Hier';
    if (diffDays < 7) return `Il y a ${diffDays} jours`;
    return date.toLocaleDateString('fr-FR');
}

 
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

 
class RealtimeUpdater {
    constructor(options = {}) {
        this.interval = options.interval || 5000;
        this.callbacks = [];
        this.timer = null;
        this.isRunning = false;
    }

    addCallback(callback) {
        this.callbacks.push(callback);
    }

    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        this.timer = setInterval(() => {
            this.callbacks.forEach(cb => cb());
        }, this.interval);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.isRunning = false;
    }
}

 
let lastActivity = Date.now();

document.addEventListener('mousemove', () => { lastActivity = Date.now(); });
document.addEventListener('keypress', () => { lastActivity = Date.now(); });
document.addEventListener('click', () => { lastActivity = Date.now(); });
document.addEventListener('scroll', () => { lastActivity = Date.now(); });

 
 
if (currentUser) {
    setInterval(async () => {
         
        if (Date.now() - lastActivity > 5 * 60 * 1000) return;

        try {
            const response = await fetch('check_session.php');
            const data = await response.json();

            if (data.authenticated) {
                 
                if (data.pending_invitations) {
                    showPendingInvitations(data.pending_invitations);
                }

                 
                if (window.location.pathname.endsWith('index.html') || window.location.pathname === '/' || window.location.pathname.endsWith('/')) {
                    if (typeof updateHeroAction === 'function') {
                        updateHeroAction(data.active_games);
                    }
                }
            } else if (data.error === 'Non connecté' && currentUser) {
                logout(false);
            }
        } catch (error) {
            console.error('Session check error', error);
        }
    }, 5000);  
}


 
 

