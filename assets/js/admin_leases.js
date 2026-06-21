(function () {
    const config = window.leaseApiConfig || {};
    const endpoint = config.endpoint;
    if (!endpoint) return;

    const state = {
        leaseId: 0,
        paymentPage: 1,
        perPage: 10,
        dirty: false,
    };

    const money = (value) => {
        const amount = Number(value || 0);
        return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const getJson = async (params) => {
        const url = `${endpoint}?${new URLSearchParams(params).toString()}`;
        const response = await fetch(url);
        return response.json();
    };

    const postForm = async (formData) => {
        const response = await fetch(endpoint, { method: 'POST', body: formData });
        return response.json();
    };

    /* ── New Lease modal: merchant picker ─────────────────────────────── */
    let merchantsLoaded = false;

    async function loadMerchantPicker() {
        const select = document.getElementById('merchantUserId');
        const hint = document.getElementById('merchantPickerHint');
        if (!select) return;

        select.innerHTML = '<option value="">Loading merchants&hellip;</option>';
        const data = await getJson({ action: 'list_merchants' });

        if (!data.success || !data.merchants || !data.merchants.length) {
            select.innerHTML = '<option value="">No registered merchants found</option>';
            return;
        }

        select.innerHTML = '<option value="">Select a merchant&hellip;</option>' + data.merchants.map((m) => `
            <option value="${m.merchant_user_id}" data-stall-name="${escapeHtml(m.stall_name)}" data-active="${m.has_active_lease ? 1 : 0}">
                ${escapeHtml(m.proprietor_name)} &mdash; ${escapeHtml(m.stall_name)}${m.has_active_lease ? ' (has active lease)' : ''}
            </option>
        `).join('');
        merchantsLoaded = true;

        select.addEventListener('change', () => {
            const opt = select.options[select.selectedIndex];
            const stallNameField = document.getElementById('stallName');
            if (opt && opt.dataset.stallName && !stallNameField.value) {
                stallNameField.value = opt.dataset.stallName;
            }
            hint.textContent = opt && opt.dataset.active === '1'
                ? 'This merchant already has an active lease. Creating a new one will not replace it.'
                : '';
        });
    }

    window.openNewLeaseModal = function () {
        document.getElementById('leaseForm').reset();
        document.getElementById('leaseFormMsg').innerHTML = '';
        const btn = document.getElementById('leaseSubmitBtn');
        btn.disabled = false;
        btn.textContent = 'Save Lease';
        if (!merchantsLoaded) loadMerchantPicker();
        new bootstrap.Modal(document.getElementById('leaseModal')).show();
    };

    document.getElementById('leaseForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('leaseSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        const msg = document.getElementById('leaseFormMsg');
        try {
            const data = await postForm(new FormData(this));
            if (data.success) {
                msg.innerHTML = '<div class="alert alert-success">Saved successfully. Refreshing…</div>';
                setTimeout(() => location.reload(), 1000);
            } else {
                msg.innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.message || 'Unknown error.') + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Lease';
            }
        } catch (err) {
            msg.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
            btn.disabled = false;
            btn.textContent = 'Save Lease';
        }
    });

    /* ── Ledger modal ──────────────────────────────────────────────────── */
    const ledgerModalEl = document.getElementById('ledgerModal');
    const ledgerModal = ledgerModalEl ? new bootstrap.Modal(ledgerModalEl) : null;
    const ledgerLoading = document.getElementById('ledgerLoading');
    const ledgerContent = document.getElementById('ledgerContent');
    const ledgerAlertBox = document.getElementById('ledgerAlert');

    const setLedgerAlert = (message, type = 'success') => {
        if (!ledgerAlertBox) return;
        ledgerAlertBox.innerHTML = message
            ? `<div class="alert alert-${type} py-2">${escapeHtml(message)}</div>`
            : '';
    };

    const statusBadgeClass = (status) => ({
        active: 'badge-success',
        expired: 'badge-danger',
        terminated: 'badge-secondary',
    }[status] || 'badge-warning');

    function renderLedgerStats(lease) {
        const stats = document.getElementById('ledgerStats');
        if (!stats) return;
        stats.innerHTML = `
            <div class="col-6 col-md-3"><div class="detail-stat"><span>Contract Status</span><strong><span class="${statusBadgeClass(lease.status)}">${escapeHtml(lease.status)}</span></strong></div></div>
            <div class="col-6 col-md-3"><div class="detail-stat"><span>Balance Due</span><strong style="${Number(lease.balance_due) > 0 ? 'color:#ef4444' : ''}">${money(lease.balance_due)}</strong></div></div>
            <div class="col-6 col-md-3"><div class="detail-stat"><span>Paid To Date</span><strong>${money(lease.paid_total)}</strong></div></div>
            <div class="col-6 col-md-3"><div class="detail-stat"><span>Next Due</span><strong>${escapeHtml(lease.next_due_date)}</strong></div></div>
        `;
    }

    function fillLedgerForms(lease) {
        document.getElementById('ledgerPayLeaseId').value = lease.id;
        document.getElementById('ledgerPayAmount').value = lease.monthly_rent;

        document.getElementById('ledgerEditLeaseId').value = lease.id;
        document.getElementById('ledgerStallNumber').value = lease.stall_number || '';
        document.getElementById('ledgerStallName').value = lease.stall_name || '';
        document.getElementById('ledgerMonthlyRent').value = lease.monthly_rent;
        document.getElementById('ledgerDeposit').value = lease.deposit_amount;
        document.getElementById('ledgerLeaseStart').value = lease.lease_start;
        document.getElementById('ledgerLeaseEnd').value = lease.lease_end;
        document.getElementById('ledgerNextDue').value = lease.next_due_date;
        document.getElementById('ledgerStatus').value = lease.status;
        document.getElementById('ledgerNotes').value = lease.contract_notes || '';
    }

    function renderPager(targetId, pageData, onPage) {
        const target = document.getElementById(targetId);
        if (!target) return;
        const page = Number(pageData.page || 1);
        const pages = Number(pageData.total_pages || 1);
        const total = Number(pageData.total || 0);
        target.innerHTML = `
            <span class="text-muted small">${total} record(s), page ${page} of ${pages}</span>
            <span class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Previous</button>
                <button type="button" class="btn btn-outline-secondary" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>Next</button>
            </span>
        `;
        target.querySelectorAll('button[data-page]').forEach((button) => {
            button.addEventListener('click', () => onPage(Number(button.dataset.page)));
        });
    }

    function renderPayments(payments) {
        const body = document.getElementById('ledgerPaymentsBody');
        if (!body) return;
        if (!payments.rows || !payments.rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No rent payments recorded yet.</td></tr>';
        } else {
            body.innerHTML = payments.rows.map((row) => `
                <tr>
                    <td>${escapeHtml(row.reference_no)}</td>
                    <td>${escapeHtml(row.period_covered)}</td>
                    <td>${escapeHtml(row.payment_date)}</td>
                    <td>${money(row.amount_paid)}</td>
                    <td>${escapeHtml((row.payment_method || 'cash').replace('_', ' '))}</td>
                    <td>${escapeHtml(row.notes || '')}</td>
                </tr>
            `).join('');
        }
        renderPager('ledgerPaymentPager', payments, (page) => {
            state.paymentPage = page;
            loadPayments();
        });
    }

    async function loadPayments() {
        if (!state.leaseId) return;
        const data = await getJson({
            action: 'get_ledger',
            lease_id: state.leaseId,
            page: state.paymentPage,
            per_page: state.perPage,
        });
        if (data.success) {
            renderPayments(data.payments);
        }
    }

    async function openLedger(leaseId, isFreshOpen = true) {
        state.leaseId = leaseId;
        state.paymentPage = 1;
        if (isFreshOpen) state.dirty = false;
        setLedgerAlert('');
        ledgerLoading.classList.remove('d-none');
        ledgerContent.classList.add('d-none');
        ledgerModal.show();

        const data = await getJson({ action: 'get_ledger', lease_id: leaseId, page: 1, per_page: state.perPage });
        ledgerLoading.classList.add('d-none');

        if (!data.success) {
            setLedgerAlert(data.message || 'Unable to load lease ledger.', 'danger');
            return;
        }

        document.getElementById('ledgerTitle').textContent = data.lease.stall_name;
        document.getElementById('ledgerSubtitle').textContent =
            `${data.merchant.name || data.merchant.email || 'Unknown merchant'} — Stall #${data.lease.stall_number}`;

        renderLedgerStats(data.lease);
        fillLedgerForms(data.lease);
        renderPayments(data.payments);
        ledgerContent.classList.remove('d-none');
    }

    document.querySelectorAll('.js-open-ledger').forEach((btn) => {
        btn.addEventListener('click', () => openLedger(Number(btn.dataset.leaseId)));
    });

    document.getElementById('ledgerEditForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = await postForm(new FormData(e.currentTarget));
        setLedgerAlert(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) {
            state.dirty = true;
            await openLedger(state.leaseId, false);
        }
    });

    document.getElementById('ledgerPaymentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = await postForm(new FormData(e.currentTarget));
        setLedgerAlert(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) {
            state.dirty = true;
            e.currentTarget.reset();
            document.getElementById('ledgerPayPeriod').value = new Date().toISOString().slice(0, 7);
            await openLedger(state.leaseId, false);
        }
    });

    ledgerModalEl?.addEventListener('hidden.bs.modal', () => {
        // Refresh the table/summary cards only if something actually changed.
        if (state.dirty) location.reload();
    });
})();
