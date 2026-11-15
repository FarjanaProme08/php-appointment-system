/**
 * Admin Panel Client-Side Application
 * Handles appointments table display, filtering, search, and the Edit Appointment modal.
 */

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('appointmentsTableBody');
    const searchInput = document.getElementById('filterSearch');
    const dateFilterInput = document.getElementById('filterDate');
    const mechanicFilterSelect = document.getElementById('filterMechanic');
    const resetFilterBtn = document.getElementById('resetFiltersBtn');

    // Stat card counters
    const statTotal = document.getElementById('statTotal');
    const statToday = document.getElementById('statToday');
    const statUpcoming = document.getElementById('statUpcoming');
    const statCompleted = document.getElementById('statCompleted');

    // Edit Modal Elements
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editAppointmentForm');
    const modalCloseBtns = document.querySelectorAll('.close-modal-trigger');
    const modalClientName = document.getElementById('modalClientName');
    const modalClientPhone = document.getElementById('modalClientPhone');
    const modalCarLicense = document.getElementById('modalCarLicense');
    const modalCarEngine = document.getElementById('modalCarEngine');
    const modalAppointmentId = document.getElementById('modalAppointmentId');
    const modalDateInput = document.getElementById('modalAppointmentDate');
    const modalMechanicSelect = document.getElementById('modalMechanicSelect');
    const modalStatusSelect = document.getElementById('modalStatusSelect');
    const modalNotes = document.getElementById('modalNotes');
    const saveEditBtn = document.getElementById('saveEditBtn');
    const modalAlertBox = document.getElementById('modalAlertBox');

    // Toast Container
    const toastContainer = document.getElementById('toastContainer');

    // Load initial data
    loadAppointments();
    loadMechanicsFilterOptions();

    // Event Listeners for Filters
    let debounceTimer = null;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadAppointments(), 300);
    });

    dateFilterInput.addEventListener('change', () => loadAppointments());
    mechanicFilterSelect.addEventListener('change', () => loadAppointments());

    resetFilterBtn.addEventListener('click', () => {
        searchInput.value = '';
        dateFilterInput.value = '';
        mechanicFilterSelect.value = '';
        loadAppointments();
    });

    /**
     * Fetch and render appointments from API
     */
    async function loadAppointments() {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4" style="padding: 40px 0;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:10px;color:var(--gray-500);">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                            <path d="M12 2a10 10 0 0 1 10 10"></path>
                        </svg>
                        <span>Loading appointment records...</span>
                    </div>
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        if (searchInput.value.trim()) params.append('search', searchInput.value.trim());
        if (dateFilterInput.value.trim()) params.append('date', dateFilterInput.value.trim());
        if (mechanicFilterSelect.value.trim()) params.append('mechanic_id', mechanicFilterSelect.value.trim());

        try {
            const res = await fetch(`api/get_appointments.php?${params.toString()}`);
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Error loading appointments');
            }

            // Update KPI Stats
            if (data.stats) {
                if (statTotal) statTotal.textContent = data.stats.total;
                if (statToday) statToday.textContent = data.stats.today;
                if (statUpcoming) statUpcoming.textContent = data.stats.upcoming;
                if (statCompleted) statCompleted.textContent = data.stats.completed;
            }

            renderTableRows(data.appointments);

        } catch (error) {
            console.error('Failed to load appointments:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="alert alert-danger" style="margin: 20px auto; max-width: 500px;">
                            ${escapeHtml(error.message)}
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Render Table Rows
     * Displays: Client Name, Phone, Car License (Registration) No, Appointment Date, Mechanic Name.
     */
    function renderTableRows(appointments) {
        if (!appointments || appointments.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="8" y1="12" x2="16" y2="12"></line>
                            </svg>
                            <h3>No appointments found</h3>
                            <p class="text-muted text-sm">Try adjusting your filters or search criteria.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        appointments.forEach(app => {
            const statusClass = app.status || 'confirmed';

            html += `
                <tr data-id="${app.id}">
                    <td>
                        <strong style="color:var(--gray-900); font-weight:600;">${escapeHtml(app.client_name)}</strong>
                        <div class="text-xs text-muted" style="margin-top:2px;">Booking ID: #${app.id}</div>
                    </td>
                    <td>
                        <a href="tel:${escapeHtml(app.client_phone)}" class="phone-link">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <span>${escapeHtml(app.client_phone)}</span>
                        </a>
                    </td>
                    <td>
                        <span class="license-pill">${escapeHtml(app.car_license_no)}</span>
                    </td>
                    <td>
                        <strong>${formatDisplayDate(app.appointment_date)}</strong>
                    </td>
                    <td>
                        <div>
                            <strong style="color:var(--primary);">${escapeHtml(app.mechanic_name)}</strong>
                            <div class="text-xs text-muted">${escapeHtml(app.mechanic_specialty || '')}</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-status ${statusClass}">${escapeHtml(app.status)}</span>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm edit-btn" data-id="${app.id}" title="Edit Appointment Date and Mechanic">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                </svg>
                                <span>Edit</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm delete-btn" data-id="${app.id}" title="Cancel / Remove" style="color:var(--danger); border-color:var(--danger-border);">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;

        // Attach action handlers
        tableBody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => openEditModal(btn.dataset.id));
        });

        tableBody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => confirmDeleteAppointment(btn.dataset.id));
        });
    }

    /**
     * Populate filter dropdown with 5 mechanics
     */
    async function loadMechanicsFilterOptions() {
        try {
            const res = await fetch('api/get_mechanics_availability.php');
            const data = await res.json();
            if (data.success && data.mechanics) {
                data.mechanics.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    mechanicFilterSelect.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Failed to populate mechanic filter:', e);
        }
    }

    /**
     * Open Edit Modal & Populate details
     */
    async function openEditModal(appointmentId) {
        clearModalAlert();
        editForm.reset();
        modalAppointmentId.value = appointmentId;

        // Show Modal with loading state
        editModal.classList.add('show');

        try {
            const res = await fetch(`api/get_appointment.php?id=${appointmentId}`);
            const data = await res.json();

            if (!data.success || !data.appointment) {
                throw new Error(data.message || 'Appointment not found.');
            }

            const app = data.appointment;

            // Fill read-only client context
            modalClientName.textContent = app.client_name;
            modalClientPhone.textContent = app.client_phone;
            modalCarLicense.textContent = app.car_license_no;
            modalCarEngine.textContent = app.car_engine_no;

            // Fill editable fields
            modalDateInput.value = app.appointment_date;
            modalStatusSelect.value = app.status;
            modalNotes.value = app.notes || '';

            // Dynamically load available mechanics for this date, excluding this appointment ID
            await loadModalMechanicsAvailability(app.appointment_date, app.mechanic_id, appointmentId);

            // Listen for date change in modal to re-evaluate slots dynamically
            modalDateInput.onchange = () => {
                if (modalDateInput.value) {
                    loadModalMechanicsAvailability(modalDateInput.value, parseInt(modalMechanicSelect.value, 10), appointmentId);
                }
            };

        } catch (err) {
            console.error('Error fetching appointment:', err);
            showModalAlert(err.message, 'danger');
        }
    }

    /**
     * Dynamically fetch & populate mechanic slots inside Edit Modal
     */
    async function loadModalMechanicsAvailability(dateStr, selectedMechanicId, excludeAppId) {
        modalMechanicSelect.innerHTML = `<option value="">Checking slot capacity...</option>`;
        modalMechanicSelect.disabled = true;

        try {
            const url = `api/get_mechanics_availability.php?date=${encodeURIComponent(dateStr)}&exclude_appointment_id=${excludeAppId}`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success || !data.mechanics) {
                throw new Error(data.message || 'Failed to fetch mechanic slots');
            }

            modalMechanicSelect.innerHTML = `<option value="">-- Select Available Mechanic --</option>`;

            data.mechanics.forEach(mech => {
                const opt = document.createElement('option');
                opt.value = mech.id;

                const isCurrent = (mech.id === selectedMechanicId);
                const isFull = (mech.available_slots <= 0);

                if (isFull) {
                    opt.textContent = `${mech.name} — FULL (0 slots available)`;
                    opt.disabled = true;
                } else {
                    opt.textContent = `${mech.name} — (${mech.available_slots} / ${mech.max_daily_slots} slots available)`;
                }

                if (isCurrent && !isFull) {
                    opt.selected = true;
                }

                modalMechanicSelect.appendChild(opt);
            });

            // If selected mechanic is full, select default empty
            if (selectedMechanicId && !data.mechanics.find(m => m.id === selectedMechanicId && m.available_slots > 0)) {
                modalMechanicSelect.value = '';
            } else if (selectedMechanicId) {
                modalMechanicSelect.value = selectedMechanicId;
            }

            modalMechanicSelect.disabled = false;

        } catch (e) {
            console.error(e);
            modalMechanicSelect.innerHTML = `<option value="">Error loading mechanics</option>`;
            showModalAlert('Could not calculate mechanic slots: ' + e.message, 'danger');
        }
    }

    /**
     * Save Edited Appointment
     */
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearModalAlert();

        const appointmentId = parseInt(modalAppointmentId.value, 10);
        const newDate = modalDateInput.value.trim();
        const newMechanicId = parseInt(modalMechanicSelect.value, 10);
        const newStatus = modalStatusSelect.value;
        const newNotes = modalNotes.value.trim();

        if (!newDate) {
            showModalAlert('Please select an appointment date.', 'danger');
            return;
        }

        if (!newMechanicId || isNaN(newMechanicId)) {
            showModalAlert('Please select an available mechanic.', 'danger');
            return;
        }

        // Set Loading state
        saveEditBtn.disabled = true;
        const origBtnText = saveEditBtn.innerHTML;
        saveEditBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                <path d="M12 2a10 10 0 0 1 10 10"></path>
            </svg>
            <span>Saving Changes...</span>
        `;

        try {
            const payload = {
                id: appointmentId,
                appointment_date: newDate,
                mechanic_id: newMechanicId,
                status: newStatus,
                notes: newNotes
            };

            const response = await fetch('api/update_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to update appointment.');
            }

            // Success
            closeModal();
            showToast(result.message, 'success', 'Appointment Updated');
            loadAppointments();

        } catch (error) {
            console.error('Update error:', error);
            showModalAlert(error.message, 'danger');
        } finally {
            saveEditBtn.disabled = false;
            saveEditBtn.innerHTML = origBtnText;
        }
    });

    /**
     * Delete Appointment Handler
     */
    async function confirmDeleteAppointment(id) {
        if (!confirm(`Are you sure you want to cancel and remove Appointment #${id}? This action cannot be undone.`)) {
            return;
        }

        try {
            const res = await fetch('api/delete_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10) })
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete appointment');
            }

            showToast(data.message, 'success', 'Appointment Removed');
            loadAppointments();

        } catch (err) {
            console.error('Delete error:', err);
            showToast(err.message, 'danger', 'Action Failed');
        }
    }

    /**
     * Modal Helper Functions
     */
    function closeModal() {
        editModal.classList.remove('show');
    }

    modalCloseBtns.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            closeModal();
        }
    });

    function showModalAlert(msg, type = 'danger') {
        modalAlertBox.innerHTML = `
            <div class="alert alert-${type}" style="margin-bottom: 16px;">
                ${escapeHtml(msg)}
            </div>
        `;
    }

    function clearModalAlert() {
        modalAlertBox.innerHTML = '';
    }

    /**
     * Format date nicely for display
     */
    function formatDisplayDate(dateStr) {
        if (!dateStr) return '';
        try {
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                return d.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
            return dateStr;
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Toast Notifications
     */
    function showToast(message, type = 'info', title = '') {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                ${title ? `<h5>${escapeHtml(title)}</h5>` : ''}
                <p>${escapeHtml(message)}</p>
            </div>
            <button class="toast-close" type="button" aria-label="Close">&times;</button>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });

        toastContainer.appendChild(toast);

        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.transition = 'opacity 0.3s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
