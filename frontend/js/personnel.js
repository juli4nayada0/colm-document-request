/**
 * COLM Registrar Document Request and Tracking System
 * Registrar Personnel Processing Queue & Verification Controller
 */

const PersonnelApp = {
    currentRequestId: null,
    currentRequest: null,

    async init() {
        await auth.checkSession(['Personnel', 'Registrar']);
        NotificationCenter.init();
        navigation.init();
        utils.setupFilterTriggers();

        this.setupFilters();
        await this.loadQueue();
        await this.loadSummaryKPIs();
    },

    setupFilters() {
        const searchInput = document.getElementById('qSearchInput');
        const statusFilter = document.getElementById('qStatusFilter');
        const myQueueCheck = document.getElementById('qMyQueueOnly');

        if (searchInput) {
            searchInput.addEventListener('input', utils.debounce(() => this.loadQueue(1), 350));
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                utils.syncFilterCards('qStatusFilter', statusFilter.value);
                this.loadQueue(1);
            });
        }
        if (myQueueCheck) {
            myQueueCheck.addEventListener('change', () => this.loadQueue(1));
        }
    },

    async loadSummaryKPIs() {
        try {
            const res = await api.get('/requests?limit=100');
            const reqs = res.data || [];

            const forVerification = reqs.filter(r => ['FOR VERIFICATION', 'REQUEST SUBMITTED', 'PENDING PAYMENT'].includes(r.current_status)).length;
            const processing = reqs.filter(r => ['FOR PROCESSING', 'PROCESSING'].includes(r.current_status)).length;
            const forReview = reqs.filter(r => r.current_status === 'FOR REVIEW/APPROVAL').length;
            const readyRelease = reqs.filter(r => r.current_status === 'READY FOR RELEASE').length;

            document.getElementById('statForVerification').textContent = forVerification;
            document.getElementById('statProcessing').textContent = processing;
            document.getElementById('statForReview').textContent = forReview;
            document.getElementById('statReadyRelease').textContent = readyRelease;
        } catch (e) {
            console.error('Error loading KPIs:', e);
        }
    },

    async loadQueue(page = 1) {
        const tbody = document.getElementById('queueTbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="8" class="loading-overlay"><div class="spinner"></div><p>Loading queue...</p></td></tr>`;

        try {
            const search = document.getElementById('qSearchInput')?.value || '';
            const status = document.getElementById('qStatusFilter')?.value || '';
            const myQueueOnly = document.getElementById('qMyQueueOnly')?.checked;

            const params = {
                page,
                limit: 15,
                search,
                status
            };

            if (myQueueOnly) {
                params.my_queue = '1';
            }

            const res = await api.get('/requests', params);
            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <h3>No requests in queue</h3>
                                <p>All document requests matching criteria have been addressed.</p>
                            </div>
                        </td>
                    </tr>
                `;
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
                        <span class="primary-cell-text">${utils.escapeHtml(r.document_name)}</span>
                        <div class="secondary-cell-text">${r.copies} copy(ies) • ${r.release_method}</div>
                    </td>
                    <td>${utils.getStatusBadge(r.current_status, 'qStatusFilter')}</td>
                    <td>
                        <span class="badge ${r.payment_status === 'Paid' ? 'badge-active' : (r.payment_status === 'For Verification' ? 'badge-payment' : 'badge-rejected')}">
                            ${r.payment_status}
                        </span>
                    </td>
                    <td>
                        <span style="font-size:0.85rem;color:var(--slate-600);">${r.assigned_personnel_name ? utils.escapeHtml(r.assigned_personnel_name) : '<em>Unassigned</em>'}</span>
                    </td>
                    <td>${utils.getOverdueBadge(r.overdue_status)}</td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-primary btn-sm" onclick="PersonnelApp.inspectRequest(${r.request_id})">
                            Process / Inspect
                        </button>
                    </td>
                </tr>
            `).join('');

            this.renderPagination('queuePagination', meta, (p) => this.loadQueue(p));
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center" style="padding:2rem;color:#024E28;">Failed to load queue.</td></tr>`;
        }
    },

    async inspectRequest(requestId) {
        this.currentRequestId = requestId;
        try {
            const res = await api.get(`/requests/${requestId}`);
            const data = res.data;
            this.currentRequest = data.request;
            const r = data.request;
            const reqs = data.uploaded_requirements || [];
            const timeline = data.timeline || [];
            const payment = data.payment;
            const release = data.release;

            const modal = document.getElementById('inspectRequestModal');
            if (!modal) return;

            document.getElementById('insTracking').textContent = r.tracking_number;
            document.getElementById('insStatus').innerHTML = utils.getStatusBadge(r.current_status);
            const updateStatusButton = document.getElementById('btnUpdateStatus');
            const recordReleaseButton = document.getElementById('btnRecordRelease');
            if (updateStatusButton) {
                updateStatusButton.style.display = ['PENDING PAYMENT', 'FOR PROCESSING', 'PROCESSING'].includes(r.current_status) ? 'none' : '';
            }
            const workflowActionButton = document.getElementById('btnWorkflowAction');
            if (workflowActionButton) {
                const actions = {
                    'FOR PROCESSING': ['Start Processing', 'PROCESSING'],
                    'PROCESSING': ['Mark Ready for Release', 'READY FOR RELEASE']
                };
                const action = actions[r.current_status];
                workflowActionButton.style.display = action ? '' : 'none';
                if (action) {
                    workflowActionButton.textContent = action[0];
                    workflowActionButton.onclick = () => this.transitionTo(action[1]);
                }
            }
            if (recordReleaseButton) {
                recordReleaseButton.style.display = r.current_status === 'READY FOR RELEASE' ? '' : 'none';
            }
            document.getElementById('insStudentNum').textContent = r.student_number;
            document.getElementById('insStudentName').textContent = `${r.last_name}, ${r.first_name} ${r.middle_name || ''}`;
            document.getElementById('insProgram').textContent = r.program || '—';
            document.getElementById('insYearLevel').textContent = r.year_level || '—';
            document.getElementById('insEmail').textContent = r.email || '—';
            document.getElementById('insContact').textContent = r.contact_number || '—';

            document.getElementById('insDocName').textContent = r.document_name;
            document.getElementById('insCopies').textContent = r.copies;
            document.getElementById('insPurpose').textContent = r.purpose;
            document.getElementById('insReleaseMethod').textContent = r.release_method;
            document.getElementById('insAmountDue').textContent = utils.formatCurrency(r.amount_due);
            document.getElementById('insPaymentStatus').textContent = r.payment_status;
            document.getElementById('insSubmittedAt').textContent = utils.formatDate(r.submitted_at);
            document.getElementById('insTargetDate').textContent = utils.formatDate(r.target_completion_date, false);

            // Requirements List
            const reqsEl = document.getElementById('insRequirementsList');
            if (reqsEl) {
                if (reqs.length === 0) {
                    reqsEl.innerHTML = `<p style="font-size:0.85rem;color:var(--slate-500);">No requirement uploads required.</p>`;
                } else {
                    reqsEl.innerHTML = reqs.map(rq => `
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem;background:var(--slate-50);border-radius:var(--radius-sm);margin-bottom:0.5rem;border:1px solid var(--border-color);">
                            <div>
                                <div style="font-weight:700;font-size:0.85rem;">${utils.escapeHtml(rq.requirement_name)}</div>
                                <div style="font-size:0.75rem;color:var(--slate-500);">${utils.escapeHtml(rq.original_filename)} • ${(rq.file_size/1024).toFixed(0)} KB</div>
                                ${rq.remarks ? `<div style="font-size:0.75rem;color:#D97706;margin-top:2px;">Remarks: ${utils.escapeHtml(rq.remarks)}</div>` : ''}
                            </div>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <a href="${API_BASE}/files/download?type=requirement&id=${rq.request_requirement_id}" class="btn btn-secondary btn-sm" target="_blank">View File</a>
                                ${rq.verification_status !== 'Verified' ? `
                                    <button type="button" class="btn btn-primary btn-sm" onclick="PersonnelApp.verifyReqFile(${rq.request_requirement_id}, 'Verified')">Verify</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="PersonnelApp.promptRejectReqFile(${rq.request_requirement_id})">Reject</button>
                                ` : '<span class="badge badge-active">Verified</span>'}
                            </div>
                        </div>
                    `).join('');
                }
            }

            // Payment verification area
            const payArea = document.getElementById('insPaymentArea');
            if (payArea) {
                if (r.current_status === 'PENDING PAYMENT' && r.payment_status !== 'Paid') {
                    payArea.innerHTML = `
                        <div style="padding:1rem;background:#FEF3C7;border-radius:var(--radius-md);margin-bottom:1rem;">
                            <strong style="color:#92400E;">Pending In-Person Cashier Payment</strong>
                            <div style="font-size:0.8rem;color:#78350F;margin:0.35rem 0 0.75rem;">The student must pay ${utils.formatCurrency(r.amount_due)} at the Registrar/Personnel office before processing.</div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="PersonnelApp.openCashierPaymentModal(${r.request_id}, ${r.amount_due})">Confirm Cashier Payment</button>
                        </div>
                    `;
                } else {
                    payArea.innerHTML = '';
                }
            }

            modal.classList.add('show');
        } catch (e) {
            Toast.error('Failed to load request details.');
        }
    },

    async verifyReqFile(reqReqId, status, remarks = null) {
        try {
            await api.patch(`/requests/requirements/${reqReqId}/verify`, { status, remarks });
            Toast.success(`Requirement marked as ${status}.`);
            await this.inspectRequest(this.currentRequestId);
        } catch (e) {
            Toast.error('Failed to verify requirement.');
        }
    },

    async promptRejectReqFile(reqReqId) {
        const reason = prompt('Please enter the reason for rejecting / requesting resubmission of this document:');
        if (reason) {
            await this.verifyReqFile(reqReqId, 'Resubmission Requested', reason);
        }
    },

    openCashierPaymentModal(requestId, amountDue) {
        this.currentRequestId = requestId;
        document.getElementById('cashierAmount').value = Number(amountDue).toFixed(2);
        document.getElementById('cashierDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('cashierReference').value = '';
        document.getElementById('cashierConfirmation').checked = false;
        document.getElementById('cashierPaymentModal').classList.add('show');
    },

    async confirmCashierPayment(requestId, amountDue) {
        try {
            await api.post(`/requests/${requestId}/payment/confirm`, {
                amount: amountDue,
                payment_method: 'Official Receipt (Cashier)',
                payment_date: new Date().toISOString().slice(0, 10),
                confirmation: true
            });
            Toast.success('Cashier payment confirmed. Request moved to For Processing.');
            await this.inspectRequest(requestId);
            await this.loadQueue();
            await this.loadSummaryKPIs();
        } catch (e) {
            Toast.error(e.message || 'Failed to confirm cashier payment.');
        }
    },

    async handleCashierPaymentSubmit(e) {
        e.preventDefault();
        const reference = document.getElementById('cashierReference').value.trim();
        if (reference && !/^\d{1,20}$/.test(reference)) {
            Toast.warning('The official receipt/reference number must contain numbers only and be no more than 20 digits.');
            document.getElementById('cashierReference').focus();
            return;
        }

        try {
            await api.post(`/requests/${this.currentRequestId}/payment/confirm`, {
                amount: document.getElementById('cashierAmount').value,
                payment_method: document.getElementById('cashierMethod').value,
                payment_date: document.getElementById('cashierDate').value,
                reference_number: reference,
                confirmation: document.getElementById('cashierConfirmation').checked
            });
            Toast.success('Cashier payment confirmed. Request moved to For Processing.');
            document.getElementById('cashierPaymentModal').classList.remove('show');
            await this.inspectRequest(this.currentRequestId);
            await this.loadQueue();
            await this.loadSummaryKPIs();
        } catch (e) {
            Toast.error(e.message || 'Failed to confirm cashier payment.');
        }
    },

    async transitionTo(targetStatus) {
        const confirmed = await utils.confirmModal({
            title: targetStatus === 'PROCESSING' ? 'Start Processing' : 'Mark Ready for Release',
            message: `Confirm changing this request to ${targetStatus}?`,
            confirmText: 'Confirm',
            confirmClass: 'btn-primary'
        });
        if (!confirmed) return;
        try {
            await api.patch(`/requests/${this.currentRequestId}/status`, { new_status: targetStatus });
            Toast.success(`Request status changed to ${targetStatus}.`);
            await this.inspectRequest(this.currentRequestId);
            await this.loadQueue();
            await this.loadSummaryKPIs();
        } catch (e) {
            Toast.error(e.message || 'Status transition failed.');
        }
    },

    openStatusTransitionModal() {
        const r = this.currentRequest;
        if (!r) return;

        const modal = document.getElementById('statusModal');
        document.getElementById('statusCurrentDisplay').textContent = r.current_status;

        const targetSelect = document.getElementById('statusTargetSelect');
        const allowed = {
            'REQUEST SUBMITTED': ['FOR VERIFICATION', 'REJECTED/CANCELLED'],
            'FOR VERIFICATION': ['PENDING PAYMENT', 'INCOMPLETE', 'FOR CORRECTION', 'ON HOLD', 'REJECTED/CANCELLED'],
            'PENDING PAYMENT': ['PAID', 'ON HOLD', 'REJECTED/CANCELLED'],
            'PAID': ['FOR PROCESSING'],
            'FOR PROCESSING': ['PROCESSING', 'ON HOLD'],
            'FOR PAYMENT': ['PROCESSING', 'ON HOLD', 'INCOMPLETE'],
            'PROCESSING': ['READY FOR RELEASE', 'FOR CORRECTION', 'ON HOLD'],
            'FOR REVIEW/APPROVAL': ['READY FOR RELEASE', 'FOR CORRECTION', 'REJECTED/CANCELLED'],
            'READY FOR RELEASE': ['RELEASED', 'ON HOLD'],
            'RELEASED': ['COMPLETED'],
            'ON HOLD': ['FOR VERIFICATION', 'FOR PAYMENT', 'PROCESSING', 'FOR REVIEW/APPROVAL', 'READY FOR RELEASE', 'REJECTED/CANCELLED'],
            'INCOMPLETE': ['FOR VERIFICATION', 'REJECTED/CANCELLED'],
            'FOR CORRECTION': ['FOR VERIFICATION', 'PROCESSING']
        };

        const targets = allowed[r.current_status] || [];
        targetSelect.innerHTML = targets.map(t => `<option value="${t}">${t}</option>`).join('');

        modal.classList.add('show');
    },

    async handleStatusTransition(e) {
        e.preventDefault();
        const targetStatus = document.getElementById('statusTargetSelect').value;
        const remarks = document.getElementById('statusRemarks').value;

        try {
            await api.patch(`/requests/${this.currentRequestId}/status`, {
                new_status: targetStatus,
                remarks
            });

            Toast.success(`Request status changed to ${targetStatus}.`);
            document.getElementById('statusModal').classList.remove('show');
            await this.inspectRequest(this.currentRequestId);
            await this.loadQueue();
            await this.loadSummaryKPIs();
        } catch (err) {
            Toast.error(err.message || 'Status transition failed.');
        }
    },

    openReleaseModal() {
        const r = this.currentRequest;
        if (!r) return;

        if (r.current_status !== 'READY FOR RELEASE') {
            Toast.warning('Move the request to READY FOR RELEASE before recording the release.');
            return;
        }

        document.getElementById('relMethod').value = r.release_method;
        document.getElementById('releaseModal').classList.add('show');
    },

    toggleRecipientFields() {
        const type = document.getElementById('relRecipientType').value;
        const repFields = document.getElementById('relRepresentativeFields');
        if (repFields) {
            repFields.style.display = type === 'Representative' ? 'block' : 'none';
        }
    },

    async handleReleaseSubmit(e) {
        e.preventDefault();
        const recipientType = document.getElementById('relRecipientType').value;
        const repName = document.getElementById('relRepName')?.value;
        const repRel = document.getElementById('relRepRel')?.value;
        const authRef = document.getElementById('relAuthRef')?.value;
        const idVerified = document.getElementById('relIdVerified')?.checked ? 1 : 0;
        const remarks = document.getElementById('relRemarks')?.value;

        const payload = {
            release_method: document.getElementById('relMethod').value,
            recipient_type: recipientType,
            representative_name: repName,
            representative_relationship: repRel,
            authorization_reference: authRef,
            identification_verified: idVerified,
            remarks
        };

        try {
            await api.post(`/requests/${this.currentRequestId}/release`, payload);
            Toast.success('Document release confirmed and recorded.');
            document.getElementById('releaseModal').classList.remove('show');
            document.getElementById('inspectRequestModal').classList.remove('show');
            await this.loadQueue();
            await this.loadSummaryKPIs();
        } catch (err) {
            Toast.error(err.message || 'Failed to record release.');
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
        btns += `<button type="button" class="pagination-btn" ${current === 1 ? 'disabled' : ''} onclick="PersonnelApp.loadQueue(${current - 1})">Prev</button>`;

        for (let p = 1; p <= total; p++) {
            btns += `<button type="button" class="pagination-btn ${p === current ? 'active' : ''}" onclick="PersonnelApp.loadQueue(${p})">${p}</button>`;
        }

        btns += `<button type="button" class="pagination-btn" ${current === total ? 'disabled' : ''} onclick="PersonnelApp.loadQueue(${current + 1})">Next</button>`;

        container.innerHTML = `
            <div class="pagination-summary">Showing page ${current} of ${total} (${meta.total} records)</div>
            <div class="pagination-controls">${btns}</div>
        `;
    }
};

window.PersonnelApp = PersonnelApp;
window.inspectRequestDetails = (id) => PersonnelApp.inspectRequest(id);
