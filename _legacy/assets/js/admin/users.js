/**
 * ============================================================
 * Maveren Capital Admin Users.js
 * Purpose: Frontend logic for the Admin Users management page.
 * Handles: Data fetching, table rendering, pagination, search/filter, and user actions (Edit/Delete/Email).
 * ============================================================
 */
;(function ($) {
    // Fix Bootstrap dropdown blocking links
$(document).on('click', '.dropdown-menu .dropdown-item', function (e) {
    e.preventDefault();
    e.stopPropagation(); 
});


    "use strict";

    // Global state for pagination and filtering
    let currentPage = 1;
    let currentFilter = 'all';
    let currentSearch = '';
    const itemsPerPage = 10; // Must match the backend API's assumption

    // --- Core Data Fetcher & UI Renderer ---
    async function loadUsers(page = 1, filter = 'all', search = '') {
        const tableBody = $('#users-table-body');
        const paginationEl = $('#pagination');
        tableBody.empty().html('<tr><td class="mvc-empty" colspan="6">Loading users...</td></tr>');
        paginationEl.empty();

        currentPage = page;
        currentFilter = filter;
        currentSearch = search;
        
        // Show loader/disable controls if needed (optional)
        // ...

        try {
            const res = await fetchApi('/api/admin/users.php', {
                page: page,
                filter: filter,
                search: search,
                per_page: itemsPerPage // Pass this for clarity, even if backend defaults
            }, "GET");

            if (res.status !== 'success') {
                showToast(res.message || 'Failed to load user list.', 'error');
                tableBody.html('<tr><td class="mvc-empty" colspan="6">Error loading data.</td></tr>');
                return;
            }

            const data = res.data;
            updateMetrics(data.metrics);
            renderUsersTable(data.users);
            renderPagination(data.current_page, data.total_pages);

        } catch (error) {
            console.error('API Error loading users:', error);
            showToast('A network error occurred while fetching user data.', 'error');
            tableBody.html('<tr><td class="mvc-empty" colspan="6">Network error. Check console.</td></tr>');
        }
    }

    // --- Metric Update ---
    function updateMetrics(m) {
        if (!m) return;
        $('#total-users').text(m.total_users ?? 0);
        $('#active-users').text(m.active_users ?? 0);
        $('#admin-count').text(m.admin_count ?? 0);
        $('#new-today').text(m.new_today ?? 0);
        // Recount animation if available (assuming countto.js is loaded later)
        if (typeof counter === 'function') {
            counter();
        }
    }

    // --- Table Renderer ---
    function renderUsersTable(users) {
        const tableBody = $('#users-table-body');
        tableBody.empty();

        if (!users || users.length === 0) {
            tableBody.html('<tr><td class="mvc-empty" colspan="6">No users found matching current criteria.</td></tr>');
            return;
        }

        const esc = window.mvcEsc;

        users.forEach(user => {

            // Admin was `bg-Primary text-White` and so was "user" - the two
            // branches were byte-identical, so the badge carried no
            // information. Members get a neutral chip now.
            const roleBadgeClass = user.role === 'admin' ? 'bg-Primary' : 'bg-Neutral';
            const statusBadgeClass = user.status === 'active' ? 'bg-Green' : 'bg-Salmon';

            /* Inline buttons, not a Bootstrap dropdown.
               .mvc-scroll-table is `overflow-x: auto`, which establishes a
               scroll container - an absolutely positioned .dropdown-menu
               inside it is clipped on the vertical axis, so the Actions menu
               was unreachable for the last rows. mvc-dashboard.css:2102 lays
               inline .tf-buttons out as a neat row and every already-converted
               table (deposit addresses, pending deposits) uses that shape. */
            const actions = `
                <button type="button" class="tf-button f12-bold action-edit"
                    data-id="${user.id}"
                    data-name="${esc(user.display_name)}"
                    data-email="${esc(user.email)}"
                    data-role="${esc(user.role)}"
                    data-status="${esc(user.status)}">Edit</button>
                <button type="button" class="tf-button f12-bold bg-Accent text-Black action-email"
                    data-id="${user.id}"
                    data-email="${esc(user.email)}"
                    data-name="${esc(user.display_name)}">Email</button>
                <button type="button" class="tf-button f12-bold bg-Red text-White action-delete"
                    data-id="${user.id}"
                    data-name="${esc(user.display_name)}">Delete</button>
            `;

            const row = `
                <tr data-id="${user.id}">
                    <td>${esc(user.display_name)}<div class="f12-regular text-Gray">ID ${user.id}</div></td>
                    <td class="mvc-td-muted">${esc(user.email)}</td>
                    <td><div class="box-status ${roleBadgeClass}"><span class="font-poppins">${esc(String(user.role || '').toUpperCase())}</span></div></td>
                    <td><div class="box-status ${statusBadgeClass}"><span class="font-poppins">${esc(String(user.status || '').toUpperCase())}</span></div></td>
                    <td class="mvc-td-muted">${esc(user.last_login)}</td>
                    <td>${actions}</td>
                </tr>
            `;
            tableBody.append(row);
        });
    }

    // --- Pagination Renderer ---
    /**
     * Shared renderer (assets/js/mvc-pagination.js). This was one of three
     * byte-identical copies emitting `.page-link`, a Bootstrap class with no
     * matching rule in either stylesheet, plus a `disabled` class that had no
     * CSS and never set the attribute.
     */
    function renderPagination(currentPage, totalPages) {
        window.mvcRenderPagination('#pagination', {
            page: currentPage,
            pages: totalPages,
            onPage: function (n) {
                loadUsers(n, currentFilter, currentSearch);
            },
        });
    }

    // --- Action Handlers ---

    // 1. Edit User Modal & Form
    function bindEditUser() {
        $(document).on('click', '.action-edit', function(e) {
            e.preventDefault();
            e.stopPropagation(); // FIX: Stop event propagation to prevent Bootstrap dropdown from blocking modal
            
            const id = $(this).data('id');
            const name = $(this).data('name');
            const email = $(this).data('email');
            const role = $(this).data('role');
            const status = $(this).data('status');

            $('#edit-user-id').val(id);
            $('#edit-name').val(name);
            $('#edit-email').val(email);
            $('#edit-role').val(role);
            
            // Map 'disabled' to 'suspended' for the modal dropdown (as used in PHP logic)
            $('#edit-status').val(status === 'disabled' ? 'suspended' : status);
            
            showModal('#edit-user-modal');
        });

        $('#edit-user-form').on('submit', async function(e) {
            e.preventDefault();

            const userId = $('#edit-user-id').val();
            const name = $('#edit-name').val();
            const email = $('#edit-email').val();
            const role = $('#edit-role').val();

            // Convert UI 'suspended' to backend 'disabled'
            let status = $('#edit-status').val() === 'suspended' ? 'disabled' : $('#edit-status').val();

            if (!userId || !name || !role || !status) {
                showToast('Missing required fields for update.', 'error');
                return;
            }

            showToast(`Updating user ID ${userId}...`, 'info', 5000);

            try {
                const res = await fetchApi('/api/admin/users.php', {
                    action: 'edit_user',
                    user_id: userId,
                    name: name,
                    email: email,
                    role: role,
                    status: status
                }, "POST");

                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    closeModal('#edit-user-modal');
                    await loadUsers(currentPage, currentFilter, currentSearch); 
                } else {
                    showToast(res.message || 'Update failed.', 'error');
                }
            } catch (error) {
                console.error('Edit user error:', error);
                showToast('A network error occurred or the server failed to respond.', 'error');
            }
        });

    }

    // 2. Send Email Modal & Form
    function bindSendEmail() {
        $(document).on('click', '.action-email', function(e) {
            e.preventDefault();
            e.stopPropagation(); // FIX: Stop event propagation to prevent Bootstrap dropdown from blocking modal
            
            const id = $(this).data('id');
            const email = $(this).data('email');
            const name = $(this).data('name');

            $('#email-user-id').val(id);
            // Stated text now, not a disabled input.
            $('#email-to').text(`${name} <${email}>`);
            $('#send-email-modal h2').text(`Send Email to ${name}`);

            showModal('#send-email-modal');
        });

        $('#send-email-form').on('submit', async function(e) {
            e.preventDefault();

            const userId = $('#email-user-id').val();
            const subject = $('#email-subject').val();
            const body = $('#email-body').val();

            if (!userId || !subject || !body) {
                showToast('Email subject and body are required.', 'error');
                return;
            }

            showToast(`Queuing email for user ID ${userId}...`, 'info', 5000);

            try {
                // Note: We use the 'users.php' API for specific user emails from the table.
                const res = await fetchApi('/api/admin/users.php', {
                    action: 'send_email',
                    user_id: userId,
                    subject: subject,
                    body: body
                }, "POST");

                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    closeModal('#send-email-modal');
                    $('#send-email-form')[0].reset(); // Reset form content
                } else {
                    showToast(res.message || 'Email sending failed.', 'error');
                }
            } catch (error) {
                console.error('Send email error:', error);
                showToast('A network error occurred or the server failed to respond.', 'error');
            }
        });
    }

    // 3. Delete User Modal & Action
    function bindDeleteUser() {
        let userToDeleteId = null;

        $(document).on('click', '.action-delete', function(e) {
            e.preventDefault();
            e.stopPropagation(); // FIX: Stop event propagation to prevent Bootstrap dropdown from blocking modal
            
            const id = $(this).data('id');
            const name = $(this).data('name');
            
            userToDeleteId = id;

            $('#delete-user-name').text(`${name} (ID: ${id})`);
            showModal('#delete-user-modal');
        });

        $('#confirm-delete').on('click', async function() {
            if (!userToDeleteId) {
                showToast('No user selected for deletion.', 'error');
                return;
            }

            // Disable button and show progress
            const originalText = $(this).text();
            $(this).text('Deleting...').prop('disabled', true);
            showToast(`Deleting user...`, 'warning');

            try {
                const res = await fetchApi('/api/admin/users.php', {
                    action: 'delete_user',
                    user_id: userToDeleteId
                }, "POST");

                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    closeModal('#delete-user-modal');
                    // Refresh current page. If the page is now empty, go back one page.
                    await loadUsers(currentPage, currentFilter, currentSearch); 
                } else {
                    showToast(res.message || 'Deletion failed.', 'error');
                }
            } catch (error) {
                console.error('Delete user error:', error);
                showToast('A network error occurred or the server failed to respond.', 'error');
            } finally {
                $(this).text(originalText).prop('disabled', false);
                userToDeleteId = null;
            }
        });
    }
    
    // 4. Search and Filter Handlers
    function bindSearchAndFilter() {
        // Search form submission
        $('.form-search').on('submit', function(e) {
            e.preventDefault();
            const searchVal = $('#user-search').val().trim();
            loadUsers(1, currentFilter, searchVal);
        });

        // Filter dropdown click
        $('.dropdown-menu a[data-filter]').on('click', function(e) {
            e.preventDefault();
            const filterVal = $(this).data('filter');
            // Update the button text to show current filter
            $(this).closest('.dropdown').find('button').html(`<i class="ph ph-funnel"></i> ${$(this).text()}`);
            loadUsers(1, filterVal, currentSearch);
        });
    }

    // --- Initialization ---
    $(function () {
        // Ensure utility functions from admin.js (like showModal, closeModal, showToast) are available.
        // Assuming the admin.js script loads first, these functions should be available globally/via closure.
        
        // Bind all interactive elements
        bindEditUser();
        bindSendEmail();
        bindDeleteUser();
        bindSearchAndFilter();

        // Initial load of the user list
        loadUsers(1); 
    });

})(jQuery);