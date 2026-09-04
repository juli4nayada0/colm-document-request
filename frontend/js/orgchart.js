/**
 * COLM Registrar – Organizational Chart Module
 * Used by BOTH registrar.html (CRUD) and student.html (read-only display)
 */

const OrgChartApp = (() => {

    // ── role metadata ────────────────────────────────────────────────────────────
    const ROLE_META = {
        vp:              { label: 'Vice President',           color: '#1a3a5c', badge: '#e8f0fe' },
        registrar:       { label: 'OIC-Registrar',            color: '#1d6a96', badge: '#e0f2fe' },
        admin_assistant: { label: 'Administrative Assistant', color: '#2e7d32', badge: '#e8f5e9' },
        coordinator:     { label: 'Coordinator',              color: '#6d4c41', badge: '#fbe9e7' },
        staff:           { label: 'Staff',                    color: '#546e7a', badge: '#eceff1' },
    };

    const ROLE_OPTIONS = Object.entries(ROLE_META)
        .map(([v, m]) => `<option value="${v}">${m.label}</option>`)
        .join('');

    // ── state ────────────────────────────────────────────────────────────────────
    let _members   = [];
    let _editId    = null;
    let _isReadOnly = false;

    // ── public init ──────────────────────────────────────────────────────────────
    async function init(readOnly = false) {
        _isReadOnly = readOnly;
        await _loadMembers();
        _bindEvents();
    }

    // ── data ─────────────────────────────────────────────────────────────────────
    async function _loadMembers() {
        try {
            const res = await api.request('/org-chart');
            _members = res.data || [];
            _render();
        } catch (e) {
            console.error('[OrgChart] load failed', e);
            const container = document.getElementById('orgChartCanvas');
            if (container) container.innerHTML = `<p style="color:#024E28;text-align:center;">Failed to load org chart. Please refresh.</p>`;
        }
    }

    // ── render (visual chart) ────────────────────────────────────────────────────
    function _render() {
        const canvas = document.getElementById('orgChartCanvas');
        if (!canvas) return;

        const byLevel = {
            vp:              _members.filter(m => m.role_level === 'vp'              && +m.is_active),
            registrar:       _members.filter(m => m.role_level === 'registrar'       && +m.is_active),
            admin_assistant: _members.filter(m => m.role_level === 'admin_assistant' && +m.is_active),
            coordinator:     _members.filter(m => m.role_level === 'coordinator'     && +m.is_active),
            staff:           _members.filter(m => m.role_level === 'staff'           && +m.is_active),
        };

        const levels = [
            { key: 'vp',              members: byLevel.vp              },
            { key: 'registrar',       members: byLevel.registrar       },
            { key: 'admin_assistant', members: [...byLevel.admin_assistant, ...byLevel.coordinator] },
            { key: 'staff',           members: byLevel.staff           },
        ].filter(l => l.members.length > 0);

        canvas.innerHTML = levels.map((lvl, li) => `
            <div class="oc-row" data-level="${lvl.key}">
                ${li > 0 ? '<div class="oc-connector-v"></div>' : ''}
                <div class="oc-cards-row ${lvl.members.length > 1 ? 'oc-multi' : ''}">
                    ${lvl.members.length > 1 ? '<div class="oc-connector-h"></div>' : ''}
                    ${lvl.members.map(m => _card(m)).join('')}
                </div>
            </div>
        `).join('');
    }

    function _card(m) {
        const meta  = ROLE_META[m.role_level] || ROLE_META.staff;
        const initials = m.full_name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
        const editBtn = !_isReadOnly
            ? `<button class="oc-card-edit" onclick="OrgChartApp.openEdit(${m.member_id})" title="Edit">Edit</button>`
            : '';
        const dept = m.department ? `<span class="oc-card-dept">${_esc(m.department)}</span>` : '';

        return `
        <div class="oc-card" style="--oc-color:${meta.color};--oc-badge:${meta.badge};">
            ${editBtn}
            <div class="oc-avatar">${initials}</div>
            <div class="oc-card-body">
                <span class="oc-role-badge" style="background:${meta.badge};color:${meta.color};">${meta.label}</span>
                <div class="oc-name">${_esc(m.full_name)}</div>
                <div class="oc-position">${_esc(m.position_title)}</div>
                ${dept}
            </div>
        </div>`;
    }

    // ── registrar table (CRUD list) ───────────────────────────────────────────────
    function _renderTable() {
        const tbody = document.getElementById('orgTableBody');
        if (!tbody) return;

        if (!_members.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--slate-400);padding:2rem;">No members yet. Click "+ Add Member" to start.</td></tr>`;
            return;
        }

        tbody.innerHTML = _members.map(m => {
            const meta   = ROLE_META[m.role_level] || ROLE_META.staff;
            const active = +m.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
            return `
            <tr>
                <td><strong>${_esc(m.full_name)}</strong></td>
                <td>${_esc(m.position_title)}</td>
                <td><span class="badge" style="background:${meta.badge};color:${meta.color};">${meta.label}</span></td>
                <td>${active}</td>
                <td class="actions-cell">
                    <button class="btn btn-secondary btn-sm" onclick="OrgChartApp.openEdit(${m.member_id})">Edit</button>
                    <button class="btn btn-sm" style="background:#EFFAF2;color:#024E28;border:none;" onclick="OrgChartApp.deleteMember(${m.member_id})">Delete</button>
                </td>
            </tr>`;
        }).join('');
    }

    // ── modal helpers ─────────────────────────────────────────────────────────────
    function openAdd() {
        _editId = null;
        document.getElementById('ocModalTitle').textContent   = 'Add Org Chart Member';
        document.getElementById('ocSaveBtn').textContent      = 'Add Member';
        document.getElementById('ocForm').reset();
        document.getElementById('ocRoleLevel').innerHTML      = ROLE_OPTIONS;
        document.getElementById('ocIsActive').checked         = true;
        _showModal();
    }

    function openEdit(id) {
        const m = _members.find(x => +x.member_id === +id);
        if (!m) return;
        _editId = id;

        document.getElementById('ocModalTitle').textContent   = 'Edit Member';
        document.getElementById('ocSaveBtn').textContent      = 'Save Changes';
        document.getElementById('ocFullName').value           = m.full_name;
        document.getElementById('ocPosition').value           = m.position_title;
        document.getElementById('ocDepartment').value         = m.department || '';
        document.getElementById('ocRoleLevel').innerHTML      = ROLE_OPTIONS;
        document.getElementById('ocRoleLevel').value          = m.role_level;
        document.getElementById('ocSortOrder').value          = m.sort_order;
        document.getElementById('ocIsActive').checked         = +m.is_active === 1;
        _showModal();
    }

    async function deleteMember(id) {
        const m = _members.find(x => +x.member_id === +id);
        if (!m) return;
        if (!confirm(`Remove "${m.full_name}" from the org chart?`)) return;
        try {
            await api.request(`/org-chart/${id}`, { method: 'DELETE' });
            await _loadMembers();
            _renderTable();
            utils.showToast('Member removed.', 'success');
        } catch (e) {
            utils.showToast('Failed to remove member.', 'error');
        }
    }

    function _showModal() {
        document.getElementById('ocModal').classList.add('show');
    }
    function _hideModal() {
        document.getElementById('ocModal').classList.remove('show');
    }

    // ── save ─────────────────────────────────────────────────────────────────────
    async function _handleSave(e) {
        e.preventDefault();
        const payload = {
            full_name:      document.getElementById('ocFullName').value.trim(),
            position_title: document.getElementById('ocPosition').value.trim(),
            department:     document.getElementById('ocDepartment').value.trim() || null,
            role_level:     document.getElementById('ocRoleLevel').value,
            sort_order:     parseInt(document.getElementById('ocSortOrder').value, 10) || 99,
            is_active:      document.getElementById('ocIsActive').checked ? 1 : 0,
        };

        try {
            if (_editId) {
                await api.request(`/org-chart/${_editId}`, { method: 'PUT', body: JSON.stringify(payload) });
                utils.showToast('Member updated.', 'success');
            } else {
                await api.request('/org-chart', { method: 'POST', body: JSON.stringify(payload) });
                utils.showToast('Member added.', 'success');
            }
            _hideModal();
            await _loadMembers();
            _renderTable();
        } catch (err) {
            utils.showToast(err.message || 'Save failed.', 'error');
        }
    }

    // ── events ────────────────────────────────────────────────────────────────────
    function _bindEvents() {
        const form = document.getElementById('ocForm');
        if (form) form.addEventListener('submit', _handleSave);

        const closeBtn = document.getElementById('ocModalClose');
        if (closeBtn) closeBtn.addEventListener('click', _hideModal);

        const cancelBtn = document.getElementById('ocCancelBtn');
        if (cancelBtn) cancelBtn.addEventListener('click', _hideModal);

        const addBtn = document.getElementById('ocAddBtn');
        if (addBtn) addBtn.addEventListener('click', openAdd);

        // after switching to org-chart view on registrar, render table
        const orig = window.onViewChanged;
        window.onViewChanged = (viewId) => {
            if (orig) orig(viewId);
            if (viewId === 'orgchart') {
                _render();
                if (!_isReadOnly) _renderTable();
            }
        };
    }

    // ── util ─────────────────────────────────────────────────────────────────────
    function _esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    return { init, openEdit, openAdd, deleteMember };
})();

window.OrgChartApp = OrgChartApp;
