/**
 * Booking Portal Client-Side Application
 * Handles dynamic mechanic slot fetching, client-side validation, and AJAX submission.
 */

document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.getElementById('bookingForm');
    const dateInput = document.getElementById('appointmentDate');
    const mechanicsContainer = document.getElementById('mechanicsList');
    const mechanicIdHidden = document.getElementById('selectedMechanicId');
    const submitBtn = document.getElementById('submitBookingBtn');
    const confirmationBox = document.getElementById('bookingConfirmation');
    const toastContainer = document.getElementById('toastContainer');

    // Restrict date picker: Minimum date is today
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const todayFormatted = `${yyyy}-${mm}-${dd}`;
    
    if (dateInput) {
        dateInput.min = todayFormatted;
        if (!dateInput.value) {
            dateInput.value = todayFormatted;
        }
        // Initial fetch of mechanics for today's date
        fetchMechanicsAvailability(dateInput.value);

        // Listen for date change to dynamically update available slots
        dateInput.addEventListener('change', () => {
            clearFieldError(dateInput);
            if (dateInput.value) {
                fetchMechanicsAvailability(dateInput.value);
            }
        });
    }

    // Live Numeric Input Filtering & Helpers
    const phoneInput = document.getElementById('clientPhone');
    const engineInput = document.getElementById('carEngineNo');

    [phoneInput, engineInput].forEach(input => {
        if (!input) return;
        input.addEventListener('input', (e) => {
            // Replace any non-digit character immediately
            const cleaned = e.target.value.replace(/\D/g, '');
            if (e.target.value !== cleaned) {
                e.target.value = cleaned;
            }
            clearFieldError(e.target);
        });
    });

    // Clear validation error on field input/change
    const formInputs = bookingForm.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', () => clearFieldError(input));
        input.addEventListener('change', () => clearFieldError(input));
    });

    /**
     * Fetch mechanics and their available slots from API
     */
    async function fetchMechanicsAvailability(dateStr) {
        if (!mechanicsContainer) return;

        mechanicsContainer.innerHTML = `
            <div class="text-center text-muted py-4" style="padding: 24px 0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin-icon" style="animation: spin 1s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10"></path>
                </svg>
                <p class="mt-2 text-sm" style="margin-top:8px;">Checking real-time mechanic availability for ${dateStr}...</p>
            </div>
        `;

        try {
            const response = await fetch(`api/get_mechanics_availability.php?date=${encodeURIComponent(dateStr)}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Unable to retrieve mechanics.');
            }

            renderMechanics(data.mechanics);

        } catch (error) {
            console.error('Fetch mechanics error:', error);
            mechanicsContainer.innerHTML = `
                <div class="alert alert-danger" style="margin-bottom:0;">
                    <p>Failed to load mechanic availability: ${error.message}</p>
                    <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }

    /**
     * Render the 5 mechanics and their available slots
     */
    function renderMechanics(mechanics) {
        if (!mechanics || mechanics.length === 0) {
            mechanicsContainer.innerHTML = `<p class="text-muted text-sm">No active mechanics found.</p>`;
            return;
        }

        const currentlySelectedId = parseInt(mechanicIdHidden.value, 10);
        let selectedStillValid = false;

        let html = '<div class="mechanics-grid">';

        mechanics.forEach(mech => {
            const isFull = mech.available_slots <= 0;
            const isSelected = (mech.id === currentlySelectedId) && !isFull;
            if (isSelected) selectedStillValid = true;

            // Slot badge styling
            let badgeClass = 'badge-available';
            let badgeText = `${mech.available_slots} / ${mech.max_daily_slots} slots available`;

            if (isFull) {
                badgeClass = 'badge-full';
                badgeText = 'Fully Booked (0 slots)';
            } else if (mech.available_slots === 1) {
                badgeClass = 'badge-warning';
                badgeText = 'Only 1 slot left!';
            }

            const initials = mech.name.split(' ').map(n => n[0]).join('').substring(0, 2);

            html += `
                <div class="mechanic-card ${isSelected ? 'selected' : ''} ${isFull ? 'disabled' : ''}" 
                     data-id="${mech.id}" 
                     data-available="${mech.available_slots}"
                     role="button"
                     tabindex="${isFull ? '-1' : '0'}"
                     aria-disabled="${isFull}">
                    <input type="radio" name="mechanic_radio" value="${mech.id}" ${isSelected ? 'checked' : ''} ${isFull ? 'disabled' : ''}>
                    <div class="mechanic-info">
                        <div class="mechanic-avatar">${initials}</div>
                        <div class="mechanic-meta">
                            <h4>${escapeHtml(mech.name)}</h4>
                            <p>${escapeHtml(mech.specialty)}</p>
                        </div>
                    </div>
                    <div class="slot-badge ${badgeClass}">
                        ${badgeText}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        mechanicsContainer.innerHTML = html;

        // If the previously selected mechanic is now full, reset selection
        if (!selectedStillValid && currentlySelectedId) {
            mechanicIdHidden.value = '';
        }

        // Add click events to mechanic cards
        const cards = mechanicsContainer.querySelectorAll('.mechanic-card:not(.disabled)');
        cards.forEach(card => {
            card.addEventListener('click', () => {
                selectMechanic(card.dataset.id);
            });
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectMechanic(card.dataset.id);
                }
            });
        });
    }

    /**
     * Handle Mechanic Selection
     */
    function selectMechanic(id) {
        mechanicIdHidden.value = id;
        clearFieldError(mechanicIdHidden);

        const allCards = mechanicsContainer.querySelectorAll('.mechanic-card');
        allCards.forEach(c => {
            if (c.dataset.id === String(id)) {
                c.classList.add('selected');
                const radio = c.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            } else {
                c.classList.remove('selected');
                const radio = c.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            }
        });
    }

    /**
     * Client-Side Validation
     */
    function validateForm() {
        let isValid = true;
        let firstInvalidField = null;

        // 1. Full Name
        const nameInput = document.getElementById('clientName');
        const nameVal = nameInput.value.trim();
        if (!nameVal) {
            showFieldError(nameInput, 'Please enter your full name.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = nameInput;
        } else if (nameVal.length < 2) {
            showFieldError(nameInput, 'Full name must be at least 2 characters.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = nameInput;
        }

        // 2. Address
        const addressInput = document.getElementById('clientAddress');
        if (!addressInput.value.trim()) {
            showFieldError(addressInput, 'Please enter your address.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = addressInput;
        }

        // 3. Phone (Must be numbers only)
        const phoneVal = phoneInput.value.trim();
        if (!phoneVal) {
            showFieldError(phoneInput, 'Please enter your phone number.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = phoneInput;
        } else if (!/^\d+$/.test(phoneVal)) {
            showFieldError(phoneInput, 'Phone number must contain numbers only.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = phoneInput;
        } else if (phoneVal.length < 7 || phoneVal.length > 15) {
            showFieldError(phoneInput, 'Phone number must be between 7 and 15 digits.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = phoneInput;
        }

        // 4. Car License No
        const licenseInput = document.getElementById('carLicenseNo');
        if (!licenseInput.value.trim()) {
            showFieldError(licenseInput, 'Please enter your car license (registration) number.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = licenseInput;
        }

        // 5. Car Engine No (Must be numbers only)
        const engineVal = engineInput.value.trim();
        if (!engineVal) {
            showFieldError(engineInput, 'Please enter your car engine number.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = engineInput;
        } else if (!/^\d+$/.test(engineVal)) {
            showFieldError(engineInput, 'Car engine number must contain numbers only.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = engineInput;
        } else if (engineVal.length < 4 || engineVal.length > 50) {
            showFieldError(engineInput, 'Engine number must be between 4 and 50 digits.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = engineInput;
        }

        // 6. Appointment Date
        const dateVal = dateInput.value.trim();
        if (!dateVal) {
            showFieldError(dateInput, 'Please select an appointment date.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = dateInput;
        } else {
            const chosenDate = new Date(dateVal + 'T00:00:00');
            const minDate = new Date(todayFormatted + 'T00:00:00');
            if (isNaN(chosenDate.getTime())) {
                showFieldError(dateInput, 'Invalid appointment date format.');
                isValid = false;
                if (!firstInvalidField) firstInvalidField = dateInput;
            } else if (chosenDate < minDate) {
                showFieldError(dateInput, 'Appointment date cannot be in the past.');
                isValid = false;
                if (!firstInvalidField) firstInvalidField = dateInput;
            }
        }

        // 7. Mechanic Selection
        const selectedId = parseInt(mechanicIdHidden.value, 10);
        if (!selectedId || isNaN(selectedId)) {
            showFieldError(mechanicIdHidden, 'Please select an available mechanic for your service.');
            isValid = false;
            if (!firstInvalidField) firstInvalidField = mechanicsContainer;
        }

        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof firstInvalidField.focus === 'function') {
                firstInvalidField.focus();
            }
        }

        return isValid;
    }

    /**
     * Show Inline Field Error
     */
    function showFieldError(inputElement, message) {
        inputElement.classList.add('is-invalid');
        const parent = inputElement.closest('.form-group') || inputElement.parentElement;
        let feedback = parent.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            parent.appendChild(feedback);
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    /**
     * Clear Inline Field Error
     */
    function clearFieldError(inputElement) {
        inputElement.classList.remove('is-invalid');
        const parent = inputElement.closest('.form-group') || inputElement.parentElement;
        const feedback = parent.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }

    /**
     * Handle Form Submission
     */
    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Run client validation
        if (!validateForm()) {
            showToast('Please fix the highlighted form errors before proceeding.', 'danger', 'Validation Error');
            return;
        }

        // Set Loading State
        submitBtn.disabled = true;
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                <path d="M12 2a10 10 0 0 1 10 10"></path>
            </svg>
            <span>Confirming Booking...</span>
        `;

        const payload = {
            client_name: document.getElementById('clientName').value.trim(),
            client_address: document.getElementById('clientAddress').value.trim(),
            client_phone: phoneInput.value.trim(),
            car_license_no: document.getElementById('carLicenseNo').value.trim(),
            car_engine_no: engineInput.value.trim(),
            appointment_date: dateInput.value.trim(),
            mechanic_id: parseInt(mechanicIdHidden.value, 10),
            notes: document.getElementById('bookingNotes') ? document.getElementById('bookingNotes').value.trim() : ''
        };

        try {
            const response = await fetch('api/book_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                // If server returned specific field errors
                if (result.errors) {
                    Object.entries(result.errors).forEach(([field, msg]) => {
                        const targetInput = document.querySelector(`[name="${field}"]`) || 
                                           document.getElementById(field) ||
                                           (field === 'mechanic_id' ? mechanicIdHidden : null);
                        if (targetInput) {
                            showFieldError(targetInput, msg);
                        }
                    });
                }
                throw new Error(result.message || 'Booking submission failed.');
            }

            // Success Handling
            showToast(result.message, 'success', 'Appointment Confirmed!');

            // Display Confirmation Summary Card
            displayConfirmationCard(result.appointment);

            // Reset Form fields
            bookingForm.reset();
            dateInput.value = payload.appointment_date;
            mechanicIdHidden.value = '';

            // Refresh mechanic availability
            fetchMechanicsAvailability(payload.appointment_date);

            // Scroll to confirmation box
            confirmationBox.scrollIntoView({ behavior: 'smooth', block: 'start' });

        } catch (err) {
            console.error('Submission error:', err);
            showToast(err.message, 'danger', 'Booking Rejected');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });

    /**
     * Render Appointment Confirmation Box
     */
    function displayConfirmationCard(app) {
        if (!confirmationBox) return;

        confirmationBox.innerHTML = `
            <div class="card" style="border-left: 6px solid var(--success); margin-bottom: 28px; background: #ffffff;">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 44px; height: 44px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                            ✓
                        </div>
                        <div>
                            <h3 style="color: #166534; font-size: 1.2rem; margin: 0;">Appointment Successfully Confirmed!</h3>
                            <p class="text-muted text-sm" style="margin: 0;">Booking Reference: <strong>#${app.id}</strong></p>
                        </div>
                    </div>
                    <div style="background: var(--gray-50); border-radius: var(--radius-md); padding: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 0.875rem;">
                        <div>
                            <span class="text-muted text-xs font-semibold" style="text-transform: uppercase; display: block;">Client</span>
                            <strong>${escapeHtml(app.client_name)}</strong> (${escapeHtml(app.client_phone)})
                        </div>
                        <div>
                            <span class="text-muted text-xs font-semibold" style="text-transform: uppercase; display: block;">Vehicle Plate</span>
                            <span class="license-pill">${escapeHtml(app.car_license_no)}</span>
                        </div>
                        <div>
                            <span class="text-muted text-xs font-semibold" style="text-transform: uppercase; display: block;">Appointment Date</span>
                            <strong>${escapeHtml(app.appointment_date)}</strong>
                        </div>
                        <div>
                            <span class="text-muted text-xs font-semibold" style="text-transform: uppercase; display: block;">Assigned Mechanic</span>
                            <strong>${escapeHtml(app.mechanic_name)}</strong>
                        </div>
                    </div>
                    <p class="text-muted text-xs" style="margin-top: 14px;">
                        Please bring your vehicle to the workshop on the scheduled date before 9:00 AM. Thank you for choosing Apex Auto Workshop!
                    </p>
                </div>
            </div>
        `;
        confirmationBox.style.display = 'block';
    }

    /**
     * Toast Notification Helper
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

        // Auto remove after 6 seconds
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

// Add Keyframe for spinner animation
const styleSheet = document.createElement("style");
styleSheet.innerText = `
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
`;
document.head.appendChild(styleSheet);
