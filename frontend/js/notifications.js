/**
 * COLM Registrar Document Request and Tracking System
 * Toast Notification Engine & Notification Center
 */

const Toast = {
    container: null,

    init() {
        if (!this.container) {
            let el = document.getElementById('toastContainer');
            if (!el) {
                el = document.createElement('div');
                el.id = 'toastContainer';
                el.className = 'toast-container';
                document.body.appendChild(el);
            }
            this.container = el;
        }
    },

    show(message, type = 'info', title = null, duration = 4000) {
        this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = {
            success: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`,
            error: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`,
            warning: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`,
            info: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
        };

        const defaultTitles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Notice'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                <div class="toast-title">${title || defaultTitles[type]}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button type="button" class="btn-toast-close" aria-label="Close notification">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;

        const closeBtn = toast.querySelector('.btn-toast-close');
        const dismiss = () => {
            toast.classList.add('toast-hiding');
            setTimeout(() => toast.remove(), 250);
        };

        closeBtn.addEventListener('click', dismiss);

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }

        this.container.appendChild(toast);
    },

    success(message, title = 'Success', duration = 4000) {
        this.show(message, 'success', title, duration);
    },

    error(message, title = 'Error', duration = 5000) {
        this.show(message, 'error', title, duration);
    },

    warning(message, title = 'Warning', duration = 4500) {
        this.show(message, 'warning', title, duration);
    },

    info(message, title = 'Notice', duration = 4000) {
        this.show(message, 'info', title, duration);
    }
};

const NotificationCenter = {
    pollingInterval: null,
    lastKnownUnread: 0,

    init() {
        const bellBtn = document.getElementById('notifBellBtn');
        const menu = document.getElementById('notifDropdownMenu');
        const markAllBtn = document.getElementById('markAllReadBtn');

        if (bellBtn && menu) {
            bellBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('show');
                if (menu.classList.contains('show')) {
                    this.fetchNotifications();
                }
            });

            document.addEventListener('click', (e) => {
                if (!menu.contains(e.target) && !bellBtn.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
                try {
                    await api.patch('/notifications/read-all');
                    Toast.success('All notifications marked as read.');
                    this.fetchNotifications();
                } catch (e) {
                    Toast.error('Failed to mark all as read.');
                }
            });
        }

        // Initial fetch
        this.fetchNotifications();

        // Start 30s background live polling
        if (!this.pollingInterval) {
            this.pollingInterval = setInterval(() => this.fetchNotifications(true), 30000);
        }
    },

    async fetchNotifications(isBackgroundPoll = false) {
        try {
            const res = await api.get('/notifications');
            const data = res.data || {};
            const unreadCount = data.unread_count || 0;
            const notifs = data.notifications || [];

            this.updateBadge(unreadCount);

            // If background poll detected new notifications, trigger a toast
            if (isBackgroundPoll && unreadCount > this.lastKnownUnread && notifs.length > 0) {
                const latest = notifs[0];
                Toast.info(latest.message, latest.title);
            }
            this.lastKnownUnread = unreadCount;

            this.renderList(notifs);
        } catch (e) {
            // Silently ignore background poll errors
        }
    },

    updateBadge(count) {
        const badge = document.getElementById('notifBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    },

    renderList(notifs) {
        const listEl = document.getElementById('notifList');
        if (!listEl) return;

        if (notifs.length === 0) {
            listEl.innerHTML = `
                <div class="notif-empty">
                    <p>No notifications yet.</p>
                </div>
            `;
            return;
        }

        listEl.innerHTML = notifs.map(n => `
            <div class="notif-item ${parseInt(n.is_read) === 0 ? 'unread' : ''}" onclick="NotificationCenter.markRead(${n.notification_id}, ${n.request_id || 'null'})">
                <div class="notif-title">${utils.escapeHtml(n.title)}</div>
                <div class="notif-message">${utils.escapeHtml(n.message)}</div>
                <div class="notif-time">${utils.formatDate(n.created_at)}</div>
                <button type="button" class="btn-notif-delete" aria-label="Delete notification" title="Delete notification" onclick="event.stopPropagation(); NotificationCenter.deleteNotification(${n.notification_id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12m-10 0v10m8-10v10M9 7V4h6v3m-9 0h12"/></svg>
                </button>
            </div>
        `).join('');
    },

    async markRead(notifId, requestId) {
        try {
            await api.patch(`/notifications/${notifId}/read`);
            this.fetchNotifications();
            if (requestId && window.viewRequestDetails) {
                window.viewRequestDetails(requestId);
            }
        } catch (e) {
            console.error('Error marking notification read:', e);
        }
    },

    async deleteNotification(notifId) {
        try {
            await api.delete(`/notifications/${notifId}`);
            this.fetchNotifications();
            Toast.success('Notification deleted.');
        } catch (e) {
            Toast.error('Failed to delete notification.');
        }
    }
};

window.Toast = Toast;
window.NotificationCenter = NotificationCenter;
