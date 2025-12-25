<!-- Admin Scripts Template -->
<!-- ไฟล์นี้ควรถูก include ก่อน </body> tag ในทุกหน้า admin -->

<!-- 
===========================================
CSS Links (ควรใส่ใน <head> section)
===========================================
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="../admin/css/admin-bootstrap-custom.css" rel="stylesheet">
===========================================
-->

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Admin Functions -->
<script src="../admin/js/admin-functions.js"></script>

<!-- Additional Scripts -->
<script>
// ตรวจสอบว่า Bootstrap ถูกโหลดหรือไม่
if (typeof bootstrap === 'undefined') {
    console.error('Bootstrap JS ไม่ถูกโหลด!');
    alert('เกิดข้อผิดพลาด: ไม่สามารถโหลด Bootstrap JavaScript ได้\nกรุณารีเฟรชหน้าเว็บ');
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Initialize popovers
document.addEventListener('DOMContentLoaded', function() {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// ============================================
// Bootstrap Form Validation
// ============================================

/**
 * Bootstrap 5 Form Validation
 * ใช้กับฟอร์มที่มี class 'needs-validation'
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // เลือกฟอร์มทั้งหมดที่ต้องการ validation
    const forms = document.querySelectorAll('.needs-validation');
    
    // วนลูปและป้องกัน submission
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // หา field แรกที่ invalid และ focus
                const firstInvalidField = form.querySelector(':invalid');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                    
                    // Scroll ไปยัง field ที่ invalid
                    firstInvalidField.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
                
                // แสดง alert แจ้งเตือน
                showValidationAlert('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Real-time validation สำหรับ input fields
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (form.classList.contains('was-validated')) {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                }
            });
            
            input.addEventListener('input', function() {
                if (form.classList.contains('was-validated')) {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                }
            });
        });
    });
});

/**
 * แสดง validation alert
 * @param {string} message - ข้อความที่ต้องการแสดง
 */
function showValidationAlert(message) {
    // ลบ alert เก่าถ้ามี
    const existingAlert = document.querySelector('.validation-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // สร้าง alert ใหม่
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show validation-alert position-fixed top-0 start-50 translate-middle-x mt-3';
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.innerHTML = `
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>ข้อผิดพลาด!</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // ลบ alert อัตโนมัติหลัง 5 วินาที
    setTimeout(() => {
        if (alert && alert.parentNode) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    }, 5000);
}

/**
 * เพิ่ม custom validation rule
 * @param {HTMLElement} input - input element
 * @param {Function} validationFn - validation function ที่ return true/false
 * @param {string} errorMessage - ข้อความ error
 */
function addCustomValidation(input, validationFn, errorMessage) {
    input.addEventListener('input', function() {
        if (!validationFn(this.value)) {
            this.setCustomValidity(errorMessage);
        } else {
            this.setCustomValidity('');
        }
    });
}

/**
 * Validate password confirmation
 * ใช้สำหรับตรวจสอบว่า password และ confirm password ตรงกันหรือไม่
 */
document.addEventListener('DOMContentLoaded', function() {
    const passwordInputs = document.querySelectorAll('input[name="new_password"], input[name="password"]');
    const confirmInputs = document.querySelectorAll('input[name="confirm_password"]');
    
    confirmInputs.forEach(confirmInput => {
        const form = confirmInput.closest('form');
        const passwordInput = form ? form.querySelector('input[name="new_password"], input[name="password"]') : null;
        
        if (passwordInput) {
            confirmInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            passwordInput.addEventListener('input', function() {
                if (confirmInput.value && confirmInput.value !== this.value) {
                    confirmInput.setCustomValidity('รหัสผ่านไม่ตรงกัน');
                } else {
                    confirmInput.setCustomValidity('');
                }
            });
        }
    });
});

/**
 * Validate Thai phone number (10 digits)
 */
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"]');
    
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            const phonePattern = /^[0-9]{10}$/;
            if (this.value && !phonePattern.test(this.value)) {
                this.setCustomValidity('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก');
            } else {
                this.setCustomValidity('');
            }
        });
    });
});

/**
 * Auto-add needs-validation class to forms
 * ฟอร์มที่มี required fields จะได้รับ class 'needs-validation' อัตโนมัติ
 */
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form:not(.needs-validation):not(.no-validation)');
    
    forms.forEach(form => {
        // ตรวจสอบว่ามี required fields หรือไม่
        const hasRequiredFields = form.querySelector('[required]');
        
        if (hasRequiredFields) {
            form.classList.add('needs-validation');
            form.setAttribute('novalidate', '');
        }
    });
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Confirm before leaving page with unsaved changes
let formChanged = false;
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('change', () => {
            formChanged = true;
        });
        form.addEventListener('submit', () => {
            formChanged = false;
        });
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});

// ============================================
// Loading States และ Spinners Utilities
// ============================================

/**
 * แสดง loading overlay แบบเต็มหน้าจอ
 * @param {string} message - ข้อความที่จะแสดง (default: 'กำลังโหลด...')
 */
function showLoadingOverlay(message = 'กำลังโหลด...') {
    // ลบ overlay เก่าถ้ามี
    hideLoadingOverlay();
    
    // สร้าง overlay element
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white bg-opacity-75';
    overlay.style.zIndex = '9999';
    overlay.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 fw-bold text-primary">${message}</p>
        </div>
    `;
    
    document.body.appendChild(overlay);
}

/**
 * ซ่อน loading overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * แสดง loading spinner ในปุ่ม submit
 * @param {HTMLElement} button - ปุ่มที่ต้องการแสดง spinner
 * @param {string} loadingText - ข้อความขณะ loading (default: 'กำลังประมวลผล...')
 */
function showButtonLoading(button, loadingText = 'กำลังประมวลผล...') {
    if (!button) return;
    
    // เก็บข้อความเดิม
    button.dataset.originalText = button.innerHTML;
    button.dataset.originalDisabled = button.disabled;
    
    // แสดง spinner และข้อความ
    button.disabled = true;
    button.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        ${loadingText}
    `;
}

/**
 * ซ่อน loading spinner ในปุ่ม submit
 * @param {HTMLElement} button - ปุ่มที่ต้องการซ่อน spinner
 */
function hideButtonLoading(button) {
    if (!button) return;
    
    // คืนค่าข้อความเดิม
    if (button.dataset.originalText) {
        button.innerHTML = button.dataset.originalText;
        button.disabled = button.dataset.originalDisabled === 'true';
        delete button.dataset.originalText;
        delete button.dataset.originalDisabled;
    }
}

/**
 * แสดง loading spinner ในพื้นที่เฉพาะ (container)
 * @param {string|HTMLElement} container - selector หรือ element ที่ต้องการแสดง spinner
 * @param {string} message - ข้อความที่จะแสดง (optional)
 * @param {string} size - ขนาด spinner: 'sm', 'md', 'lg' (default: 'md')
 */
function showContainerLoading(container, message = '', size = 'md') {
    const element = typeof container === 'string' ? document.querySelector(container) : container;
    if (!element) return;
    
    // เก็บเนื้อหาเดิม
    if (!element.dataset.originalContent) {
        element.dataset.originalContent = element.innerHTML;
    }
    
    // กำหนดขนาด spinner
    const spinnerSizes = {
        'sm': 'width: 1.5rem; height: 1.5rem;',
        'md': 'width: 2.5rem; height: 2.5rem;',
        'lg': 'width: 3.5rem; height: 3.5rem;'
    };
    const spinnerSize = spinnerSizes[size] || spinnerSizes['md'];
    
    // แสดง spinner
    element.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" style="${spinnerSize}" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            ${message ? `<p class="mt-3 text-muted">${message}</p>` : ''}
        </div>
    `;
}

/**
 * ซ่อน loading spinner ในพื้นที่เฉพาะ (container)
 * @param {string|HTMLElement} container - selector หรือ element ที่ต้องการซ่อน spinner
 */
function hideContainerLoading(container) {
    const element = typeof container === 'string' ? document.querySelector(container) : container;
    if (!element) return;
    
    // คืนค่าเนื้อหาเดิม
    if (element.dataset.originalContent) {
        element.innerHTML = element.dataset.originalContent;
        delete element.dataset.originalContent;
    }
}

/**
 * แสดง loading spinner แบบ inline (ใช้ใน table row หรือ list item)
 * @param {string} message - ข้อความที่จะแสดง (optional)
 * @returns {string} HTML string ของ spinner
 */
function getInlineSpinner(message = '') {
    return `
        <span class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></span>
        ${message}
    `;
}

/**
 * Auto-attach loading states ให้กับฟอร์มทั้งหมด
 * เมื่อ submit form จะแสดง loading overlay อัตโนมัติ
 */
document.addEventListener('DOMContentLoaded', function() {
    // Auto-loading สำหรับฟอร์มที่มี class 'auto-loading'
    const autoLoadingForms = document.querySelectorAll('form.auto-loading');
    autoLoadingForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // ตรวจสอบว่าฟอร์ม valid หรือไม่
            if (form.checkValidity()) {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    showButtonLoading(submitButton);
                }
                
                // แสดง overlay ถ้ามี attribute data-loading-overlay
                if (form.dataset.loadingOverlay) {
                    showLoadingOverlay(form.dataset.loadingOverlay);
                }
            }
        });
    });
    
    // Auto-loading สำหรับปุ่มที่มี class 'btn-loading'
    const loadingButtons = document.querySelectorAll('.btn-loading');
    loadingButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!button.disabled) {
                const loadingText = button.dataset.loadingText || 'กำลังประมวลผล...';
                showButtonLoading(button, loadingText);
                
                // ถ้ามี data-loading-duration ให้ซ่อนหลังจากเวลาที่กำหนด
                if (button.dataset.loadingDuration) {
                    setTimeout(() => {
                        hideButtonLoading(button);
                    }, parseInt(button.dataset.loadingDuration));
                }
            }
        });
    });
});

