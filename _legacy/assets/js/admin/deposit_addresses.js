/**
 * FILE: /assets/js/admin/deposit_addresses.js
 * ============================================================
 * CRUD for the crypto deposit addresses members are shown.
 *
 * Replaces the #set-deposit-address-form / #view-deposit-address-btn
 * handlers that lived in admin.js and wrote a single address into two
 * hardcoded `settings` columns.
 *
 * Relies on the globals admin.js exports: fetchApi, showToast,
 * showModal, closeModal.
 * ============================================================
 */
;(function ($) {
    "use strict";

    const ENDPOINT = '/api/admin/deposit_addresses.php';

    // Populated from the endpoint's `networks` list, so the <select> can
    // never drift from the server-side whitelist that validates against it.
    let networks = [];
    let pendingDeleteId = null;

    /** Text-only insertion. Addresses are user-controlled and never innerHTML'd. */
    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function renderStatusBadge(isActive) {
        const cls = isActive ? 'bg-Green' : 'bg-Orange';
        const label = isActive ? 'Active' : 'Hidden';
        return `<div class="box-status ${cls}"><span class="font-poppins key-sort">${label}</span></div>`;
    }

    function renderAddressRows(rows) {
        const body = $('#deposit-address-rows');
        body.empty();

        if (!rows || !rows.length) {
            body.html('<tr><td class="mvc-empty" colspan="8">No deposit addresses yet. Add one so members have somewhere to send funds.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            body.append(`
                <tr data-address-id="${row.id}">
                    <td>${esc(row.asset)}</td>
                    <td class="mvc-td-muted">${esc(networkLabel(row.network))}</td>
                    <td>${esc(row.label)}</td>
                    <td class="mvc-td-muted" title="${esc(row.address)}">${esc(row.address_short)}</td>
                    <td>$${Number(row.min_amount).toFixed(2)}</td>
                    <td>${renderStatusBadge(row.is_active)}</td>
                    <td class="mvc-td-muted">${esc(row.updated_at)}</td>
                    <td>
                        <button type="button" class="tf-button f12-bold address-edit" data-id="${row.id}">Edit</button>
                        <button type="button" class="tf-button f12-bold address-toggle" data-id="${row.id}" data-active="${row.is_active ? 1 : 0}">
                            ${row.is_active ? 'Hide' : 'Show'}
                        </button>
                        <button type="button" class="tf-button f12-bold address-delete" data-id="${row.id}" data-label="${esc(row.label)}">Delete</button>
                    </td>
                </tr>
            `);
        });
    }

    // The endpoint's DEPOSIT_NETWORKS constant is a list of slugs. Rendering
    // them raw put "erc20", "legacy" and "other" in front of an admin
    // unsorted and unlabelled. Slug stays the value; only the label changes.
    const NETWORK_LABELS = {
        bitcoin: 'Bitcoin',
        erc20: 'ERC-20 (Ethereum)',
        trc20: 'TRC-20 (Tron)',
        bep20: 'BEP-20 (BNB Chain)',
        solana: 'Solana',
        polygon: 'Polygon',
        arbitrum: 'Arbitrum',
        ripple: 'XRP Ledger',
        litecoin: 'Litecoin',
        legacy: 'Legacy (migrated)',
        other: 'Other',
    };

    function networkLabel(slug) {
        return NETWORK_LABELS[slug] || slug;
    }

    function renderNetworkOptions(selected) {
        const select = $('#address-network');
        select.empty();

        // Named chains first, alphabetically; the two housekeeping slugs last,
        // where they belong.
        const tail = ['legacy', 'other'];
        const ordered = networks.slice().sort(function (a, b) {
            const ta = tail.indexOf(a), tb = tail.indexOf(b);
            if (ta !== -1 || tb !== -1) return (ta === -1 ? -1 : ta) - (tb === -1 ? -1 : tb);
            return networkLabel(a).localeCompare(networkLabel(b));
        });

        ordered.forEach(function (n) {
            select.append($('<option>').val(n).text(networkLabel(n)));
        });
        if (selected) select.val(selected);
    }

    async function loadAddresses() {
        const body = $('#deposit-address-rows');
        body.html('<tr><td class="mvc-empty" colspan="8">Loading addresses...</td></tr>');

        try {
            const res = await fetchApi(ENDPOINT, {}, 'GET');
            if (res.status !== 'success') {
                window.showToast(res.message || 'Failed to load addresses.', 'error');
                body.html('<tr><td class="mvc-empty" colspan="8">Could not load addresses.</td></tr>');
                return;
            }
            networks = res.data.networks || [];
            renderNetworkOptions();
            renderAddressRows(res.data.addresses);
            // Without a network list the <select> is empty, so every save
            // posts network:null and comes back "Unknown network."
            $('#add-address-btn').prop('disabled', !networks.length);
        } catch (err) {
            console.error('Deposit address load error', err);
            window.showToast('Failed to load addresses.', 'error');
            body.html('<tr><td class="mvc-empty" colspan="8">Could not load addresses.</td></tr>');
            $('#add-address-btn').prop('disabled', true);
        }
    }

    function setActiveSegment(isActive) {
        const want = isActive ? '1' : '0';
        $('#address-active .mvc-segment__btn').each(function () {
            const on = String($(this).attr('data-active')) === want;
            $(this).toggleClass('is-active', on).attr('aria-pressed', String(on));
        });
    }

    // Reads the ATTRIBUTE, not .data(). The old `.data('active') === 1` worked
    // only because jQuery coerces "1" to a number, and returned undefined -> 0
    // if no button carried .is-active - silently saving the address as Hidden.
    // Default to Active when the class is somehow missing.
    function readActiveSegment() {
        const $on = $('#address-active .mvc-segment__btn.is-active');
        if (!$on.length) return 1;
        return String($on.attr('data-active')) === '0' ? 0 : 1;
    }

    // A stored memo_label outside the four options makes .val() a no-op and
    // leaves selectedIndex at -1, so the control renders EMPTY. saveAddress
    // then sends memo_label:null with a populated memo_tag and the server
    // rejects it - an error the admin cannot see the cause of.
    function setSelectValue($select, value) {
        const v = value || '';
        $select.val(v);
        if (v !== '' && ($select.val() === null || $select.val() === undefined)) {
            $select.append($('<option>').val(v).text(v)).val(v);
        }
    }

    // Drives the "N set" badge on the disclosure summary, and decides whether
    // to open it: collapsing fields that already hold data would hide exactly
    // what the admin came to change.
    function syncOptionalDisclosure(openIfSet) {
        const set = [
            ($('#address-memo').val() || '').trim(),
            ($('#address-instructions').val() || '').trim(),
            Number($('#address-min').val() || 0) > 0 ? '1' : '',
            Number($('#address-confirmations').val() || 0) > 0 ? '1' : '',
        ].filter(Boolean).length;

        $('#address-optional-count').text(set ? set + ' set' : '');
        if (openIfSet) $('#address-optional').prop('open', set > 0);
    }

    /** @param {object|null} row null opens the modal in "add" mode. */
    function openAddressModal(row) {
        const isEdit = !!row;

        $('#deposit-address-modal-title').text(isEdit ? 'Edit deposit address' : 'Add deposit address');
        $('#address-id').val(isEdit ? row.id : '');
        $('#address-asset').val(isEdit ? row.asset : '');
        renderNetworkOptions(isEdit ? row.network : (networks[0] || ''));
        $('#address-label').val(isEdit ? row.label : '');
        $('#address-value').val(isEdit ? row.address : '');
        setSelectValue($('#address-memo-label'), isEdit ? row.memo_label : '');
        $('#address-memo').val(isEdit ? (row.memo_tag || '') : '');
        // Blank rather than "0.00": an empty field reads as "no minimum",
        // which is what zero means. Both this file and the server coerce
        // empty to 0 on save.
        $('#address-min').val(isEdit && Number(row.min_amount) > 0 ? Number(row.min_amount).toFixed(2) : '');
        $('#address-confirmations').val(isEdit && row.confirmations ? row.confirmations : '');
        $('#address-instructions').val(isEdit ? (row.instructions || '') : '');
        $('#address-sort').val(isEdit && row.sort_order ? row.sort_order : '');
        setActiveSegment(isEdit ? row.is_active : true);

        syncOptionalDisclosure(true);

        // The dialog always used to reopen wherever the last one was left
        // scrolled - after editing and saving, "Add address" showed the footer
        // and no title.
        $('#deposit-address-modal .modal-body').scrollTop(0);

        window.showModal('#deposit-address-modal');

        // showModal focuses the first input, button, select or textarea - which
        // here is <input type="hidden" id="address-id">. Hidden inputs are not
        // focusable, so focus stayed on the trigger BEHIND the overlay while
        // the dialog claimed aria-modal="true". Put it on a real control.
        window.setTimeout(function () { $('#address-asset').trigger('focus'); }, 60);
    }

    async function saveAddress(e) {
        e.preventDefault();

        const id = $('#address-id').val();

        // Client-side first. The server returns every failure concatenated
        // into one auto-dismissing toast with nothing anchored to a field, so
        // catching the obvious cases here saves a guaranteed round trip on a
        // typo and points at the control that is wrong.
        const asset = ($('#address-asset').val() || '').trim().toUpperCase();
        const label = ($('#address-label').val() || '').trim();
        const address = ($('#address-value').val() || '').trim();
        const memoTag = ($('#address-memo').val() || '').trim();
        const memoLabel = $('#address-memo-label').val() || '';

        const problems = [
            [!/^[A-Z0-9]{2,12}$/.test(asset), 'Asset must be 2-12 letters or digits.', '#address-asset'],
            [!$('#address-network').val(), 'Choose a network.', '#address-network'],
            [!label, 'Display label is required.', '#address-label'],
            [!address, 'Deposit address is required.', '#address-value'],
            [/\s/.test(address), 'The address cannot contain spaces or line breaks.', '#address-value'],
            [!!memoTag && !memoLabel, 'A memo needs a label so members know what to enter.', '#address-memo-label'],
        ].filter(function (p) { return p[0]; });

        if (problems.length) {
            window.showToast(problems[0][1], 'error');
            // Open the disclosure if the offending control is inside it.
            if ($(problems[0][2]).closest('.mvc-disclosure').length) {
                $('#address-optional').prop('open', true);
            }
            $(problems[0][2]).trigger('focus');
            return;
        }

        const payload = {
            action: id ? 'edit_address' : 'add_address',
            id: id ? Number(id) : undefined,
            asset: asset,
            network: $('#address-network').val(),
            label: label,
            // Wallets are copy-pasted and routinely arrive with a trailing
            // newline; the server rejects internal whitespace, so trimming the
            // ends here turns a guaranteed error into a successful save.
            address: address,
            memo_label: memoLabel,
            memo_tag: memoTag,
            min_amount: Number($('#address-min').val() || 0),
            confirmations: Number($('#address-confirmations').val() || 0),
            instructions: ($('#address-instructions').val() || '').trim(),
            is_active: readActiveSegment(),
            sort_order: Number($('#address-sort').val() || 0),
        };

        const btn = $('#save-address-btn');
        btn.prop('disabled', true);

        try {
            const res = await fetchApi(ENDPOINT, payload);
            if (res.status !== 'success') {
                window.showToast(res.message || 'Could not save the address.', 'error');
                return;
            }
            window.showToast(res.message, 'success');
            renderAddressRows(res.data.addresses);
            window.closeModal('#deposit-address-modal');
        } catch (err) {
            console.error('Deposit address save error', err);
            window.showToast('Could not save the address.', 'error');
        } finally {
            btn.prop('disabled', false);
        }
    }

    async function toggleAddress(id, nextActive) {
        try {
            const res = await fetchApi(ENDPOINT, {
                action: 'toggle_address',
                id: Number(id),
                is_active: nextActive,
            });
            if (res.status !== 'success') {
                window.showToast(res.message || 'Could not update visibility.', 'error');
                return;
            }
            window.showToast(res.message, 'success');
            renderAddressRows(res.data.addresses);
        } catch (err) {
            console.error('Deposit address toggle error', err);
            window.showToast('Could not update visibility.', 'error');
        }
    }

    async function deleteAddress() {
        if (!pendingDeleteId) return;

        try {
            const res = await fetchApi(ENDPOINT, { action: 'delete_address', id: Number(pendingDeleteId) });
            if (res.status !== 'success') {
                window.showToast(res.message || 'Could not delete the address.', 'error');
                return;
            }
            window.showToast(res.message, 'success');
            renderAddressRows(res.data.addresses);
            window.closeModal('#delete-address-modal');
        } catch (err) {
            console.error('Deposit address delete error', err);
            window.showToast('Could not delete the address.', 'error');
        } finally {
            pendingDeleteId = null;
        }
    }

    function bindEvents() {
        $('#add-address-btn').on('click', function () { openAddressModal(null); });

        // Delegated: the rows are re-rendered on every mutation.
        $('#deposit-address-rows').on('click', '.address-edit', async function () {
            const id = $(this).data('id');
            try {
                const res = await fetchApi(`${ENDPOINT}?fetch=address_details&id=${id}`, {}, 'GET');
                if (res.status !== 'success') {
                    window.showToast(res.message || 'Could not load that address.', 'error');
                    return;
                }
                openAddressModal(res.data);
            } catch (err) {
                console.error('Deposit address detail error', err);
                window.showToast('Could not load that address.', 'error');
            }
        });

        $('#deposit-address-rows').on('click', '.address-toggle', function () {
            toggleAddress($(this).data('id'), $(this).data('active') === 1 ? 0 : 1);
        });

        $('#deposit-address-rows').on('click', '.address-delete', function () {
            pendingDeleteId = $(this).data('id');
            $('#delete-address-label').text($(this).data('label') || 'this address');
            window.showModal('#delete-address-modal');
        });

        $('#confirm-delete-address').on('click', deleteAddress);
        $('#deposit-address-form').on('submit', saveAddress);

        $('#address-active').on('click', '.mvc-segment__btn', function () {
            setActiveSegment(String($(this).attr('data-active')) !== '0');
        });

        // Keep the "N set" badge honest as the admin types.
        $('#address-memo, #address-instructions, #address-min, #address-confirmations')
            .on('input', function () { syncOptionalDisclosure(false); });

        // Asset codes are uppercase everywhere else in the UI; normalising on
        // blur keeps UNIQUE (asset, network) from treating usdt and USDT as
        // two different chains.
        $('#address-asset').on('blur', function () {
            $(this).val(($(this).val() || '').trim().toUpperCase());
        });
    }

    $(function () {
        if (!$('#deposit-address-rows').length) return;
        bindEvents();
        loadAddresses();
    });
})(jQuery);
