/* =======================================================
   announcements.js - Maveren Capital Admin Announcements
   ======================================================= */

(function ($) {
    const ENDPOINT = '/api/admin/announcements.php';

    $(function () {
        loadList();
        bindInteractions();
    });

    async function loadList() {
        const body = $('#announcements-body');
        body.html('<tr><td class="mvc-empty" colspan="5">Loading announcements...</td></tr>');

        const res = await fetchApi(ENDPOINT, { action: 'get_list' });
        if (res.status !== 'success') {
            body.html('<tr><td class="mvc-empty" colspan="5">Error loading announcements.</td></tr>');
            return;
        }
        const list = res.data?.announcements || [];
        if (!list.length) {
            body.html('<tr><td class="mvc-empty" colspan="5">No announcements yet. Click "New Announcement" to publish one.</td></tr>');
            return;
        }
        body.empty();
        list.forEach(a => {
            const statusBadge = a.status === 'published'
                ? '<div class="box-status bg-Green"><span>published</span></div>'
                : '<div class="box-status bg-Neutral"><span>draft</span></div>';
            const created = a.created_at ? new Date(a.created_at).toLocaleString() : '—';
            body.append(`
                <tr data-announcement-id="${a.id}">
                    <td><span class="f14-bold">${escapeHtml(a.title)}</span><div class="f12-regular text-Gray">${escapeHtml((a.body || '').slice(0, 100))}...</div></td>
                    <td class="mvc-td-muted">${escapeHtml(a.category || 'general')}</td>
                    <td>${statusBadge}</td>
                    <td class="mvc-td-muted">${escapeHtml(created)}</td>
                    <td>
                        <button type="button" class="tf-button f12-bold action-edit-announcement" data-id="${a.id}">Edit</button>
                        <button type="button" class="tf-button bg-Red text-White f12-bold action-delete-announcement" data-id="${a.id}">Delete</button>
                    </td>
                </tr>`);
        });
    }

    function openModal(a) {
        const isEdit = !!a;
        $('#announcement-title').text(isEdit ? `Edit: ${a.title}` : 'New Announcement');
        $('#announcement-id').val(isEdit ? a.id : '');
        $('#announcement-title-input').val(isEdit ? a.title : '');
        $('#announcement-body').val(isEdit ? a.body : '');
        $('#announcement-category').val(isEdit ? (a.category || 'general') : 'general');
        $('#announcement-status').val(isEdit ? a.status : 'published');
        if (window.showModal) window.showModal('#announcement-modal');
        else $('#announcement-modal').addClass('show').show();
    }

    function closeModal() {
        if (window.closeModal) window.closeModal('#announcement-modal');
        else $('#announcement-modal').removeClass('show').hide();
    }

    function bindInteractions() {
        $('#add-announcement-btn').on('click', () => openModal(null));
        $('.button-close-modal').on('click', closeModal);

        $(document).on('click', '.action-edit-announcement', async function () {
            const id = $(this).data('id');
            const res = await fetchApi(ENDPOINT, { action: 'get_list' });
            if (res.status !== 'success') return;
            const a = (res.data?.announcements || []).find(x => parseInt(x.id) === parseInt(id));
            if (a) openModal(a);
        });

        $(document).on('click', '.action-delete-announcement', async function () {
            const id = $(this).data('id');
            const ok = await mvcConfirm({
                title: 'Delete this announcement?',
                body: 'It disappears from every member dashboard straight away. This cannot be undone.',
                confirmLabel: 'Delete',
                danger: true,
            });
            if (!ok) return;
            const res = await fetchApi(ENDPOINT, { action: 'delete', id });
            if (res.status === 'success') {
                showToast('Announcement deleted.', 'success');
                loadList();
            } else {
                showToast(res.message || 'Failed to delete.', 'error');
            }
        });

        $('#announcement-form').on('submit', async function (e) {
            e.preventDefault();
            const id = $('#announcement-id').val();
            const isEdit = !!id;
            const payload = {
                action: isEdit ? 'edit' : 'add',
                id: id,
                title: $('#announcement-title-input').val(),
                body: $('#announcement-body').val(),
                category: $('#announcement-category').val(),
                status: $('#announcement-status').val()
            };
            const res = await fetchApi(ENDPOINT, payload);
            if (res.status === 'success') {
                showToast(isEdit ? 'Announcement updated.' : 'Announcement published.', 'success');
                closeModal();
                loadList();
            } else {
                showToast(res.message || 'Failed to save announcement.', 'error');
            }
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"'`=\/]/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
            "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
        })[s]);
    }
})(jQuery);
