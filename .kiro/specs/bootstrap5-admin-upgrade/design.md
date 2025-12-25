# Design Document

## Overview

เอกสารนี้อธิบายการออกแบบการอัพเกรด UI ของหน้า admin ทั้งหมดให้ใช้ Bootstrap 5 framework โดยจะใช้ประโยชน์จาก Bootstrap components, utilities และ grid system เพื่อสร้าง UI ที่ทันสมัย responsive และง่ายต่อการ maintain

## Architecture

### System Structure

```
admin/
├── css/
│   └── admin-bootstrap-custom.css (ไฟล์ custom CSS ใหม่)
├── templates/
│   ├── navbar-admin.php (อัพเกรดเป็น Bootstrap 5 navbar)
│   ├── footer-admin.php (อัพเกรดเป็น Bootstrap 5)
│   └── admin-scripts.php (เพิ่ม Bootstrap 5 JS)
└── [admin pages].php (อัพเกรดทุกหน้า)
```

### Bootstrap 5 Integration

**CDN Links:**
```html
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<!-- Custom CSS -->
<link href="../css/admin-bootstrap-custom.css" rel="stylesheet">

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
```

### Design System Layers

1. **Bootstrap Foundation**: Bootstrap 5 core CSS และ JS
2. **Custom Theme**: admin-bootstrap-custom.css สำหรับ brand colors และ overrides
3. **Page Components**: ใช้ Bootstrap components ตามมาตรฐาน
4. **Utility Classes**: ใช้ Bootstrap utilities สำหรับ spacing, colors, typography

## Components and Interfaces

### 1. Custom Theme Variables


**admin-bootstrap-custom.css:**
```css
/* Custom Bootstrap 5 Theme Variables */
:root {
  /* Brand Colors */
  --bs-primary: #4361ee;
  --bs-primary-rgb: 67, 97, 238;
  --bs-secondary: #3f37c9;
  --bs-secondary-rgb: 63, 55, 201;
  --bs-success: #06d6a0;
  --bs-success-rgb: 6, 214, 160;
  --bs-danger: #ef476f;
  --bs-danger-rgb: 239, 71, 111;
  --bs-warning: #ffd166;
  --bs-warning-rgb: 255, 209, 102;
  --bs-info: #118ab2;
  --bs-info-rgb: 17, 138, 178;
  
  /* Thai Font */
  --bs-body-font-family: 'Sarabun', 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --bs-body-font-size: 1rem;
  --bs-body-font-weight: 400;
  --bs-body-line-height: 1.6;
  
  /* Spacing */
  --bs-gutter-x: 1.5rem;
  --bs-gutter-y: 1.5rem;
  
  /* Border Radius */
  --bs-border-radius: 0.5rem;
  --bs-border-radius-sm: 0.375rem;
  --bs-border-radius-lg: 0.75rem;
  --bs-border-radius-xl: 1rem;
  
  /* Shadows */
  --bs-box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  --bs-box-shadow-sm: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.05);
  --bs-box-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

/* Body */
body {
  font-family: var(--bs-body-font-family);
  background-color: #f8f9fa;
}

/* Custom Card Enhancements */
.card {
  border: none;
  box-shadow: var(--bs-box-shadow);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: var(--bs-box-shadow-lg);
}

/* Gradient Card Headers */
.card-header.bg-gradient-primary {
  background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
  color: white;
}

.card-header.bg-gradient-success {
  background: linear-gradient(135deg, var(--bs-success), var(--bs-info));
  color: white;
}

.card-header.bg-gradient-danger {
  background: linear-gradient(135deg, var(--bs-danger), var(--bs-warning));
  color: white;
}
```

### 2. Navbar Component

**Bootstrap 5 Navbar Structure:**
```html
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <i class="bi bi-spa"></i> Admin Panel
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link active" href="dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="manage_bookings.php">
            <i class="bi bi-calendar-check"></i> Bookings
          </a>
        </li>
        <!-- More nav items -->
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> Admin
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
```

### 3. Statistics Cards

**Bootstrap 5 Card Grid:**
```html
<div class="row g-4 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card text-white bg-primary">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-subtitle mb-2 text-white-50">Total Bookings</h6>
            <h2 class="card-title mb-0">150</h2>
          </div>
          <div class="fs-1">
            <i class="bi bi-calendar-check"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- More stat cards -->
</div>
```

### 4. Data Tables

