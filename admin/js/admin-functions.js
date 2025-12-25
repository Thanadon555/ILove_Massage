/**
 * Admin Functions - JavaScript สำหรับหน้า Admin ทั้งหมด
 * แก้ไขปัญหาปุ่มที่คลิกแล้วไม่ทำงาน
 */

/**
 * FormValidator Class
 * Provides client-side form validation
 */
class FormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.errors = {};
    }
    
    /**
     * Validate required field
     */
    required(fieldName, message = 'ฟิลด์นี้จำเป็นต้องกรอก') {
        const field = this.form.elements[fieldName];
        if (!field || !field.value.trim()) {
            this.errors[fieldName] = message;
            return false;
        }
        return true;
    }
    
    /**
     * Validate email format
     */
    email(fieldName, message = 'อีเมลไม่ถูกต้อง') {
        const field = this.form.elements[fieldName];
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (field && field.value && !emailRegex.test(field.value)) {
            this.errors[fieldName] = message;
            return false;
        }
        return true;
    }
    
    /**
     * Validate phone number (10 digits)
     */
    phone(fieldName, message = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก') {
        const field = this.form.elements[fieldName];
        const phoneRegex = /^[0-9]{10}$/;
        if (field && field.value && !phoneRegex.test(field.value)) {
            this.errors[fieldName] = message;
            return false;
        }
        return true;
    }
    
    /**
     * Validate number range
     */
    numberRange(fieldName, min, max, message = null) {
        const field = this.form.elements[fieldName];
        const value = parseFloat(field.value);
        if (isNaN(value) || value < min || value > max) {
            this.errors[fieldName] = message || `ค่าต้องอยู่ระหว่าง ${min} ถึง ${max}`;
            return false;
        }
        return true;
    }
    
    /**
     * Validate file upload
     */
    file(fieldName, allowedTypes, maxSize, message = null) {
        const field = this.form.elements[fieldName];
        if (!field || !field.files || field.files.length === 0) {
            return true; // Optional field
        }
        
        const file = field.files[0];
        
        // Check file type
        if (allowedTypes && !allowedTypes.includes(file.type)) {
            this.errors[fieldName] = message || 'ประเภทไฟล์ไม่ถูกต้อง';
            return false;
        }
        
        // Check file size
        if (maxSize && file.size > maxSize) {
            this.errors[fieldName] = message || `ขนาดไฟล์ต้องไม่เกิน ${maxSize / 1024 / 1024} MB`;
            return false;
        }
        
        return true;
    }
    
    /**
     * Show validation errors
     */
    showErrors() {
        // Clear previous errors
        this.form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        this.form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
        
        // Show new errors
        for (const [fieldName, message] of Object.entries(this.errors)) {
            const field = this.form.elements[fieldName];
            if (field) {
                field.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.textContent = message;
                field.parentNode.appendChild(feedback);
            }
        }
    }
    
    /**
     * Check if form is valid
     */
    isValid() {
        return Object.keys(this.errors).length === 0;
    }
    
    /**
     * Reset validation errors
     */
    reset() {
        this.errors = {};
        this.form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        this.form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.container-fluid') || document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            bsAlert.close();
        }, 5000);
    }
}

/**
 * Confirm action with custom message
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

/**
 * Reload table data without page refresh
 */
function reloadTableData(tableId, url) {
    fetch(url)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.querySelector(`#${tableId}`);
            const oldTable = document.querySelector(`#${tableId}`);
            if (newTable && oldTable) {
                oldTable.innerHTML = newTable.innerHTML;
            }
        })
        .catch(error => {
            console.error('Error reloading table:', error);
            showAlert('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'danger');
        });
}

// ฟังก์ชันสำหรับ Export Excel
function exportToExcel() {
    const startDate = document.querySelector('input[name="start_date"]')?.value;
    const endDate = document.querySelector('input[name="end_date"]')?.value;
    const reportType = document.querySelector('select[name="report_type"]')?.value;
    
    if (startDate && endDate) {
        const url = `${window.location.pathname}?export=excel&start_date=${startDate}&end_date=${endDate}&report_type=${reportType || 'overview'}`;
        window.location.href = url;
    } else {
        alert('กรุณาเลือกช่วงวันที่ก่อน');
    }
}

