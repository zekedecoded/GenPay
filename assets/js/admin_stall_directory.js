(function () {
    const config = window.stallDirectoryConfig || {};
    const endpoint = config.endpoint;

    if (!endpoint) {
        return;
    }

    const state = {
        merchantId: 0,
        merchantUserId: 0,
        leaseId: 0,
        paymentPage: 1,
        inventoryPage: 1,
        activityPage: 1,
        perPage: 10,
    };

    const modalEl = document.getElementById('stallDetailModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const loading = document.getElementById('stallDetailLoading');
    const content = document.getElementById('stallDetailContent');
    const alertBox = document.getElementById('stallDetailAlert');

    const money = (value) => {
        const amount = Number(value || 0);
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.innerHTML = message
            ? `<div class="alert alert-${type} py-2">${escapeHtml(message)}</div>`
            : '';
    };

    const getJson = async (params) => {
        const url = `${endpoint}?${new URLSearchParams(params).toString()}`;
        const response = await fetch(url);
        return response.json();
    };

    const postForm = async (formData) => {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
        });
        return response.json();
    };

    const renderPager = (targetId, pageData, onPage) => {
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
    };

    const renderLease = (lease) => {
        const summary = document.getElementById('leaseSummary');
        const leaseForm = document.getElementById('leaseUpdateForm');
        const paymentForm = document.getElementById('rentPaymentForm');

        if (!lease) {
            state.leaseId = 0;
            if (summary) {
                summary.innerHTML = '<div class="alert alert-warning">No lease contract is attached to this stall yet.</div>';
            }
            if (leaseForm) leaseForm.classList.add('d-none');
            if (paymentForm) paymentForm.classList.add('d-none');
            return;
        }

        state.leaseId = Number(lease.id);
        if (leaseForm) leaseForm.classList.remove('d-none');
        if (paymentForm) paymentForm.classList.remove('d-none');

        summary.innerHTML = `
            <div class="row g-3">
                <div class="col-md-3"><div class="detail-stat"><span>Contract Status</span><strong>${escapeHtml(lease.status)}</strong></div></div>
                <div class="col-md-3"><div class="detail-stat"><span>Lifespan</span><strong>${Number(lease.lifespan_months || 0)} month(s)</strong></div></div>
                <div class="col-md-3"><div class="detail-stat"><span>Paid To Date</span><strong>${money(lease.paid_total)}</strong></div></div>
                <div class="col-md-3"><div class="detail-stat"><span>Balance Due</span><strong>${money(lease.balance_due)}</strong></div></div>
            </div>
            <div class="small text-muted mt-2">Current month lease status: <strong>${escapeHtml(lease.current_month_status)}</strong></div>
        `;

        document.getElementById('leaseIdInput').value = lease.id;
        document.getElementById('paymentLeaseIdInput').value = lease.id;
        document.getElementById('leaseMonthlyRent').value = lease.monthly_rent;
        document.getElementById('leaseDeposit').value = lease.deposit_amount;
        document.getElementById('leaseStart').value = lease.lease_start;
        document.getElementById('leaseEnd').value = lease.lease_end;
        document.getElementById('leaseNextDue').value = lease.next_due_date;
        document.getElementById('leaseStatus').value = lease.status;
        document.getElementById('leaseNotes').value = lease.contract_notes || '';
    };

    const renderPayments = (payments) => {
        const body = document.getElementById('rentPaymentsBody');
        if (!body) return;

        if (!payments.rows || payments.rows.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No rent payments found for this filter.</td></tr>';
        } else {
            body.innerHTML = payments.rows.map((row) => `
                <tr>
                    <td>${escapeHtml(row.reference_no)}</td>
                    <td>${escapeHtml(row.period_covered)}</td>
                    <td>${escapeHtml(row.payment_date)}</td>
                    <td>${money(row.amount_paid)}</td>
                    <td>${escapeHtml((row.payment_method || 'cash').replace('_', ' '))}</td>
                </tr>
            `).join('');
        }

        renderPager('paymentPager', payments, (page) => {
            state.paymentPage = page;
            loadPayments();
        });
    };

    const renderInventory = (inventory) => {
        const body = document.getElementById('inventoryComplianceBody');
        if (!body) return;

        if (!inventory.rows || inventory.rows.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No inventory items found for this filter.</td></tr>';
        } else {
            body.innerHTML = inventory.rows.map((row) => {
                const restricted = Number(row.is_restricted) === 1;
                const available = Number(row.is_available) === 1 && !restricted;
                return `
                    <tr>
                        <td>
                            <strong>${escapeHtml(row.product_name)}</strong>
                            ${row.sku ? `<br><small class="text-muted">${escapeHtml(row.sku)}</small>` : ''}
                            ${row.restriction_note ? `<br><small class="text-danger">${escapeHtml(row.restriction_note)}</small>` : ''}
                        </td>
                        <td>${escapeHtml(row.category)}</td>
                        <td>${money(row.price)}</td>
                        <td>${available ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-secondary">Disabled</span>'}</td>
                        <td>${restricted ? '<span class="badge bg-danger">Restricted</span>' : '<span class="badge bg-success">Allowed</span>'}</td>
                        <td class="text-end">
                            <button type="button"
                                class="btn btn-sm ${restricted ? 'btn-outline-success' : 'btn-outline-danger'} js-toggle-restriction"
                                data-item-id="${Number(row.id)}"
                                data-restricted="${restricted ? 0 : 1}">
                                ${restricted ? 'Clear' : 'Flag/Restrict'}
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        body.querySelectorAll('.js-toggle-restriction').forEach((button) => {
            button.addEventListener('click', async () => {
                const shouldRestrict = Number(button.dataset.restricted) === 1;
                const note = shouldRestrict
                    ? window.prompt('Restriction reason:', 'Restricted by school nutritional compliance review.') || ''
                    : '';
                const form = new FormData();
                form.append('action', 'toggle_product_restriction');
                form.append('item_id', button.dataset.itemId);
                form.append('restricted', shouldRestrict ? '1' : '0');
                form.append('note', note);

                const data = await postForm(form);
                setAlert(data.message || '', data.success ? 'success' : 'danger');
                if (data.success) {
                    await loadInventory();
                }
            });
        });

        renderPager('inventoryPager', inventory, (page) => {
            state.inventoryPage = page;
            loadInventory();
        });
    };

    // Merchant Activity tab — audited management actions (products, staff,
    // profile, banned items), matching what the dashboard badge counts.
    const ACTIVITY_META = {
        MENU_MUTATION: { label: 'Product / Menu', cls: 'bg-success' },
        USER_ACCOUNT: { label: 'Staff / Profile', cls: 'bg-primary' },
        PRODUCT_RESTRICTION: { label: 'Banned Item', cls: 'bg-danger' },
    };

    // Human summary from the audit row's new_value JSON: pick a few telling
    // scalar fields instead of dumping the raw payload.
    const activitySummary = (row) => {
        let data = null;
        try { data = JSON.parse(row.new_value); } catch (e) { /* not JSON */ }
        if (!data || typeof data !== 'object') {
            return row.new_value ? String(row.new_value).slice(0, 120) : '—';
        }
        const preferred = ['event', 'product_name', 'attempted_name', 'name', 'email', 'stall_name', 'sku', 'matched_reason', 'status', 'restriction_note', 'reason', 'price'];
        const parts = [];
        const push = (key, value) => parts.push(`${key.replace(/_/g, ' ')}: ${value}`);
        preferred.forEach((key) => {
            if (parts.length < 3 && data[key] !== undefined && data[key] !== null && data[key] !== '' && typeof data[key] !== 'object') {
                push(key, data[key]);
            }
        });
        if (!parts.length) {
            Object.entries(data).some(([key, value]) => {
                if (value !== null && value !== '' && typeof value !== 'object') push(key, value);
                return parts.length >= 3;
            });
        }
        return parts.join(' · ') || '—';
    };

    const renderActivity = (activity) => {
        const body = document.getElementById('merchantActivityBody');
        if (!body) return;

        if (!activity.rows || activity.rows.length === 0) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No merchant activity recorded yet.</td></tr>';
        } else {
            body.innerHTML = activity.rows.map((row) => {
                const meta = ACTIVITY_META[row.action_type] || { label: row.action_type, cls: 'bg-secondary' };
                const when = new Date(String(row.timestamp).replace(' ', 'T'));
                const whenText = Number.isNaN(when.getTime())
                    ? String(row.timestamp)
                    : when.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                return `
                    <tr>
                        <td class="text-nowrap">${escapeHtml(whenText)}</td>
                        <td>
                            <strong>${escapeHtml(row.actor_name || 'Unknown user')}</strong>
                            <br><small class="text-muted">${escapeHtml(row.user_role || '')}</small>
                        </td>
                        <td><span class="badge ${meta.cls}">${escapeHtml(meta.label)}</span></td>
                        <td><small>${escapeHtml(activitySummary(row))}</small></td>
                    </tr>
                `;
            }).join('');
        }

        renderPager('activityPager', activity, (page) => {
            state.activityPage = page;
            loadActivity();
        });
    };

    const loadActivity = async () => {
        if (!state.merchantUserId) return;
        const data = await getJson({
            action: 'activity',
            merchant_user_id: state.merchantUserId,
            page: state.activityPage,
            per_page: state.perPage,
        });
        if (data.success) {
            renderActivity(data.activity);
        } else {
            setAlert(data.message || 'Unable to load merchant activity.', 'danger');
        }
    };

    const loadPayments = async () => {
        if (!state.leaseId) return;
        const data = await getJson({
            action: 'payments',
            lease_id: state.leaseId,
            from: document.getElementById('paymentFilterFrom').value,
            to: document.getElementById('paymentFilterTo').value,
            page: state.paymentPage,
            per_page: state.perPage,
        });
        if (data.success) {
            renderPayments(data.payments);
        } else {
            setAlert(data.message || 'Unable to load rent payments.', 'danger');
        }
    };

    const loadInventory = async () => {
        if (!state.merchantUserId) return;
        const data = await getJson({
            action: 'inventory',
            merchant_user_id: state.merchantUserId,
            search: document.getElementById('inventorySearch').value,
            category: document.getElementById('inventoryCategory').value,
            restriction: document.getElementById('inventoryRestriction').value,
            page: state.inventoryPage,
            per_page: state.perPage,
        });
        if (data.success) {
            renderInventory(data.inventory);
        } else {
            setAlert(data.message || 'Unable to load inventory.', 'danger');
        }
    };

    const openStall = async (merchantId) => {
        state.merchantId = merchantId;
        state.paymentPage = 1;
        state.inventoryPage = 1;
        state.activityPage = 1;
        setAlert('');
        loading.classList.remove('d-none');
        content.classList.add('d-none');
        modal.show();

        const data = await getJson({
            action: 'details',
            merchant_id: merchantId,
            payments_page: 1,
            inventory_page: 1,
            per_page: state.perPage,
        });

        loading.classList.add('d-none');

        if (!data.success) {
            setAlert(data.message || 'Unable to load stall details.', 'danger');
            return;
        }

        state.merchantUserId = Number(data.summary.merchant_user_id);
        state.leaseId = data.lease ? Number(data.lease.id) : 0;

        document.getElementById('stallDetailTitle').textContent = data.summary.stall_name;
        document.getElementById('stallDetailSubtitle').textContent =
            `${data.summary.proprietor_name} | ${data.summary.operational_status.replace('_', ' ')}`;

        renderLease(data.lease);
        renderPayments(data.payments);
        renderInventory(data.inventory);
        renderActivity(data.activity || { rows: [], page: 1, total_pages: 1, total: 0 });
        content.classList.remove('d-none');

        // The server stamped this stall as checked — drop its unread badge.
        document
            .querySelector(`.js-stall-card[data-merchant-id="${merchantId}"] .tenant-card-notif`)
            ?.remove();
    };

    document.querySelectorAll('.js-stall-card').forEach((card) => {
        card.addEventListener('click', () => openStall(Number(card.dataset.merchantId)));
    });

    document.getElementById('applyPaymentFilters')?.addEventListener('click', () => {
        state.paymentPage = 1;
        loadPayments();
    });

    document.getElementById('applyInventoryFilters')?.addEventListener('click', () => {
        state.inventoryPage = 1;
        loadInventory();
    });

    document.getElementById('leaseUpdateForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = await postForm(new FormData(event.currentTarget));
        setAlert(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) {
            await openStall(state.merchantId);
        }
    });

    document.getElementById('rentPaymentForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = await postForm(new FormData(event.currentTarget));
        setAlert(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) {
            event.currentTarget.reset();
            await openStall(state.merchantId);
        }
    });
})();