**Bootstrap 5 Table:**
```html
<div class="card">
  <div class="card-header bg-gradient-primary">
    <h5 class="mb-0"><i class="bi bi-table"></i> Bookings List</h5>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>1</td>
            <td>John Doe</td>
            <td>Thai Massage</td>
            <td>2024-01-15</td>
            <td><span class="badge bg-success">Confirmed</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary"><i class="bi bi-eye"></i></button>
                <button class="btn btn-outline-warning"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
```

### 5. Forms

**Bootstrap 5 Form:**
```html
<div class="card">
  <div class="card-header bg-gradient-primary">
    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Bookings</h5>
  </div>
  <div class="card-body">
    <form>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select class="form-select">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Date From</label>
          <input type="date" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Date To</label>
          <input type="date" class="form-control">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Search
          </button>
          <button type="reset" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Reset
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
```

### 6. Modals

**Bootstrap 5 Modal:**
```html
<div class="modal fade" id="addBookingModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Booking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addBookingForm">
          <div class="mb-3">
            <label class="form-label">Customer Name</label>
            <input type="text" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Service</label>
            <select class="form-select" required>
              <option value="">Select Service</option>
              <option value="1">Thai Massage</option>
            </select>
          </div>
          <!-- More form fields -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" form="addBookingForm">
          <i class="bi bi-check-circle"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>
```

### 7. Alerts

**Bootstrap 5 Alerts:**
```html
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i>
  <strong>Success!</strong> Booking has been created successfully.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <strong>Error!</strong> Failed to delete booking.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### 8. Badges

**Bootstrap 5 Badges:**
```php
<?php
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
        'confirmed' => '<span class="badge bg-info">Confirmed</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}
?>
```

### 9. Loading States

**Bootstrap 5 Spinners:**
```html
<!-- Button with spinner -->
<button class="btn btn-primary" type="button" disabled>
  <span class="spinner-border spinner-border-sm me-2"></span>
  Loading...
</button>

<!-- Page loading overlay -->
<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 9999;">
  <div class="text-center">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
    <p class="mt-3">Loading...</p>
  </div>
</div>
```

## Data Models

### Bootstrap Color Mapping

```javascript
const statusColorMap = {
  pending: 'warning',
  confirmed: 'info',
  completed: 'success',
  cancelled: 'danger',
  approved: 'success',
  rejected: 'danger'
};

const priorityColorMap = {
  low: 'secondary',
  medium: 'warning',
  high: 'danger',
  urgent: 'danger'
};
```

### Responsive Breakpoints

```javascript
const breakpoints = {
  xs: 0,      // < 576px
  sm: 576,    // >= 576px
  md: 768,    // >= 768px
  lg: 992,    // >= 992px
  xl: 1200,   // >= 1200px
  xxl: 1400   // >= 1400px
};
```

## Error Handling

### Form Validation

```html
<form class="needs-validation" novalidate>
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" class="form-control" required>
    <div class="invalid-feedback">
      Please provide a valid email.
    </div>
  </div>
</form>