// ฟังก์ชันสำหรับ Toggle Message
function toggleMessage(contactId) {
    const messageElement = document.getElementById('message-' + contactId);
    const button = messageElement?.nextElementSibling;
    
    if (messageElement && button) {
        if (messageElement.classList.contains('expanded')) {
            messageElement.classList.remove('expanded');
            button.textContent = 'อ่านเพิ่มเติม';
        } else {
            messageElement.classList.add('expanded');
            button.textContent = 'ซ่อน';
        }
    }
}

// ฟังก์ชันสำหรับ Preview Image
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-fluid mt-2';
            img.style.maxHeight = '200px';
            preview.appendChild(img);
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// ฟังก์ชันสำหรับ Download PDF
function downloadPDF() {
    window.print();
}

// ฟังก์ชันสำหรับ Email Receipt
function emailReceipt() {
    alert('ฟังก์ชันการส่งอีเมลกำลังพัฒนา...');
}

// Initialize เมื่อ DOM โหลดเสร็จ
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Functions Loaded');
    
    // ตรวจสอบว่า Bootstrap ถูกโหลดหรือไม่
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JS ไม่ถูกโหลด! กรุณาตรวจสอบ script tag');
        alert('เกิดข้อผิดพลาด: ไม่สามารถโหลด Bootstrap JavaScript ได้\nกรุณารีเฟรชหน้าเว็บ');
        return;
    }
    
    // ===================================================================
    // ลบ manual modal, dropdown, tab toggles ออก
    // ให้ Bootstrap 5 จัดการอัตโนมัติผ่าน data attributes
    // ===================================================================
    
    // ===================================================================
    // เก็บเฉพาะ custom functionality ที่จำเป็น
    // ===================================================================
    
    // ป้องกัน scroll ของ number input
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('wheel', function(e) {
            e.preventDefault();
        });
    });
    
    // เพิ่ม ripple effect สำหรับปุ่ม
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Auto-dismiss alerts หลัง 5 วินาที
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // ===================================================================
    // เพิ่ม Bootstrap Modal Event Listeners สำหรับ custom behavior
    // ===================================================================
    
    document.querySelectorAll('.modal').forEach(modal => {
        // เมื่อ modal เริ่มเปิด
        modal.addEventListener('show.bs.modal', function(e) {
            console.log('Modal opening:', this.id);
        });
        
        // เมื่อ modal เปิดเสร็จแล้ว
        modal.addEventListener('shown.bs.modal', function(e) {
            console.log('Modal opened:', this.id);
            // Focus first input in modal
            const firstInput = this.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // เมื่อ modal เริ่มปิด
        modal.addEventListener('hide.bs.modal', function(e) {
            console.log('Modal closing:', this.id);
        });
        
        // เมื่อ modal ปิดเสร็จแล้ว
        modal.addEventListener('hidden.bs.modal', function(e) {
            console.log('Modal closed:', this.id);
            // Reset form validation state
            const form = this.querySelector('form');
            if (form) {
                form.classList.remove('was-validated');
                form.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                form.querySelectorAll('.invalid-feedback').forEach(el => {
                    el.remove();
                });
            }
        });
    });
});

// ฟังก์ชันสำหรับ Confirm Delete
function confirmDelete(message) {
    return confirm(message || 'คุณแน่ใจว่าต้องการลบรายการนี้?');
}

// ฟังก์ชันสำหรับ Print
function printPage() {
    window.print();
}

// Export functions เพื่อให้สามารถเรียกใช้จาก inline onclick ได้
window.FormValidator = FormValidator;
window.showAlert = showAlert;
window.confirmAction = confirmAction;
window.reloadTableData = reloadTableData;
window.exportToExcel = exportToExcel;
window.toggleMessage = toggleMessage;
window.previewImage = previewImage;
window.downloadPDF = downloadPDF;
window.emailReceipt = emailReceipt;
window.confirmDelete = confirmDelete;
window.printPage = printPage;