// ============================================
// AJAX Loading Helper Functions
// ============================================

/**
 * Fetch wrapper พร้อม loading state
 * @param {string} url - URL ที่ต้องการ fetch
 * @param {object} options - fetch options
 * @param {object} loadingOptions - loading options: { overlay: true/false, button: element, container: element }
 * @returns {Promise}
 */
async function fetchWithLoading(url, options = {}, loadingOptions = {}) {
    try {
        // แสดง loading states
        if (loadingOptions.overlay) {
            showLoadingOverlay(loadingOptions.overlayMessage);
        }
        if (loadingOptions.button) {
            showButtonLoading(loadingOptions.button, loadingOptions.buttonText);
        }
        if (loadingOptions.container) {
            showContainerLoading(loadingOptions.container, loadingOptions.containerMessage);
        }
        
        // ทำ fetch request
        const response = await fetch(url, options);
        
        return response;
        
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
        
    } finally {
        // ซ่อน loading states
        if (loadingOptions.overlay) {
            hideLoadingOverlay();
        }
        if (loadingOptions.button) {
            hideButtonLoading(loadingOptions.button);
        }
        if (loadingOptions.container) {
            hideContainerLoading(loadingOptions.container);
        }
    }
}

// ============================================
// Page Load Progress Bar (Optional)
// ============================================

/**
 * แสดง progress bar ด้านบนหน้าจอขณะโหลดหน้า
 */
function initPageLoadProgress() {
    // สร้าง progress bar element
    const progressBar = document.createElement('div');
    progressBar.id = 'pageLoadProgress';
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, var(--bs-primary), var(--bs-info));
        z-index: 10000;
        transition: width 0.3s ease;
    `;
    document.body.appendChild(progressBar);
    
    // Simulate progress
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress > 90) progress = 90;
        progressBar.style.width = progress + '%';
    }, 200);
    
    // Complete on page load
    window.addEventListener('load', () => {
        clearInterval(interval);
        progressBar.style.width = '100%';
        setTimeout(() => {
            progressBar.remove();
        }, 300);
    });
}

// เรียกใช้ page load progress (comment out ถ้าไม่ต้องการ)
// initPageLoadProgress();

</script>
