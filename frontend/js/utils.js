/**
 * COLM Registrar Document Request and Tracking System
 * Global UI Utilities, Modal Dialogs & Helpers
 */

const utils = {
    /**
     * Escape HTML string to prevent XSS
     */
    escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    /**
     * Format PHP currency amount (e.g. ₱350.00)
     */
    formatCurrency(amount) {
        const num = parseFloat(amount || 0);
        return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    /**
     * Format Date String (e.g. Sep 02, 2026 08:30 AM)
     */
    formatDate(dateStr, includeTime = true) {
        if (!dateStr) return '—';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            const options = {
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            };
            if (includeTime) {
                options.hour = '2-digit';
                options.minute = '2-digit';
                options.hour12 = true;
            }
            return date.toLocaleDateString('en-US', options);
        } catch (e) {
            return dateStr;
        }
    },

    /**
     * Render semantic Status Badge with icon
     */
    getStatusBadge(status, filterTarget = '') {
        const icons = {
            document: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l3 3v13a2 2 0 01-2 2z"/></svg>',
            search: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" stroke-width="2" d="m20 20-4-4"/></svg>',
            payment: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-width="2" d="M3 10h18"/></svg>',
            processing: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36-6.36-2.12 2.12M7.76 16.24l-2.12 2.12m12.72 0-2.12-2.12M7.76 7.76 5.64 5.64"/></svg>',
            check: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6"/></svg>',
            warning: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5 20h14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 17A2 2 0 005 20z"/></svg>'
        };
        const statusMap = {
            'REQUEST SUBMITTED': { cls: 'badge-submitted', label: 'Submitted', icon: icons.document },
            'PENDING PAYMENT': { cls: 'badge-payment', label: 'Pending Payment', icon: icons.payment },
            'PAID': { cls: 'badge-active', label: 'PAID', icon: icons.check },
            'FOR PROCESSING': { cls: 'badge-processing', label: 'For Processing', icon: icons.processing },
            'FOR VERIFICATION': { cls: 'badge-verification', label: 'For Verification', icon: icons.search },
            'FOR PAYMENT': { cls: 'badge-payment', label: 'For Payment', icon: icons.payment },
            'PROCESSING': { cls: 'badge-processing', label: 'Processing', icon: icons.processing },
            'FOR REVIEW/APPROVAL': { cls: 'badge-review', label: 'For Review', icon: icons.document },
            'READY FOR RELEASE': { cls: 'badge-ready', label: 'Ready for Release', icon: icons.check },
            'RELEASED': { cls: 'badge-released', label: 'Released', icon: icons.check },
            'COMPLETED': { cls: 'badge-released', label: 'Completed', icon: icons.check },
            'ON HOLD': { cls: 'badge-on-hold', label: 'On Hold', icon: icons.warning },
            'INCOMPLETE': { cls: 'badge-incomplete', label: 'Incomplete', icon: icons.warning },
            'FOR CORRECTION': { cls: 'badge-correction', label: 'For Correction', icon: icons.warning },
            'REJECTED/CANCELLED': { cls: 'badge-rejected', label: 'Cancelled', icon: icons.warning }
        };

        const config = statusMap[status] || { cls: 'badge-inactive', label: status || 'Unknown', icon: icons.document };
        if (filterTarget) {
            return `<button type="button" class="badge ${config.cls} filter-trigger" data-filter-target="${utils.escapeHtml(filterTarget)}" data-filter-value="${utils.escapeHtml(status)}" title="Filter by ${utils.escapeHtml(config.label)}">${config.icon} ${utils.escapeHtml(config.label)}</button>`;
        }
        return `<span class="badge ${config.cls}">${config.icon} ${utils.escapeHtml(config.label)}</span>`;
    },

    setupFilterTriggers() {
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.filter-trigger, .stat-card[data-filter-target]:not([data-filter-scope])');
            if (!trigger) return;

            const target = document.getElementById(trigger.dataset.filterTarget);
            if (!target) return;

            target.value = trigger.dataset.filterValue || '';
            utils.syncFilterCards(trigger.dataset.filterTarget, target.value);
            target.dispatchEvent(new Event('change', { bubbles: true }));

            if (trigger.classList.contains('stat-card') && trigger.closest('#view_dashboard')) {
                if (window.navigation) {
                    window.navigation.switchView('requests');
                }
            }
        });
    },

    syncFilterCards(targetId, value) {
        document.querySelectorAll(`.stat-card[data-filter-target="${targetId}"]`).forEach((card) => {
            card.classList.toggle('is-active', card.dataset.filterValue === value);
        });
    },

    /**
     * Render Overdue Pill
     */
    getOverdueBadge(overdueStatus) {
        if (overdueStatus === 'Overdue') {
            return `<span class="badge badge-rejected">Overdue</span>`;
        } else if (overdueStatus === 'Due Soon') {
            return `<span class="badge badge-payment">Due Soon</span>`;
        } else if (overdueStatus === 'Completed') {
            return `<span class="badge badge-released">Completed</span>`;
        }
        return `<span class="badge badge-submitted">On Time</span>`;
    },

    /**
     * Debounce function calls
     */
    debounce(func, delay = 300) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    },

    /**
     * Reusable Accessible Confirmation Modal Engine
     * Returns Promise<boolean>
     */
    confirmModal({
        title = 'Are you sure?',
        message = 'Please confirm this action.',
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        confirmClass = 'btn-primary',
        iconType = 'warning'
    } = {}) {
        return new Promise((resolve) => {
            let backdrop = document.getElementById('globalConfirmModal');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'globalConfirmModal';
                backdrop.className = 'modal-backdrop';
                document.body.appendChild(backdrop);
            }

            const icons = {
                warning: `<svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`,
                danger: `<svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`,
                info: `<svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
            };

            backdrop.innerHTML = `
                <div class="modal-dialog" style="max-width: 440px;">
                    <div class="modal-body">
                        <div class="confirm-dialog-content">
                            <div class="confirm-dialog-icon ${iconType}">
                                ${icons[iconType] || icons.warning}
                            </div>
                            <div class="confirm-dialog-title">${utils.escapeHtml(title)}</div>
                            <div class="confirm-dialog-desc">${utils.escapeHtml(message)}</div>
                        </div>
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                        <button type="button" class="btn btn-secondary" id="confirmCancelBtn">${utils.escapeHtml(cancelText)}</button>
                        <button type="button" class="btn ${confirmClass}" id="confirmOkBtn">${utils.escapeHtml(confirmText)}</button>
                    </div>
                </div>
            `;

            backdrop.classList.add('show');

            const okBtn = backdrop.querySelector('#confirmOkBtn');
            const cancelBtn = backdrop.querySelector('#confirmCancelBtn');

            const cleanup = () => {
                backdrop.classList.remove('show');
            };

            okBtn.onclick = () => {
                cleanup();
                resolve(true);
            };

            cancelBtn.onclick = () => {
                cleanup();
                resolve(false);
            };

            backdrop.onclick = (e) => {
                if (e.target === backdrop) {
                    cleanup();
                    resolve(false);
                }
            };
        });
    },

    /**
     * Unsaved Changes Guard for forms
     */
    setupUnsavedChangesGuard(formElement) {
        if (!formElement) return;
        let isDirty = false;

        formElement.addEventListener('input', () => {
            isDirty = true;
        });

        formElement.addEventListener('submit', () => {
            isDirty = false;
        });

        window.addEventListener('beforeunload', (e) => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        return {
            reset() { isDirty = false; },
            markDirty() { isDirty = true; },
            isDirty() { return isDirty; }
        };
    }
};

window.utils = utils;
