/**
 * COLM Registrar Document Request and Tracking System
 * System Administrator Controller
 */

const AdminApp = {
    async init() {
        await auth.checkSession(['Admin']);
        NotificationCenter.init();
        navigation.init();
        utils.setupFilterTriggers();

        await this.loadUsers();
        await this.loadSystemAudit();
        this.setupFilterEvents();
    },

    setupFilterEvents() {
        const userSearch = document.getElementById('userSearchInput');
        const roleFilter = document.getElementById('userRoleFilter');
        const statusFilter = document.getElementById('userStatusFilter');
        if (userSearch) userSearch.addEventListener('input', utils.debounce(() => this.loadUsers(1), 350));
        if (roleFilter) roleFilter.addEventListener('change', () => this.loadUsers(1));
        if (statusFilter) statusFilter.addEventListener('change', () => this.loadUsers(1));
    },

    async loadUsers(page = 1) {
        const tbody = document.getElementById('usersTbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="7" class="loading-overlay"><div class="spinner"></div><p>Loading users...</p></td></tr>`;

        try {
            const search = document.getElementById('userSearchInput')?.value || '';
            const role = document.getElementById('userRoleFilter')?.value || '';
            const is_active = document.getElementById('userStatusFilter')?.value || '';

            const res = await api.get('/users', { page, limit: 15, search, role, is_active });
            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:2rem;color:var(--slate-500);">No users found.</td></tr>`;
                return;
            }

            tbody.innerHTML = records.map(u => `
                <tr>
                    <td><strong>#${u.user_id}</strong></td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(u.username)}</span>
                        ${u.email ? `<div class="secondary-cell-text">${utils.escapeHtml(u.email)}</div>` : ''}
                    </td>
                    <td>
                        <button type="button" class="badge filter-trigger ${u.role === 'Admin' ? 'badge-correction' : (u.role === 'Registrar' ? 'badge-submitted' : (u.role === 'Personnel' ? 'badge-verification' : 'badge-inactive'))}" data-filter-target="userRoleFilter" data-filter-value="${utils.escapeHtml(u.role)}" title="Filter by role">
                            ${u.role}
                        </button>
                    </td>
                    <td>
                        <button type="button" class="badge filter-trigger ${parseInt(u.is_active) === 1 ? 'badge-active' : 'badge-inactive'}" data-filter-target="userStatusFilter" data-filter-value="${parseInt(u.is_active) === 1 ? '1' : '0'}" title="Filter by account status">
                            ${parseInt(u.is_active) === 1 ? 'Active' : 'Inactive'}
                        </button>
                    </td>
                    <td>${u.last_login_at ? utils.formatDate(u.last_login_at) : '<em>Never</em>'}</td>
                    <td>${utils.formatDate(u.created_at, false)}</td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="AdminApp.openEditUserModal(${u.user_id})">Edit</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="AdminApp.toggleUserStatus(${u.user_id})">
                            ${parseInt(u.is_active) === 1 ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `).join('');

            this.renderPagination('usersPagination', meta, (p) => this.loadUsers(p));
        } catch (e) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="color:#024E28;">Failed to load users.</td></tr>`;
        }
    },

    openCreateUserModal() {
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('userUsername').readOnly = false;
        document.getElementById('userPassword').required = true;
        document.getElementById('userModalTitle').textContent = 'Create New User Account';
        document.getElementById('userModal').classList.add('show');
    },

    async openEditUserModal(userId) {
        try {
            const res = await api.get(`/users/${userId}`);
            const u = res.data;

            document.getElementById('userId').value = u.user_id;
            document.getElementById('userUsername').value = u.username;
            document.getElementById('userUsername').readOnly = true; // Username immutable in edit
            document.getElementById('userRole').value = u.role;
            document.getElementById('userActive').value = u.is_active;
            document.getElementById('userPassword').value = '';
            document.getElementById('userPassword').required = false;

            document.getElementById('userModalTitle').textContent = `Edit User: ${u.username}`;
            document.getElementById('userModal').classList.add('show');
        } catch (e) {
            Toast.error('Failed to load user details.');
        }
    },

    async handleUserFormSubmit(e) {
        e.preventDefault();
        const userId = document.getElementById('userId').value;
        const payload = {
            username: document.getElementById('userUsername').value,
            role: document.getElementById('userRole').value,
            is_active: parseInt(document.getElementById('userActive').value, 10),
            password: document.getElementById('userPassword').value
        };

        try {
            if (userId) {
                await api.put(`/users/${userId}`, payload);
                Toast.success('User account updated successfully.');
            } else {
                await api.post('/users', payload);
                Toast.success('User account created successfully.');
            }
            document.getElementById('userModal').classList.remove('show');
            await this.loadUsers();
        } catch (err) {
            Toast.error(err.message || 'Failed to save user account.');
        }
    },

    async toggleUserStatus(userId) {
        try {
            const res = await api.patch(`/users/${userId}/status`);
            Toast.success(res.message);
            await this.loadUsers();
        } catch (err) {
            Toast.error(err.message || 'Failed to update user status.');
        }
    },

    async loadSystemAudit(page = 1) {
        const tbody = document.getElementById('adminAuditTbody');
        if (!tbody) return;

        try {
            const res = await api.get('/audit-logs', { page, limit: 15 });
            const records = res.data || [];

            tbody.innerHTML = records.map(a => `
                <tr>
                    <td><strong>#${a.log_id}</strong></td>
                    <td><span class="badge badge-submitted">${utils.escapeHtml(a.action)}</span></td>
                    <td>${utils.escapeHtml(a.entity_affected)} (${a.entity_id})</td>
                    <td>${a.username ? utils.escapeHtml(a.username) : 'System'} (${a.role || 'Guest'})</td>
                    <td><code>${utils.escapeHtml(a.ip_address)}</code></td>
                    <td>${utils.formatDate(a.created_at)}</td>
                </tr>
            `).join('');
        } catch (e) {
            console.error('Failed to load audit logs:', e);
        }
    },

    renderPagination(containerId, meta, onPageClick) {
        const container = document.getElementById(containerId);
        if (!container || !meta.pages || meta.pages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        const current = meta.page;
        const total = meta.pages;

        let btns = '';
        btns += `<button type="button" class="pagination-btn" ${current === 1 ? 'disabled' : ''} onclick="AdminApp.loadUsers(${current - 1})">Prev</button>`;

        for (let p = 1; p <= total; p++) {
            btns += `<button type="button" class="pagination-btn ${p === current ? 'active' : ''}" onclick="AdminApp.loadUsers(${p})">${p}</button>`;
        }

        btns += `<button type="button" class="pagination-btn" ${current === total ? 'disabled' : ''} onclick="AdminApp.loadUsers(${current + 1})">Next</button>`;

        container.innerHTML = `
            <div class="pagination-summary">Showing page ${current} of ${total} (${meta.total} records)</div>
            <div class="pagination-controls">${btns}</div>
        `;
    }
};

window.AdminApp = AdminApp;
