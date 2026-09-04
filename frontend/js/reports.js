/**
 * COLM Registrar Document Request and Tracking System
 * Institutional Reports & Analytics Engine (13 Official Reports)
 */

const ReportsApp = {
    currentReport: 'summary',

    openRequestFilter(scope = '', status = '') {
        const statusFilter = document.getElementById('reqStatusFilter');
        if (statusFilter) statusFilter.value = status;
        if (window.RegistrarApp) {
            RegistrarApp.requestScope = scope;
            navigation.switchView('requests');
            RegistrarApp.loadAllRequests(1);
        }
    },

    async init() {
        this.setupTabs();
        await this.loadReport('summary');
    },

    setupTabs() {
        const tabs = document.querySelectorAll('.report-tab-btn');
        tabs.forEach(t => {
            t.addEventListener('click', (e) => {
                e.preventDefault();
                tabs.forEach(btn => btn.classList.remove('active'));
                t.classList.add('active');

                const reportKey = t.getAttribute('data-report');
                this.loadReport(reportKey);
            });
        });
    },

    async loadReport(key) {
        this.currentReport = key;
        const container = document.getElementById('reportDisplayArea');
        if (!container) return;

        container.innerHTML = `<div class="loading-overlay"><div class="spinner"></div><p>Generating institutional report...</p></div>`;

        try {
            switch (key) {
                case 'summary':
                    await this.renderSummaryReport(container);
                    break;
                case 'daily':
                    await this.renderDailyReport(container);
                    break;
                case 'monthly':
                    await this.renderMonthlyReport(container);
                    break;
                case 'document-types':
                    await this.renderDocTypesReport(container);
                    break;
                case 'programs':
                    await this.renderProgramsReport(container);
                    break;
                case 'pending':
                    await this.renderPendingReport(container);
                    break;
                case 'completed':
                    await this.renderCompletedReport(container);
                    break;
                case 'released':
                    await this.renderReleasedReport(container);
                    break;
                case 'cancelled':
                    await this.renderCancelledReport(container);
                    break;
                case 'personnel':
                    await this.renderPersonnelReport(container);
                    break;
                case 'processing-time':
                    await this.renderProcessingTimeReport(container);
                    break;
                case 'overdue':
                    await this.renderOverdueReport(container);
                    break;
                case 'payments':
                    await this.renderPaymentsReport(container);
                    break;
                default:
                    await this.renderSummaryReport(container);
            }
        } catch (e) {
            container.innerHTML = `<div class="card" style="color:#024E28;text-align:center;padding:2rem;">Failed to load report: ${e.message}</div>`;
        }
    },

    // 1. Overall Summary Report
    async renderSummaryReport(container) {
        const res = await api.get('/reports/summary');
        const data = res.data;
        const k = data.kpis;
        const topDocs = data.top_documents || [];
        const topPrograms = data.top_programs || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Executive Summary & Operational KPIs</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print Report</button>
                </div>

                <div class="stats-grid">
                    <div class="stat-card is-filterable" onclick="ReportsApp.openRequestFilter('')" title="Show all requests">
                        <div class="stat-icon"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l3 3v13a2 2 0 01-2 2z"/></svg></div>
                        <div class="stat-info">
                            <span class="stat-value">${k.total_requests}</span>
                            <span class="stat-label">Total Requests</span>
                        </div>
                    </div>
                    <div class="stat-card is-filterable" onclick="ReportsApp.openRequestFilter('active')" title="Show active requests">
                        <div class="stat-icon" style="background:#FEF3C7;color:#D97706;"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" stroke-width="2" d="M12 8v4l2 2"/></svg></div>
                        <div class="stat-info">
                            <span class="stat-value">${k.active_pending}</span>
                            <span class="stat-label">Active Workload</span>
                        </div>
                    </div>
                    <div class="stat-card is-filterable" onclick="ReportsApp.openRequestFilter('overdue')" title="Show overdue requests">
                        <div class="stat-icon" style="background:#EFFAF2;color:#024E28;"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5 20h14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 17A2 2 0 005 20z"/></svg></div>
                        <div class="stat-info">
                            <span class="stat-value">${k.overdue_count}</span>
                            <span class="stat-label">Overdue / Urgent</span>
                        </div>
                    </div>
                    <div class="stat-card is-filterable" onclick="ReportsApp.openRequestFilter('released', 'RELEASED')" title="Show released requests">
                        <div class="stat-icon" style="background:#D1FAE5;color:#059669;"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6"/></svg></div>
                        <div class="stat-info">
                            <span class="stat-value">${k.status_released}</span>
                            <span class="stat-label">Total Released</span>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;margin-top:1.5rem;">
                    <div class="card" style="margin-bottom:0;background:var(--slate-50);">
                        <h4 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;color:var(--slate-800);">Top Requested Document Types</h4>
                        <div>
                            ${topDocs.map(d => `
                                <div style="margin-bottom:0.75rem;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:3px;">
                                        <span>${utils.escapeHtml(d.document_name)}</span>
                                        <strong>${d.total_requests} reqs</strong>
                                    </div>
                                    <div style="height:8px;background:var(--slate-200);border-radius:4px;overflow:hidden;">
                                        <div style="width:${Math.min(100, (d.total_requests / (k.total_requests || 1)) * 100)}%;height:100%;background:var(--primary-700);"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    <div class="card" style="margin-bottom:0;background:var(--slate-50);">
                        <h4 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;color:var(--slate-800);">Requests by Academic Program</h4>
                        <div>
                            ${topPrograms.map(p => `
                                <div style="margin-bottom:0.75rem;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:3px;">
                                        <span>${utils.escapeHtml(p.program_name)}</span>
                                        <strong>${p.total_requests} reqs</strong>
                                    </div>
                                    <div style="height:8px;background:var(--slate-200);border-radius:4px;overflow:hidden;">
                                        <div style="width:${Math.min(100, (p.total_requests / (k.total_requests || 1)) * 100)}%;height:100%;background:var(--gold-500);"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // 2. Daily Report
    async renderDailyReport(container) {
        const today = new Date().toISOString().slice(0, 10);
        const res = await api.get(`/reports/daily?date=${today}`);
        const data = res.data;

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daily Document Requests Report (${data.report_date})</h3>
                    <div style="display:flex;gap:0.5rem;">
                        <a href="${API_BASE}/reports/export?type=daily" class="btn btn-secondary btn-sm"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg> Export CSV</a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Copies</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.records.length === 0 ? '<tr><td colspan="8" class="text-center" style="padding:2rem;">No requests logged today.</td></tr>' : 
                                data.records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)} (${r.student_number})</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${r.copies}</td>
                                    <td>${utils.formatCurrency(r.amount_due)}</td>
                                    <td>${r.payment_status}</td>
                                    <td>${utils.getStatusBadge(r.current_status)}</td>
                                    <td>${utils.formatDate(r.submitted_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 3. Monthly Report
    async renderMonthlyReport(container) {
        const d = new Date();
        const res = await api.get(`/reports/monthly?year=${d.getFullYear()}&month=${d.getMonth() + 1}`);
        const data = res.data;

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Monthly Document Requests Breakdown (${data.month}/${data.year})</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;">
                    <div><strong>Total Requests:</strong> ${data.total_month_requests}</div>
                    <div><strong>Total Revenue:</strong> ${utils.formatCurrency(data.total_month_revenue)}</div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Requests</th>
                                <th>Pending</th>
                                <th>Released</th>
                                <th>Total Assessed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.records.length === 0 ? '<tr><td colspan="5" class="text-center" style="padding:2rem;">No requests in this month.</td></tr>' :
                                data.records.map(m => `
                                <tr>
                                    <td><strong>${m.request_date}</strong></td>
                                    <td>${m.total_requests}</td>
                                    <td>${m.pending_count}</td>
                                    <td>${m.released_count}</td>
                                    <td>${utils.formatCurrency(m.total_revenue)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 4. Document Types Report
    async renderDocTypesReport(container) {
        const res = await api.get('/reports/document-types');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Requests by Document Type</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Document Name</th>
                                <th>Unit Fee</th>
                                <th>Total Requests</th>
                                <th>Copies</th>
                                <th>Total Assessed</th>
                                <th>Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.map(d => `
                                <tr>
                                    <td><strong>${d.document_code}</strong></td>
                                    <td>${utils.escapeHtml(d.document_name)}</td>
                                    <td>${utils.formatCurrency(d.fee_amount)}</td>
                                    <td><strong>${d.total_requests}</strong></td>
                                    <td>${d.total_copies || 0}</td>
                                    <td>${utils.formatCurrency(d.total_assessed || 0)}</td>
                                    <td>${d.total_released || 0}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 5. Programs Report
    async renderProgramsReport(container) {
        const res = await api.get('/reports/programs');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Requests by Academic Program</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Academic Program</th>
                                <th>Total Requests</th>
                                <th>Completed / Released</th>
                                <th>Total Volume (PHP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.map(p => `
                                <tr>
                                    <td><strong>${utils.escapeHtml(p.program_name)}</strong></td>
                                    <td>${p.total_requests}</td>
                                    <td>${p.completed_requests}</td>
                                    <td>${utils.formatCurrency(p.total_amount)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 6. Pending Requests Report
    async renderPendingReport(container) {
        const res = await api.get('/reports/pending');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pending Requests Queue (${records.length})</h3>
                    <div style="display:flex;gap:0.5rem;">
                        <a href="${API_BASE}/reports/export?type=pending" class="btn btn-secondary btn-sm"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg> Export CSV</a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Overdue Status</th>
                                <th>Target Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="7" class="text-center" style="padding:2rem;">No pending requests!</td></tr>' :
                                records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)}</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${utils.getStatusBadge(r.current_status)}</td>
                                    <td>${r.assigned_personnel || '<em>Unassigned</em>'}</td>
                                    <td>${utils.getOverdueBadge(r.overdue_status)}</td>
                                    <td>${utils.formatDate(r.target_completion_date, false)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 7. Completed Requests Report
    async renderCompletedReport(container) {
        const res = await api.get('/reports/completed');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Completed / Ready for Release Requests (${records.length})</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Completed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="5" class="text-center" style="padding:2rem;">No completed records.</td></tr>' :
                                records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)}</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${utils.getStatusBadge(r.current_status)}</td>
                                    <td>${utils.formatDate(r.completed_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 8. Released Documents Report
    async renderReleasedReport(container) {
        const res = await api.get('/reports/released');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Released Documents & Claiming Audit</h3>
                    <div style="display:flex;gap:0.5rem;">
                        <a href="${API_BASE}/reports/export?type=released" class="btn btn-secondary btn-sm"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg> Export CSV</a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 012 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Method</th>
                                <th>Recipient</th>
                                <th>Released By</th>
                                <th>Date Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="7" class="text-center" style="padding:2rem;">No released documents.</td></tr>' :
                                records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)} (${r.student_number})</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${r.release_method}</td>
                                    <td>${r.recipient_type} ${r.representative_name ? `(${r.representative_name})` : ''}</td>
                                    <td>${r.released_by_user}</td>
                                    <td>${utils.formatDate(r.released_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 9. Cancelled / Rejected Requests Report
    async renderCancelledReport(container) {
        const res = await api.get('/reports/cancelled');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cancelled / Rejected Requests</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Reason / Purpose</th>
                                <th>Cancelled Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="5" class="text-center" style="padding:2rem;">No cancelled requests.</td></tr>' :
                                records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)}</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${utils.escapeHtml(r.purpose)}</td>
                                    <td>${utils.formatDate(r.cancelled_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 10. Personnel Performance Report
    async renderPersonnelReport(container) {
        const res = await api.get('/reports/personnel');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Registrar Personnel Workload & Resolution</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Personnel</th>
                                <th>Role</th>
                                <th>Total Assigned</th>
                                <th>Active Workload</th>
                                <th>Completed</th>
                                <th>Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.map(p => `
                                <tr>
                                    <td><strong>${utils.escapeHtml(p.username)}</strong></td>
                                    <td>${p.role}</td>
                                    <td><strong>${p.total_assigned}</strong></td>
                                    <td><span class="badge badge-payment">${p.active_workload} active</span></td>
                                    <td>${p.completed_count}</td>
                                    <td><span class="badge badge-active">${p.released_count} released</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 11. Average Processing Time Report
    async renderProcessingTimeReport(container) {
        const res = await api.get('/reports/processing-time');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Average Processing Turnaround Time</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Target Days</th>
                                <th>Completed Sample</th>
                                <th>Avg Actual Days</th>
                                <th>Fastest</th>
                                <th>Slowest</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="6" class="text-center" style="padding:2rem;">No completion metrics yet.</td></tr>' :
                                records.map(d => `
                                <tr>
                                    <td><strong>${utils.escapeHtml(d.document_name)}</strong> (${d.document_code})</td>
                                    <td>~${d.target_days} days</td>
                                    <td>${d.total_completed}</td>
                                    <td><strong style="color:#024E28;">${d.avg_actual_days} days</strong></td>
                                    <td>${d.min_days} days</td>
                                    <td>${d.max_days} days</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 12. Overdue Requests Report
    async renderOverdueReport(container) {
        const res = await api.get('/reports/overdue');
        const records = res.data || [];

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Unreleased & Overdue Requests (${records.length})</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Overdue Metric</th>
                                <th>Submitted At</th>
                                <th>Target Completion</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.length === 0 ? '<tr><td colspan="7" class="text-center" style="padding:2rem;color:#059669;font-weight:700;">No overdue requests found. All processing is on schedule!</td></tr>' :
                                records.map(r => `
                                <tr>
                                    <td><strong>${r.tracking_number}</strong></td>
                                    <td>${utils.escapeHtml(r.student_name)}</td>
                                    <td>${utils.escapeHtml(r.document_name)}</td>
                                    <td>${utils.getStatusBadge(r.current_status)}</td>
                                    <td>${utils.getOverdueBadge(r.overdue_status)}</td>
                                    <td>${utils.formatDate(r.submitted_at)}</td>
                                    <td>${utils.formatDate(r.target_completion_date, false)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // 13. Payments Assessment Report
    async renderPaymentsReport(container) {
        const res = await api.get('/reports/payments');
        const data = res.data;

        container.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment Collections & Verification Report</h3>
                    <div style="display:flex;gap:0.5rem;">
                        <a href="${API_BASE}/reports/export?type=payments" class="btn btn-secondary btn-sm"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg> Export CSV</a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 012 2h-2M6 14h12v7H6v-7z"/></svg> Print</button>
                    </div>
                </div>

                <div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;">
                    <div><strong>Total Verified Collections:</strong> <span style="color:#059669;font-weight:700;">${utils.formatCurrency(data.total_paid)}</span></div>
                    <div><strong>Pending Verification:</strong> <span style="color:#D97706;font-weight:700;">${utils.formatCurrency(data.total_pending)}</span></div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference #</th>
                                <th>Tracking #</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.records.length === 0 ? '<tr><td colspan="8" class="text-center" style="padding:2rem;">No payment records found.</td></tr>' :
                                data.records.map(p => `
                                <tr>
                                    <td><strong>${utils.escapeHtml(p.reference_number)}</strong></td>
                                    <td>${p.tracking_number}</td>
                                    <td>${utils.escapeHtml(p.student_name)}</td>
                                    <td>${utils.formatCurrency(p.amount)}</td>
                                    <td>${p.payment_method}</td>
                                    <td>
                                        <span class="badge ${p.payment_status === 'Paid' ? 'badge-active' : (p.payment_status === 'For Verification' ? 'badge-payment' : 'badge-rejected')}">
                                            ${p.payment_status}
                                        </span>
                                    </td>
                                    <td>${p.verifier_username || '—'}</td>
                                    <td>${p.payment_date}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
};

window.ReportsApp = ReportsApp;
