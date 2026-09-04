/**
 * COLM Registrar Document Request and Tracking System
 * Authentication & Session Guard Module
 */

const auth = {
    currentUser: null,

    async checkSession(allowedRoles = []) {
        try {
            const res = await api.get('/auth/me');
            const user = res.data;
            this.currentUser = user;

            // Enforce RBAC
            if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
                this.redirectToRoleHome(user.role);
                return null;
            }

            this.updateHeaderProfile(user);
            return user;
        } catch (err) {
            // Unauthenticated
            if (!window.location.pathname.includes('login.html') && !window.location.pathname.includes('index.html')) {
                window.location.href = 'login.html';
            }
            return null;
        }
    },

    updateHeaderProfile(user) {
        if (!user) return;
        
        const nameEl = document.getElementById('headerUserName');
        const roleEl = document.getElementById('headerUserRole');
        const avatarEl = document.getElementById('headerUserAvatar');

        const sidebarName = document.getElementById('sidebarUserName');
        const sidebarRole = document.getElementById('sidebarUserRole');

        const initial = (user.full_name || user.username || 'U').charAt(0).toUpperCase();

        if (nameEl) nameEl.textContent = user.full_name || user.username;
        if (roleEl) roleEl.textContent = user.role + (user.student_number ? ` (${user.student_number})` : '');
        if (avatarEl) avatarEl.textContent = initial;

        if (sidebarName) sidebarName.textContent = user.full_name || user.username;
        if (sidebarRole) sidebarRole.textContent = user.role;
    },

    redirectToRoleHome(role) {
        switch (role) {
            case 'Student':
                window.location.href = 'student.html';
                break;
            case 'Personnel':
                window.location.href = 'personnel.html';
                break;
            case 'Registrar':
                window.location.href = 'registrar.html';
                break;
            case 'Admin':
                window.location.href = 'admin.html';
                break;
            default:
                window.location.href = 'login.html';
        }
    },

    async login(username, password) {
        try {
            const res = await api.post('/auth/login', { username, password });
            Toast.success('Login successful! Redirecting...', 'Welcome');
            const user = res.data;
            setTimeout(() => {
                this.redirectToRoleHome(user.role);
            }, 600);
            return user;
        } catch (err) {
            Toast.error(err.message || 'Invalid credentials.', 'Login Failed');
            throw err;
        }
    },

    async logout() {
        const confirmed = await utils.confirmModal({
            title: 'Confirm Logout',
            message: 'Are you sure you want to end your active session?',
            confirmText: 'Logout',
            confirmClass: 'btn-danger',
            iconType: 'warning'
        });

        if (confirmed) {
            try {
                await api.post('/auth/logout');
            } catch (e) {
                // Ignore
            }
            window.location.href = 'login.html';
        }
    }
};

window.auth = auth;
