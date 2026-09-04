/**
 * COLM Registrar Document Request and Tracking System
 * Student Portal Logic & Multi-Step Request Wizard
 */

const StudentApp = {
    selectedDoc: null,
    catalog: [],
    uploadedReqFiles: {},
    wizardStep: 1,

    async init() {
        await auth.checkSession(['Student']);
        NotificationCenter.init();
        navigation.init();
        utils.setupFilterTriggers();

        await this.loadCatalog();
        await this.loadDashboardSummary();
        await this.loadMyRequests();

        this.setupWizardEvents();
    },

    async loadCatalog() {
        try {
            const res = await api.get('/documents');
            this.catalog = res.data || [];
            this.renderCatalogGrid(this.catalog);
            this.populateWizardDocSelect(this.catalog);
        } catch (e) {
            Toast.error('Failed to load document catalog.');
        }
    },

    async loadDashboardSummary() {
        try {
            const res = await api.get('/requests?limit=100');
            const reqs = res.data || [];

            const total = reqs.length;
            const pending = reqs.filter(r => r.current_status === 'PROCESSING').length;
            const forPayment = reqs.filter(r => r.current_status === 'PENDING PAYMENT').length;
            const ready = reqs.filter(r => r.current_status === 'READY FOR RELEASE').length;
            const released = reqs.filter(r => ['RELEASED', 'COMPLETED'].includes(r.current_status)).length;

            document.getElementById('statTotalRequests').textContent = total;
            document.getElementById('statPendingRequests').textContent = pending;
            document.getElementById('statForPayment').textContent = forPayment;
            document.getElementById('statReadyRelease').textContent = ready;
            document.getElementById('statReleased').textContent = released;
        } catch (e) {
            console.error('Error loading summary:', e);
        }
    },

    async loadMyRequests(page = 1) {
        const tbodies = [
            document.getElementById('myRequestsTbody'),
            document.getElementById('myRequestsHistoryTbody')
        ].filter(Boolean);
        if (tbodies.length === 0) return;

        tbodies.forEach(tbody => {
            tbody.innerHTML = `<tr><td colspan="7" class="loading-overlay"><div class="spinner"></div><p>Loading your requests...</p></td></tr>`;
        });

        try {
            const search = document.getElementById('reqSearchInput')?.value || '';
            const status = document.getElementById('reqStatusFilter')?.value || '';

            const res = await api.get('/requests', {
                page,
                limit: 10,
                search,
                status
            });

            const records = res.data || [];
            const meta = res.meta || {};

            if (records.length === 0) {
                const emptyState = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <h3>No document requests found</h3>
                                <p>You haven't submitted any requests yet, or no records match your filter.</p>
                                <button type="button" class="btn btn-primary btn-sm" onclick="StudentApp.openRequestPopup()">Request a Document</button>
                            </div>
                        </td>
                    </tr>
                `;
                tbodies.forEach(tbody => { tbody.innerHTML = emptyState; });
                return;
            }

            const rows = records.map(r => `
                <tr>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(r.tracking_number)}</span>
                        <div class="secondary-cell-text">${utils.formatDate(r.submitted_at, false)}</div>
                    </td>
                    <td>
                        <span class="primary-cell-text">${utils.escapeHtml(r.document_name)}</span>
                        <div class="secondary-cell-text">${r.copies} copy(ies) • ${r.release_method}</div>
                    </td>
                    <td>${utils.formatCurrency(r.amount_due)}</td>
                    <td>
                        <span class="badge ${r.payment_status === 'Paid' ? 'badge-active' : (r.payment_status === 'For Verification' ? 'badge-payment' : 'badge-rejected')}">
                            ${r.payment_status}
                        </span>
                    </td>
                    <td>${utils.getStatusBadge(r.current_status, 'reqStatusFilter')}</td>
                    <td>${utils.getOverdueBadge(r.overdue_status)}</td>
                    <td class="actions-cell">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="StudentApp.viewRequestDetail(${r.request_id})">
                            View Details
                        </button>
                    </td>
                </tr>
            `).join('');
            tbodies.forEach(tbody => { tbody.innerHTML = rows; });

            this.renderPagination('reqPagination', meta, (p) => this.loadMyRequests(p));
            this.renderPagination('historyPagination', meta, (p) => this.loadMyRequests(p));
        } catch (e) {
            tbodies.forEach(tbody => {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:2rem;color:#024E28;">Failed to load requests.</td></tr>`;
            });
        }
    },

    renderCatalogGrid(docs) {
        const grid = document.getElementById('catalogGrid');
        if (!grid) return;

        grid.innerHTML = docs.map(d => `
            <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;margin-bottom:0;">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
                        <span class="badge badge-submitted">${utils.escapeHtml(d.document_code)}</span>
                        <span style="font-weight:700;font-size:1.1rem;color:var(--primary-700);">${utils.formatCurrency(d.fee_amount)}</span>
                    </div>
                    <h3 style="font-size:1rem;font-weight:700;color:var(--slate-900);margin-bottom:0.4rem;">${utils.escapeHtml(d.document_name)}</h3>
                    <p style="font-size:0.8rem;color:var(--slate-600);line-height:1.4;margin-bottom:0.85rem;">${utils.escapeHtml(d.description)}</p>
                    
                    <div style="font-size:0.75rem;color:var(--slate-500);margin-bottom:0.5rem;">
                        <strong>Typical Purpose:</strong> ${utils.escapeHtml(d.typical_purpose)}
                    </div>
                    <div style="font-size:0.75rem;color:var(--slate-500);margin-bottom:0.85rem;">
                        <strong>Processing Time:</strong> ~${d.processing_days} working day(s)
                    </div>
                </div>

                <div style="border-top:1px solid var(--border-color);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:var(--slate-500);">
                        ${parseInt(d.is_digital_allowed) === 1 ? '🌐 Digital/Physical' : '🏛️ Physical Only'}
                    </span>
                    <button type="button" class="btn btn-primary btn-sm" onclick="StudentApp.startRequestForDoc(${d.document_id})">
                        Select Document
                    </button>
                </div>
            </div>
        `).join('');
    },

    populateWizardDocSelect(docs) {
        const sel = document.getElementById('wizardDocSelect');
        if (!sel) return;

        sel.innerHTML = '<option value="">-- Choose a Document --</option>' +
            docs.map(d => `<option value="${d.document_id}">${utils.escapeHtml(d.document_name)} (${utils.formatCurrency(d.fee_amount)})</option>`).join('');
    },

    startRequestForDoc(docId) {
        navigation.switchView('wizard');
        const sel = document.getElementById('wizardDocSelect');
        if (sel) {
            sel.value = docId;
            sel.dispatchEvent(new Event('change'));
        }
    },

    openRequestPopup() {
        const wizard = document.getElementById('view_wizard');
        if (!wizard) return;

        document.getElementById('wizardForm')?.reset();
        this.selectedDoc = null;
        this.uploadedReqFiles = {};
        this.wizardStep = 1;
        this.updateWizardSummaryPrice();
        this.renderWizardRequirementsStep();
        this.goToStep(1);
        wizard.classList.add('request-popup-open');
    },

    closeRequestPopup() {
        const wizard = document.getElementById('view_wizard');
        if (!wizard) return;

        if (wizard.classList.contains('request-popup-open')) {
            wizard.classList.remove('request-popup-open');
            return;
        }

        navigation.switchView('dashboard');
    },

    setupWizardEvents() {
        const sel = document.getElementById('wizardDocSelect');
        const copiesInput = document.getElementById('wizardCopies');

        if (sel) {
            sel.addEventListener('change', () => {
                const docId = parseInt(sel.value);
                this.selectedDoc = this.catalog.find(d => parseInt(d.document_id) === docId) || null;
                this.updateWizardSummaryPrice();
                this.renderWizardRequirementsStep();
            });
        }

        if (copiesInput) {
            copiesInput.addEventListener('input', () => {
                this.updateWizardSummaryPrice();
            });
        }

        // Search debouncing for requests list
        const searchInput = document.getElementById('reqSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', utils.debounce(() => this.loadMyRequests(1), 350));
        }

        const filterStatus = document.getElementById('reqStatusFilter');
        if (filterStatus) {
            filterStatus.addEventListener('change', () => {
                utils.syncFilterCards('reqStatusFilter', filterStatus.value);
                this.loadMyRequests(1);
            });
        }
    },

    updateWizardSummaryPrice() {
        const copies = parseInt(document.getElementById('wizardCopies')?.value || 1);
        const fee = this.selectedDoc ? parseFloat(this.selectedDoc.fee_amount) : 0;
        const total = fee * copies;

        const feeEl = document.getElementById('wizardDocFee');
        const totalEl = document.getElementById('wizardTotalFee');
        const procEl = document.getElementById('wizardProcessingDays');

        if (feeEl) feeEl.textContent = utils.formatCurrency(fee);
        if (totalEl) totalEl.textContent = utils.formatCurrency(total);
        if (procEl && this.selectedDoc) procEl.textContent = `~${this.selectedDoc.processing_days} working days`;
    },

    renderWizardRequirementsStep() {
        const container = document.getElementById('wizardReqsList');
        if (!container) return;

        if (!this.selectedDoc || !this.selectedDoc.requirements || this.selectedDoc.requirements.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding:1.5rem;">
                    <p>No mandatory uploaded documents required for this request. Official verification will be performed by the Registrar Office.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.selectedDoc.requirements.map(req => `
            <div class="card" style="margin-bottom:1rem;background-color:var(--slate-50);">
                <div style="margin-bottom:0.5rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <strong>${utils.escapeHtml(req.requirement_name)}</strong>
                        ${parseInt(req.is_required) === 1 ? '<span class="badge badge-rejected" style="font-size:0.65rem;">Required</span>' : '<span class="badge badge-inactive" style="font-size:0.65rem;">Optional</span>'}
                    </div>
                    <p style="font-size:0.8rem;color:var(--slate-600);">${utils.escapeHtml(req.description || 'Upload clear scan/photo')}</p>
                    <div style="font-size:0.75rem;color:var(--slate-500);margin-top:2px;">
                        Allowed formats: ${req.allowed_file_types} (Max ${req.max_file_size_mb} MB)
                    </div>
                </div>

                <div class="file-upload-dropzone" id="dropzone_req_${req.requirement_id}">
                    <input type="file" id="file_req_${req.requirement_id}" name="req_${req.requirement_id}" accept="${req.allowed_file_types.split(',').map(ext => '.' + ext.trim()).join(',')}" onchange="StudentApp.onReqFileSelected(${req.requirement_id}, this)">
                    <div class="dropzone-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    </div>
                    <div class="dropzone-text" id="dropzone_text_${req.requirement_id}">Click or drag file here to upload</div>
                </div>
            </div>
        `).join('');
    },

    onReqFileSelected(reqId, inputEl) {
        if (inputEl.files && inputEl.files[0]) {
            const file = inputEl.files[0];
            this.uploadedReqFiles[reqId] = file;
            const textEl = document.getElementById(`dropzone_text_${reqId}`);
            if (textEl) {
                textEl.innerHTML = `<span style="color:#059669;font-weight:700;">Selected:</span> ${utils.escapeHtml(file.name)} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            }
        }
    },

    goToStep(step) {
        if (step === 2) {
            const docSel = document.getElementById('wizardDocSelect');
            if (!docSel || !docSel.value) {
                Toast.warning('Please select a document first.');
                return;
            }
        }

        if (step === 3 || step === 4) {
            const purposeInput = document.getElementById('wizardPurpose');
            if (!purposeInput || !purposeInput.value.trim()) {
                Toast.warning('Please enter the specific purpose of your request.');
                purposeInput?.focus();
                return;
            }
        }

        if (step === 4) {
            const requiredRequirements = (this.selectedDoc?.requirements || [])
                .filter(req => parseInt(req.is_required) === 1);
            const missingRequirements = requiredRequirements
                .filter(req => !this.uploadedReqFiles[req.requirement_id]);

            if (missingRequirements.length > 0) {
                Toast.warning(`Please upload all required supporting requirements. Missing: ${missingRequirements.map(req => req.requirement_name).join(', ')}`);
                document.getElementById(`file_req_${missingRequirements[0].requirement_id}`)?.focus();
                return;
            }

            // Populate Review Summary
            const copies = parseInt(document.getElementById('wizardCopies')?.value || 1);
            const purpose = document.getElementById('wizardPurpose')?.value || '—';
            const method = document.getElementById('wizardReleaseMethod')?.value || 'Personal Claiming';
            const date = document.getElementById('wizardClaimingDate')?.value || 'TBD (Based on Processing Time)';
            const remarks = document.getElementById('wizardInstructions')?.value || '—';
            const total = (parseFloat(this.selectedDoc?.fee_amount || 0) * copies);

            document.getElementById('revDocName').textContent = this.selectedDoc?.document_name || '—';
            document.getElementById('revCopies').textContent = copies;
            document.getElementById('revTotalFee').textContent = utils.formatCurrency(total);
            document.getElementById('revPurpose').textContent = purpose;
            document.getElementById('revReleaseMethod').textContent = method;
            document.getElementById('revClaimingDate').textContent = date;
            document.getElementById('revInstructions').textContent = remarks;

            const reqCount = Object.keys(this.uploadedReqFiles).length;
            document.getElementById('revUploadedCount').textContent = `${reqCount} file(s) attached`;
        }

        this.wizardStep = step;

        // Update Track Circles
        document.querySelectorAll('.wizard-step').forEach((el, idx) => {
            const stepNum = idx + 1;
            el.classList.remove('active', 'completed');
            if (stepNum === step) {
                el.classList.add('active');
            } else if (stepNum < step) {
                el.classList.add('completed');
            }
        });

        // Show/Hide Panes
        document.querySelectorAll('.wizard-step-pane').forEach((el, idx) => {
            el.classList.toggle('active', (idx + 1) === step);
        });
    },

    async submitWizardRequest() {
        const requiredRequirements = (this.selectedDoc?.requirements || [])
            .filter(req => parseInt(req.is_required) === 1);
        const missingRequirements = requiredRequirements
            .filter(req => !this.uploadedReqFiles[req.requirement_id]);

        if (missingRequirements.length > 0) {
            Toast.warning(`Please upload all required supporting requirements. Missing: ${missingRequirements.map(req => req.requirement_name).join(', ')}`);
            document.getElementById(`file_req_${missingRequirements[0].requirement_id}`)?.focus();
            return;
        }

        const submitBtn = document.getElementById('btnSubmitWizard');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Submitting...`;
        }

        try {
            const formData = new FormData();
            formData.append('document_id', this.selectedDoc.document_id);
            formData.append('copies', document.getElementById('wizardCopies').value);
            formData.append('purpose', document.getElementById('wizardPurpose').value);
            formData.append('release_method', document.getElementById('wizardReleaseMethod').value);
            formData.append('preferred_claiming_date', document.getElementById('wizardClaimingDate').value);
            formData.append('additional_instructions', document.getElementById('wizardInstructions').value);

            // Attach requirement files
            for (const [reqId, file] of Object.entries(this.uploadedReqFiles)) {
                formData.append(`req_${reqId}`, file);
            }

            const res = await api.upload('/requests', formData);
            const reqData = res.data;

            // Step 5: Success Screen
            document.getElementById('successTrackingNumber').textContent = reqData.tracking_number;
            document.getElementById('successAmountDue').textContent = utils.formatCurrency(reqData.amount_due);

            this.goToStep(5);
            Toast.success('Request submitted successfully!', 'Congratulations');

            // Reload dashboard requests
            await this.loadDashboardSummary();
            await this.loadMyRequests();
        } catch (e) {
            Toast.error(e.message || 'Failed to submit document request.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
            }
        }
    },

    copyTrackingNumber() {
        const text = document.getElementById('successTrackingNumber')?.textContent;
        if (text) {
            navigator.clipboard.writeText(text);
            Toast.success(`Tracking number ${text} copied to clipboard!`);
        }
    },

    async viewRequestDetail(requestId) {
        try {
            const res = await api.get(`/requests/${requestId}`);
            const data = res.data;
            const r = data.request;
            const timeline = data.timeline || [];
            const reqs = data.uploaded_requirements || [];
            const payment = data.payment || null;
            const release = data.release || null;

            const modal = document.getElementById('requestDetailModal');
            if (!modal) return;

            document.getElementById('mdlTracking').textContent = r.tracking_number;
            document.getElementById('mdlStatus').innerHTML = utils.getStatusBadge(r.current_status);
            document.getElementById('mdlDocName').textContent = r.document_name;
            document.getElementById('mdlCopies').textContent = r.copies;
            document.getElementById('mdlPurpose').textContent = r.purpose;
            document.getElementById('mdlReleaseMethod').textContent = r.release_method;
            document.getElementById('mdlAmountDue').textContent = utils.formatCurrency(r.amount_due);
            document.getElementById('mdlAmountPaid').textContent = utils.formatCurrency(r.amount_paid);
            document.getElementById('mdlPaymentStatus').textContent = r.payment_status;
            document.getElementById('mdlSubmittedAt').textContent = utils.formatDate(r.submitted_at);
            document.getElementById('mdlTargetDate').textContent = utils.formatDate(r.target_completion_date, false);

            // Timeline rendering
            const timelineEl = document.getElementById('mdlTimeline');
            if (timelineEl) {
                timelineEl.innerHTML = timeline.map((t, idx) => `
                    <div class="timeline-item ${idx === timeline.length - 1 ? 'active' : 'completed'}">
                        <div class="timeline-point"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-title">${utils.escapeHtml(t.new_status)}</span>
                                <span class="timeline-date">${utils.formatDate(t.created_at)}</span>
                            </div>
                            <div class="timeline-remarks">${utils.escapeHtml(t.remarks || 'Status logged.')}</div>
                        </div>
                    </div>
                `).join('');
            }

            // Requirements rendering
            const reqsEl = document.getElementById('mdlRequirements');
            if (reqsEl) {
                if (reqs.length === 0) {
                    reqsEl.innerHTML = `<p style="font-size:0.85rem;color:var(--slate-500);">No attached requirement files.</p>`;
                } else {
                    reqsEl.innerHTML = reqs.map(rq => `
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0.85rem;background:var(--slate-50);border-radius:var(--radius-sm);margin-bottom:0.5rem;border:1px solid var(--border-color);">
                            <div>
                                <div style="font-weight:600;font-size:0.85rem;">${utils.escapeHtml(rq.requirement_name)}</div>
                                <div style="font-size:0.75rem;color:var(--slate-500);">${utils.escapeHtml(rq.original_filename)} • ${(rq.file_size / 1024).toFixed(0)} KB</div>
                            </div>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span class="badge ${rq.verification_status === 'Verified' ? 'badge-active' : 'badge-payment'}" style="font-size:0.7rem;">${rq.verification_status}</span>
                                <a href="${API_BASE}/files/download?type=requirement&id=${rq.request_requirement_id}" class="btn btn-secondary btn-sm" target="_blank">Download</a>
                            </div>
                        </div>
                    `).join('');
                }
            }

            // Payment Action Area
            const payActionEl = document.getElementById('mdlPaymentActionArea');
            if (payActionEl) {
                if (r.current_status === 'PENDING PAYMENT' && parseFloat(r.amount_due) > 0) {
                    payActionEl.innerHTML = `
                        <div style="padding:1rem;background:#FEF3C7;border-radius:var(--radius-md);margin-top:1rem;">
                            <h4 style="font-size:0.9rem;font-weight:700;color:#92400E;margin-bottom:0.25rem;">Payment Required</h4>
                            <p style="font-size:0.8rem;color:#78350F;margin-bottom:0;">Please pay ${utils.formatCurrency(r.amount_due)} in person at the Registrar/Personnel office. Online payment is not available.</p>
                        </div>
                    `;
                } else if (r.current_status === 'READY FOR RELEASE') {
                    payActionEl.innerHTML = `
                        <div style="padding:1rem;background:#D1FAE5;border-radius:var(--radius-md);margin-top:1rem;color:#065F46;">
                            <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.25rem;">Document Ready for Pickup</h4>
                            <p style="font-size:0.8rem;margin:0;">Your document is ready. Please pick it up at the Registrar/Personnel office.</p>
                        </div>
                    `;
                } else if (['RELEASED', 'COMPLETED'].includes(r.current_status)) {
                    payActionEl.innerHTML = `
                        <div style="padding:1rem;background:#D1FAE5;border-radius:var(--radius-md);margin-top:1rem;color:#065F46;">
                            <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.25rem;">Document Released</h4>
                            <p style="font-size:0.8rem;margin:0;">This document request has been released and completed.</p>
                        </div>
                    `;
                } else if (r.payment_status === 'For Verification') {
                    payActionEl.innerHTML = `
                        <div style="padding:0.75rem 1rem;background:#EFF6FF;border-radius:var(--radius-md);margin-top:1rem;color:#1E40AF;font-size:0.85rem;">
                            Payment reference submitted (${payment?.reference_number || 'Under Review'}). Awaiting verification by the Registrar.
                        </div>
                    `;
                } else {
                    payActionEl.innerHTML = '';
                }
            }

            modal.classList.add('show');
        } catch (e) {
            Toast.error('Failed to load request details.');
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
        btns += `<button type="button" class="pagination-btn" ${current === 1 ? 'disabled' : ''} onclick="StudentApp.loadMyRequests(${current - 1})">Prev</button>`;

        for (let p = 1; p <= total; p++) {
            btns += `<button type="button" class="pagination-btn ${p === current ? 'active' : ''}" onclick="StudentApp.loadMyRequests(${p})">${p}</button>`;
        }

        btns += `<button type="button" class="pagination-btn" ${current === total ? 'disabled' : ''} onclick="StudentApp.loadMyRequests(${current + 1})">Next</button>`;

        container.innerHTML = `
            <div class="pagination-summary">Showing page ${current} of ${total} (${meta.total} records)</div>
            <div class="pagination-controls">${btns}</div>
        `;
    }
};

window.StudentApp = StudentApp;
window.viewRequestDetails = (reqId) => StudentApp.viewRequestDetail(reqId);

const existingViewChangedHandler = window.onViewChanged;
window.onViewChanged = (viewId) => {
    if (existingViewChangedHandler) {
        existingViewChangedHandler(viewId);
    }
    if (viewId === 'requests') {
        StudentApp.loadMyRequests(1);
    }
};
