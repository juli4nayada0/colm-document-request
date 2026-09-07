/**
 * COLM Registrar Document Request and Tracking System
 * Registrar Administrative Portal & CSV Importer
 */

const RegistrarApp = {
    csvPreviewData: null,
    personnelList: [],
    requestScope: '',

    async init() {
        await auth.checkSession(['Registrar', 'Admin']);
        NotificationCenter.init();
        navigation.init();
        utils.setupFilterTriggers();

        await this.loadSummaryKPIs();
        await this.loadPersonnelList();
        await this.loadAllRequests();
        await this.loadDocumentCatalog();
        await this.loadStudents();

        this.setupFilterEvents();
        this.setupKpiFilters();
        this.setupCsvImportEvents();
    },

    async loadSummaryKPIs() {
        try {
            const res = await api.get('/reports/summary');
            const kpis = res.data?.kpis || {};

            document.getElementById('statTotalRequests').textContent = kpis.total_requests || 0;
            document.getElementById('statActivePending').textContent = kpis.active_pending || 0;
            document.getElementById('statOverdueCount').textContent = kpis.overdue_count || 0;
            document.getElementById('statTotalReleased').textContent = kpis.status_released || 0;
            document.getElementById('statTotalCollected').textContent = utils.formatCurrency(kpis.total_collected || 0);
        } catch (e) {
            console.error('Error loading summary:', e);
        }
    },

    async loadPersonnelList() {
        try {
            const res = await api.get('/users?role=Personnel&limit=50');
            this.personnelList = res.data || [];
            
            const assignSel = document.getElementById('assignPersonnelSelect');
            if (assignSel) {
                assignSel.innerHTML = '<option value="">-- Choose Personnel --</option>' + 
                    this.personnelList.map(u => `<option value="${u.user_id}">${utils.escapeHtml(u.username)} (${u.user_id})</option>`).join('');
            }
        } catch (e) {
            console.error('Error loading personnel:', e);
        }
    },

    setupFilterEvents() {
        const reqSearch = document.getElementById('reqSearchInput');
        const reqStatus = document.getElementById('reqStatusFilter');
        const reqDoc = document.getElementById('reqDocFilter');

        if (reqSearch) reqSearch.addEventListener('input', utils.debounce(() => this.loadAllRequests(1), 350));
        if (reqStatus) reqStatus.addEventListener('change', () => {
            this.requestScope = '';
            document.querySelectorAll('.stat-card[data-filter-scope]').forEach(item => item.classList.remove('is-active'));
            this.loadAllRequests(1);
        });
        if (reqDoc) reqDoc.addEventListener('change', () => this.loadAllRequests(1));

        const studSearch = document.getElementById('studSearchInput');
        const studProgram = document.getElementById('studProgramFilter');
        const studentFilters = ['studEducationFilter', 'studGradeFilter', 'studSectionFilter', 'studYearFilter', 'studCourseFilter'];
        if (studSearch) studSearch.addEventListener('input', utils.debounce(() => this.loadStudents(1), 350));
        if (studProgram) studProgram.addEventListener('change', () => this.loadStudents(1));
        studentFilters.forEach(id => {
            const filter = document.getElementById(id);
            if (filter) filter.addEventListener('change', () => this.loadStudents(1));
        });
    },

    setupKpiFilters() {
        document.querySelectorAll('.stat-card[data-filter-scope]').forEach((card) => {
            card.addEventListener('click', () => {
                this.requestScope = card.dataset.filterScope === 'all' ? '' : card.dataset.filterScope;
                document.querySelectorAll('.stat-card[data-filter-scope]').forEach(item => {
                    item.classList.toggle('is-active', item === card);
                });
                const filter = document.getElementById(card.dataset.filterTarget);
                if (filter) filter.value = card.dataset.filterValue || '';
                navigation.switchView('requests');
                this.loadAllRequests(1);
            });
        });
    },

    async loadAllRequests(page = 1) {
        const tbody = document.getElementById('allRequestsTbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="9" class="loading-overlay"><div class="spinner"></div><p>Loading requests...</p></td></tr>`;

        try {
            const search = document.getElementById('reqSearchInput')?.value || '';
            const status = document.getElementById('reqStatusFilter')?.value || '';
            const document_id = document.getElementById('reqDocFilter')?.value || '';

            const params = {
                page,
                limit: 15,
                search,
                status,
                document_id
            };
            if (this.requestScope) params.scope = this.requestScope;

            const res = await api.get('/requests', params);

            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="padding:2.5rem;color:var(--slate-500);">No requests matching criteria.</td></tr>`;
                return;
            }

            tbody.innerHTML = records.map(r => `
                <tr>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(r.tracking_number)}</span>
                        <div class="secondary-cell-text">${utils.formatDate(r.submitted_at, false)}</div>
                    </td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(r.student_number)}</span>
                        <div class="secondary-cell-text">${utils.escapeHtml(r.last_name)}, ${utils.escapeHtml(r.first_name)}</div>
                    </td>
                    <td>
                        <button type="button" class="primary-cell-text filter-trigger" data-filter-target="reqDocFilter" data-filter-value="${r.document_id}" title="Filter by document">${utils.escapeHtml(r.document_name)}</button>
                        <div class="secondary-cell-text">${r.copies} copy(ies)</div>
                    </td>
                    <td>${utils.formatCurrency(r.amount_due)}</td>
                    <td>${utils.getStatusBadge(r.current_status, 'reqStatusFilter')}</td>
                    <td>
                        <span class="badge ${r.payment_status === 'Paid' ? 'badge-active' : (r.payment_status === 'For Verification' ? 'badge-payment' : 'badge-rejected')}">
                            ${r.payment_status}
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="RegistrarApp.openAssignModal(${r.request_id}, ${r.assigned_personnel_id || 'null'})">
                            ${r.assigned_personnel_name ? utils.escapeHtml(r.assigned_personnel_name) : '+ Assign'}
                        </button>
                    </td>
                    <td>${utils.getOverdueBadge(r.overdue_status)}</td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-primary btn-sm" onclick="RegistrarApp.inspectRequest(${r.request_id})">Inspect</button>
                    </td>
                </tr>
            `).join('');

            this.renderPagination('reqPagination', meta, (p) => this.loadAllRequests(p));
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="padding:2rem;color:#024E28;">Failed to load requests.</td></tr>`;
        }
    },

    async inspectRequest(requestId) {
        try {
            const res = await api.get(`/requests/${requestId}`);
            const request = res.data.request;
            let modal = document.getElementById('registrarInspectModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'registrarInspectModal';
                modal.className = 'modal-backdrop';
                document.body.appendChild(modal);
            }

            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-header">
                        <div><span class="detail-label">REQUEST INSPECTOR</span><h3 class="modal-title">${utils.escapeHtml(request.tracking_number)}</h3></div>
                        <button type="button" class="btn-close-modal" aria-label="Close" onclick="document.getElementById('registrarInspectModal').classList.remove('show')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-grid">
                            <div><span class="detail-label">Student</span><strong>${utils.escapeHtml(`${request.last_name}, ${request.first_name}`)}</strong></div>
                            <div><span class="detail-label">Student Number</span><strong>${utils.escapeHtml(request.student_number)}</strong></div>
                            <div><span class="detail-label">Document</span><strong>${utils.escapeHtml(request.document_name)}</strong></div>
                            <div><span class="detail-label">Status</span>${utils.getStatusBadge(request.current_status)}</div>
                            <div><span class="detail-label">Payment</span><strong>${utils.escapeHtml(request.payment_status)}</strong></div>
                            <div><span class="detail-label">Amount Due</span><strong>${utils.formatCurrency(request.amount_due)}</strong></div>
                        </div>
                        <div style="margin-top:1.25rem;"><span class="detail-label">Purpose</span><p>${utils.escapeHtml(request.purpose)}</p></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('registrarInspectModal').classList.remove('show')">Close</button></div>
                </div>`;
            modal.classList.add('show');
        } catch (e) {
            Toast.error(e.message || 'Failed to load request details.');
        }
    },

    openAssignModal(requestId, currentPersonnelId) {
        document.getElementById('assignRequestId').value = requestId;
        const sel = document.getElementById('assignPersonnelSelect');
        if (sel) {
            sel.value = currentPersonnelId || '';
        }
        document.getElementById('assignModal').classList.add('show');
    },

    async handleAssignSubmit(e) {
        e.preventDefault();
        const requestId = document.getElementById('assignRequestId').value;
        const personnelId = document.getElementById('assignPersonnelSelect').value;

        try {
            await api.post(`/requests/${requestId}/assign`, { personnel_id: personnelId || null });
            Toast.success('Personnel assigned successfully.');
            document.getElementById('assignModal').classList.remove('show');
            await this.loadAllRequests();
        } catch (err) {
            Toast.error(err.message || 'Failed to assign personnel.');
        }
    },

    // --------------------------------------------------------------------------
    // DOCUMENT CATALOG MANAGEMENT
    // --------------------------------------------------------------------------
    async loadDocumentCatalog() {
        const tbody = document.getElementById('catalogTbody');
        if (!tbody) return;

        try {
            const res = await api.get('/documents?all=1');
            const docs = res.data || [];

            // Populate document filter dropdown
            const filterDoc = document.getElementById('reqDocFilter');
            if (filterDoc) {
                filterDoc.innerHTML = '<option value="">All Document Types</option>' +
                    docs.map(d => `<option value="${d.document_id}">${utils.escapeHtml(d.document_name)}</option>`).join('');
            }

            tbody.innerHTML = docs.map(d => `
                <tr>
                    <td><strong>${utils.escapeHtml(d.document_code)}</strong></td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(d.document_name)}</span>
                        <div class="secondary-cell-text">${utils.escapeHtml(d.typical_purpose)}</div>
                    </td>
                    <td>${utils.formatCurrency(d.fee_amount)}</td>
                    <td>~${d.processing_days} days</td>
                    <td>${parseInt(d.is_digital_allowed) === 1 ? 'Yes' : 'Physical'}</td>
                    <td>
                        <span class="badge ${parseInt(d.is_active) === 1 ? 'badge-active' : 'badge-inactive'}">
                            ${parseInt(d.is_active) === 1 ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="RegistrarApp.editDocument(${d.document_id})">Edit</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="RegistrarApp.toggleDocStatus(${d.document_id})">
                            ${parseInt(d.is_active) === 1 ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="color:#024E28;">Failed to load catalog.</td></tr>`;
        }
    },

    openCreateDocModal() {
        document.getElementById('docForm').reset();
        document.getElementById('docId').value = '';
        document.getElementById('docModalTitle').textContent = 'Create Document Type';
        document.getElementById('docModal').classList.add('show');
    },

    async editDocument(docId) {
        try {
            const res = await api.get(`/documents/${docId}`);
            const d = res.data;

            document.getElementById('docId').value = d.document_id;
            document.getElementById('docCode').value = d.document_code;
            document.getElementById('docName').value = d.document_name;
            document.getElementById('docFee').value = d.fee_amount;
            document.getElementById('docDays').value = d.processing_days;
            document.getElementById('docDigital').value = d.is_digital_allowed;
            document.getElementById('docDesc').value = d.description;
            document.getElementById('docPurpose').value = d.typical_purpose;
            document.getElementById('docBasicReqs').value = d.basic_requirements;

            document.getElementById('docModalTitle').textContent = 'Edit Document Type';
            document.getElementById('docModal').classList.add('show');
        } catch (e) {
            Toast.error('Failed to load document details.');
        }
    },

    async handleDocFormSubmit(e) {
        e.preventDefault();
        const docId = document.getElementById('docId').value;
        const payload = {
            document_code: document.getElementById('docCode').value,
            document_name: document.getElementById('docName').value,
            fee_amount: document.getElementById('docFee').value,
            processing_days: document.getElementById('docDays').value,
            is_digital_allowed: document.getElementById('docDigital').value,
            description: document.getElementById('docDesc').value,
            typical_purpose: document.getElementById('docPurpose').value,
            basic_requirements: document.getElementById('docBasicReqs').value,
        };

        try {
            if (docId) {
                await api.put(`/documents/${docId}`, payload);
                Toast.success('Document type updated successfully.');
            } else {
                await api.post('/documents', payload);
                Toast.success('Document type created successfully.');
            }
            document.getElementById('docModal').classList.remove('show');
            await this.loadDocumentCatalog();
        } catch (err) {
            Toast.error(err.message || 'Failed to save document.');
        }
    },

    async toggleDocStatus(docId) {
        try {
            await api.patch(`/documents/${docId}/status`);
            Toast.success('Document status updated.');
            await this.loadDocumentCatalog();
        } catch (e) {
            Toast.error('Failed to update status.');
        }
    },

    // --------------------------------------------------------------------------
    // STUDENTS DIRECTORY
    // --------------------------------------------------------------------------
    async loadStudents(page = 1) {
        const tbody = document.getElementById('studentsTbody');
        if (!tbody) return;

        const hasRenderedRows = tbody.querySelector('tr') && !tbody.querySelector('.loading-overlay');
        if (hasRenderedRows) {
            tbody.classList.add('is-loading');
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="loading-overlay"><div class="spinner"></div><p>Loading students...</p></td></tr>`;
        }

        try {
            const search = document.getElementById('studSearchInput')?.value || '';
            const program = document.getElementById('studProgramFilter')?.value || '';
            const education_level = document.getElementById('studEducationFilter')?.value || '';
            const gradeLevel = document.getElementById('studGradeFilter')?.value || '';
            const collegeYear = document.getElementById('studYearFilter')?.value || '';
            const section = document.getElementById('studSectionFilter')?.value || '';
            const course = document.getElementById('studCourseFilter')?.value || '';

            const res = await api.get('/students', {
                page,
                limit: 15,
                search,
                program: course || program,
                education_level,
                year_level: gradeLevel || collegeYear,
                section
            });
            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:2.5rem;color:var(--slate-500);">No students found.</td></tr>`;
                tbody.classList.remove('is-loading');
                return;
            }

            tbody.innerHTML = records.map(s => `
                <tr>
                    <td><strong>${utils.escapeHtml(s.student_number)}</strong></td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(s.last_name)}, ${utils.escapeHtml(s.first_name)} ${utils.escapeHtml(s.middle_name || '')}</span>
                        <div class="secondary-cell-text">${s.sex} • DOB: ${s.dob}</div>
                    </td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(s.program || '—')}</span>
                        <div class="secondary-cell-text">${s.year_level || ''} ${s.section ? `• ${utils.escapeHtml(s.section)}` : ''} • ${s.academic_year || ''}</div>
                    </td>
                    <td>${utils.escapeHtml(s.email)}</td>
                    <td>${utils.escapeHtml(s.contact_number)}</td>
                    <td>
                        <span class="badge ${s.enrollment_status === 'Officially Enrolled' ? 'badge-active' : 'badge-payment'}">
                            ${s.enrollment_status || 'Enrolled'}
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="RegistrarApp.viewStudentProfile(${s.student_id})">Profile</button>
                    </td>
                </tr>
            `).join('');

            this.renderPagination('studPagination', meta, (p) => this.loadStudents(p));
            tbody.classList.remove('is-loading');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="color:#024E28;">Failed to load students.</td></tr>`;
            tbody.classList.remove('is-loading');
        }
    },

    async viewStudentProfile(studentId) {
        try {
            const res = await api.get(`/students/${studentId}`);
            const data = res.data;
            const p = data.profile;
            const enroll = data.enrollment_history || [];
            const acad = data.academic_record;

            const modal = document.getElementById('studentProfileModal');
            document.getElementById('spStudentNum').textContent = p.student_number;
            document.getElementById('spFullName').textContent = `${p.last_name}, ${p.first_name} ${p.middle_name || ''}`;
            document.getElementById('spDob').textContent = p.dob;
            document.getElementById('spSex').textContent = p.sex;
            document.getElementById('spEmail').textContent = p.email;
            document.getElementById('spContact').textContent = p.contact_number;
            document.getElementById('spAddress').textContent = p.address;
            document.getElementById('spProgram').textContent = p.program || '—';
            document.getElementById('spYear').textContent = p.year_level || '—';

            // Enrollment history
            const enrollEl = document.getElementById('spEnrollHistory');
            if (enrollEl) {
                enrollEl.innerHTML = enroll.map(e => `
                    <div style="padding:0.6rem;background:var(--slate-50);border-radius:var(--radius-sm);margin-bottom:0.4rem;font-size:0.85rem;border:1px solid var(--border-color);">
                        <strong>${e.academic_year} (${e.semester})</strong>: ${e.program} - ${e.year_level} [${e.enrollment_status}]
                    </div>
                `).join('');
            }

            modal.classList.add('show');
        } catch (e) {
            Toast.error('Failed to load student profile.');
        }
    },

    // --------------------------------------------------------------------------
    // CSV BULK STUDENT IMPORTER (Two-Stage Process)
    // --------------------------------------------------------------------------
    setupCsvImportEvents() {
        const fileInput = document.getElementById('csvFileInput');
        if (fileInput) {
            fileInput.addEventListener('change', () => this.handleCsvFileSelected(fileInput));
        }
    },

    async handleCsvFileSelected(inputEl) {
        if (!inputEl.files || !inputEl.files[0]) return;
        const file = inputEl.files[0];

        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('action', 'preview');

        Toast.info('Uploading and validating CSV file...', 'Processing');

        try {
            const res = await api.upload('/students/import', formData);
            const data = res.data;
            this.csvPreviewData = data;

            document.getElementById('csvTotalRows').textContent = data.total_rows;
            document.getElementById('csvValidRows').textContent = data.valid_count;
            document.getElementById('csvInvalidRows').textContent = data.invalid_count;
            document.getElementById('csvDuplicateRows').textContent = data.duplicate_count;

            const previewArea = document.getElementById('csvPreviewSection');
            if (previewArea) previewArea.style.display = 'block';

            // Render Error Table
            const errTbody = document.getElementById('csvErrorsTbody');
            if (errTbody) {
                if (data.errors.length === 0) {
                    errTbody.innerHTML = `<tr><td colspan="4" class="text-center" style="padding:1.5rem;color:#059669;font-weight:700;">All rows passed validation! Ready to import.</td></tr>`;
                } else {
                    errTbody.innerHTML = data.errors.map(err => `
                        <tr>
                            <td><strong>Row ${err.row_number}</strong></td>
                            <td><span class="badge badge-submitted">${utils.escapeHtml(err.field_name)}</span></td>
                            <td style="color:#024E28;font-weight:600;">${utils.escapeHtml(err.error_message)}</td>
                            <td><code>${utils.escapeHtml(err.raw_value || '—')}</code></td>
                        </tr>
                    `).join('');
                }
            }

            const commitBtn = document.getElementById('btnConfirmImport');
            if (commitBtn) {
                commitBtn.disabled = data.valid_count === 0;
            }

            Toast.success(`CSV analyzed: ${data.valid_count} valid records found.`);
        } catch (err) {
            Toast.error(err.message || 'Failed to parse CSV file.');
        }
    },

    async commitCsvImport() {
        if (!this.csvPreviewData || !this.csvPreviewData.valid_rows || this.csvPreviewData.valid_rows.length === 0) {
            Toast.warning('No valid rows available to import.');
            return;
        }

        const confirmed = await utils.confirmModal({
            title: 'Confirm Bulk Student Import',
            message: `You are about to create ${this.csvPreviewData.valid_count} student accounts and enrollment records. Proceed?`,
            confirmText: 'Import Records',
            confirmClass: 'btn-primary'
        });

        if (confirmed) {
            try {
                const payload = {
                    action: 'commit',
                    valid_rows: this.csvPreviewData.valid_rows,
                    errors: this.csvPreviewData.errors,
                    filename: this.csvPreviewData.filename
                };

                const res = await api.post('/students/import', payload);
                Toast.success(`Successfully imported ${res.data.successful_rows} student accounts!`, 'Import Completed');

                document.getElementById('csvPreviewSection').style.display = 'none';
                document.getElementById('csvFileInput').value = '';
                this.csvPreviewData = null;

                await this.loadStudents();
                navigation.switchView('students');
            } catch (err) {
                Toast.error(err.message || 'Import transaction failed.');
            }
        }
    },

    downloadErrorReportCsv() {
        if (!this.csvPreviewData || !this.csvPreviewData.errors) return;
        const errors = this.csvPreviewData.errors;

        let csv = 'Row Number,Field Name,Error Message,Raw Value\n';
        errors.forEach(e => {
            csv += `"${e.row_number}","${e.field_name}","${e.error_message.replace(/"/g, '""')}","${(e.raw_value || '').replace(/"/g, '""')}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `COLM_Import_Errors_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    },

    // --------------------------------------------------------------------------
    // AUDIT LOGS
    // --------------------------------------------------------------------------
    async loadAuditLogs(page = 1) {
        const tbody = document.getElementById('auditTbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="6" class="loading-overlay"><div class="spinner"></div><p>Loading audit logs...</p></td></tr>`;

        try {
            const search = document.getElementById('auditSearchInput')?.value || '';
            const action = document.getElementById('auditActionFilter')?.value || '';

            const res = await api.get('/audit-logs', { page, limit: 20, search, action });
            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center" style="padding:2rem;color:var(--slate-500);">No audit logs recorded.</td></tr>`;
                return;
            }

            tbody.innerHTML = records.map(a => `
                <tr>
                    <td><strong>#${a.log_id}</strong></td>
                    <td>
                        <span class="badge badge-submitted">${utils.escapeHtml(a.action)}</span>
                    </td>
                    <td>${utils.escapeHtml(a.entity_affected)} (${a.entity_id})</td>
                    <td>
                        <span class="primary-cell-text">${a.username ? utils.escapeHtml(a.username) : 'System'}</span>
                        <div class="secondary-cell-text">${a.role || 'Guest'}</div>
                    </td>
                    <td><code>${utils.escapeHtml(a.ip_address)}</code></td>
                    <td>${utils.formatDate(a.created_at)}</td>
                </tr>
            `).join('');

            this.renderPagination('auditPagination', meta, (p) => this.loadAuditLogs(p));
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center" style="color:#024E28;">Failed to load audit logs.</td></tr>`;
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
        btns += `<button type="button" class="pagination-btn" ${current === 1 ? 'disabled' : ''} onclick="RegistrarApp.loadAllRequests(${current - 1})">Prev</button>`;

        for (let p = 1; p <= total; p++) {
            btns += `<button type="button" class="pagination-btn ${p === current ? 'active' : ''}" onclick="RegistrarApp.loadAllRequests(${p})">${p}</button>`;
        }

        btns += `<button type="button" class="pagination-btn" ${current === total ? 'disabled' : ''} onclick="RegistrarApp.loadAllRequests(${current + 1})">Next</button>`;

        container.innerHTML = `
            <div class="pagination-summary">Showing page ${current} of ${total} (${meta.total} records)</div>
            <div class="pagination-controls">${btns}</div>
        `;
    }
};

window.RegistrarApp = RegistrarApp;