<script>
// Bootstrap form validation
(function() {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
```

### Error Messages

```php
<?php
function showAlert($type, $message) {
    $icons = [
        'success' => 'check-circle-fill',
        'danger' => 'exclamation-triangle-fill',
        'warning' => 'exclamation-circle-fill',
        'info' => 'info-circle-fill'
    ];
    $icon = $icons[$type] ?? 'info-circle-fill';
    
    echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            <i class='bi bi-{$icon} me-2'></i>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}
?>
```

## Testing Strategy

### Visual Testing Checklist

1. **Desktop (>= 1200px)**
   - Navbar แสดงเต็มรูปแบบ
   - Cards แสดงแบบ 4 columns
   - Tables แสดงทุก columns
   - Modals ขนาดกลาง-ใหญ่

2. **Tablet (768px - 1199px)**
   - Navbar collapsible
   - Cards แสดงแบบ 2-3 columns
   - Tables scrollable horizontal
   - Modals ขนาดกลาง

3. **Mobile (< 768px)**
   - Hamburger menu
   - Cards แสดงแบบ 1 column
   - Tables responsive
   - Modals full width

### Component Testing

1. **Buttons**: Hover, active, disabled states
2. **Forms**: Validation, error messages, success states
3. **Modals**: Open, close, backdrop click, keyboard (ESC)
4. **Alerts**: Display, dismiss, auto-hide
5. **Tables**: Sorting, pagination, responsive view
6. **Navbar**: Collapse, dropdown, active states

### Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Page-Specific Implementations

### Dashboard Page

**Layout:**
```html
<div class="container-fluid py-4">
  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <div>
      <button class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Quick Action
      </button>
    </div>
  </div>
  
  <!-- Statistics Cards -->
  <div class="row g-4 mb-4">
    <!-- 4 stat cards -->
  </div>
  
  <!-- Charts and Recent Items -->
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header bg-gradient-primary">
          <h5 class="mb-0">Booking Chart</h5>
        </div>
        <div class="card-body">
          <!-- Chart here -->
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header bg-gradient-primary">
          <h5 class="mb-0">Recent Bookings</h5>
        </div>
        <div class="card-body">
          <div class="list-group list-group-flush">
            <!-- List items -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Management Pages

**Common Structure:**
```html
<div class="container-fluid py-4">
  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Manage [Resource]</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="bi bi-plus-circle"></i> Add New
    </button>
  </div>
  
  <!-- Filter Card -->
  <div class="card mb-4">
    <!-- Filter form -->
  </div>
  
  <!-- Statistics Cards -->
  <div class="row g-4 mb-4">
    <!-- Stat cards -->
  </div>
  
  <!-- Data Table -->
  <div class="card">
    <!-- Table -->
  </div>
  
  <!-- Modals -->
  <!-- Add/Edit/Delete modals -->
</div>
```

## Implementation Priority

### Phase 1: Foundation (สัปดาห์ที่ 1)
1. สร้าง admin-bootstrap-custom.css
2. อัพเดท templates (navbar, footer, scripts)
3. ทดสอบ Bootstrap integration

### Phase 2: Core Pages (สัปดาห์ที่ 2)
1. Dashboard
2. Manage Bookings
3. Manage Users
4. Manage Therapists

### Phase 3: Additional Pages (สัปดาห์ที่ 3)
1. Manage Services
2. Manage Payments
3. Manage Schedule
4. Manage Holidays

### Phase 4: Special Pages (สัปดาห์ที่ 4)
1. Reports
2. Contact
3. Profile
4. Receipt & Print Report

### Phase 5: Testing & Refinement (สัปดาห์ที่ 5)
1. Cross-browser testing
2. Responsive testing
3. Performance optimization
4. Bug fixes

## Migration Strategy

### Step-by-Step Process

1. **Backup**: สำรองไฟล์เดิมทั้งหมด
2. **Add Bootstrap**: เพิ่ม Bootstrap CDN links
3. **Replace Classes**: แทนที่ custom classes ด้วย Bootstrap classes
4. **Test**: ทดสอบแต่ละหน้า
5. **Refine**: ปรับแต่ง custom CSS ตามต้องการ
6. **Remove Old CSS**: ลบ CSS เก่าที่ไม่ใช้แล้ว

### Class Mapping Guide

```
Old Custom Class → Bootstrap 5 Class
----------------------------------------
.btn-admin-primary → .btn.btn-primary
.admin-card → .card
.admin-card-header → .card-header
.admin-table → .table.table-hover
.admin-badge-success → .badge.bg-success
.admin-alert-success → .alert.alert-success
.admin-form-control → .form-control
```

## Design Decisions

### Why Bootstrap 5?

1. **Modern**: ไม่ต้องพึ่ง jQuery, ใช้ vanilla JavaScript
2. **Comprehensive**: มี components ครบครัน
3. **Responsive**: Grid system ที่ยืดหยุ่น
4. **Customizable**: สามารถ override variables ได้ง่าย
5. **Well-documented**: เอกสารครบถ้วน community ใหญ่
6. **Maintained**: มีการอัพเดทสม่ำเสมอ

### Why CDN?

1. **Fast**: โหลดเร็วจาก CDN servers
2. **Cached**: Browser อาจ cache ไว้แล้ว
3. **No Build**: ไม่ต้อง compile หรือ build
4. **Easy Update**: เปลี่ยน version ได้ง่าย

### Custom CSS Strategy

1. **Minimal Override**: Override เฉพาะที่จำเป็น
2. **Use Variables**: ใช้ CSS variables สำหรับ theming
3. **Utility First**: ใช้ Bootstrap utilities ก่อน
4. **Component Enhancement**: เพิ่ม effects เล็กน้อย (hover, transitions)

## Future Enhancements

1. **Dark Mode**: เพิ่ม dark theme toggle
2. **Custom Components**: สร้าง reusable PHP components
3. **SASS Compilation**: ใช้ SASS สำหรับ advanced customization
4. **Progressive Web App**: เพิ่ม PWA features
5. **Accessibility**: ปรับปรุง ARIA labels และ keyboard navigation
