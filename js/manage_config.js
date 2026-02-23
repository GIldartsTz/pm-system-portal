// manage_config.js - ฉบับสมบูรณ์ (Filter + Theme + Page Edit)

// 1. ฟังก์ชันกรองตาราง (Filter Table)
function filterTable(tableId, filterValue) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var rows = table.getElementsByTagName("tr");

    // เริ่ม loop ที่ 1 เพราะแถว 0 คือ Header ตารางหลัก
    for (var i = 1; i < rows.length; i++) { 
        var row = rows[i];
        
        // รับค่าป้ายกำกับที่ฝังไว้ในแต่ละแถว
        var sys = row.getAttribute("data-sys");
        var cat = row.getAttribute("data-cat");

        // ข้ามแถวที่ไม่มีข้อมูล (ยกเว้น Header ของตาราง)
        if (!sys && !row.classList.contains('section-header')) continue;

        // --- Logic การกรอง ---
        
        // 1. แสดงทั้งหมด
        if (filterValue === "all") {
            row.style.display = "";
        } 
        
        // 2. กรองเฉพาะ Hardware (โชว์แถวที่เป็น Hardware + หัวข้อ Hardware)
        else if (filterValue === "hardware") {
            if (cat === "hardware") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
        // 3. กรองเฉพาะ Software (โชว์แถวที่เป็น Software + หัวข้อ Software)
        else if (filterValue === "software") {
            if (cat === "software") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
        // 4. กรอง HardSoft รวม (โชว์หมดทั้ง Hardware และ Software)
        else if (filterValue === "hardsoft") {
            if (sys === "hardsoft") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
        // 5. กรณีอื่นๆ (Server, Network, Backup)
        else {
            if (sys === filterValue) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }
}

// 2. ฟังก์ชันจัดการหน้าอิสระ (Edit/Cancel Page)
window.editPage = function(id, name) {
    // ดึง Element ฟอร์มจัดการหน้า
    const pageIdInput = document.getElementById('page_id');
    const pageNameInput = document.getElementById('page_name');
    const btnSubmit = document.getElementById('btnPageSubmit');
    const btnCancel = document.getElementById('btnCancelEdit');

    if (pageIdInput && pageNameInput) {
        // ใส่ข้อมูลลงในฟอร์ม
        pageIdInput.value = id;
        pageNameInput.value = name;
        
        // เปลี่ยนหน้าตาปุ่มเป็นโหมดแก้ไข
        if (btnSubmit) {
            btnSubmit.innerHTML = '<i class="fa-solid fa-save"></i> บันทึกการแก้ไข';
            btnSubmit.style.background = '#f59e0b'; // สีส้ม (Warning/Edit)
        }
        
        // แสดงปุ่มยกเลิก
        if (btnCancel) {
            btnCancel.style.display = 'inline-block';
        }

        // เลื่อนหน้าจอขึ้นไปที่ฟอร์ม
        const form = document.getElementById('customPageForm');
        if (form) {
            window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
        }
    }
};

window.cancelEditPage = function() {
    const pageIdInput = document.getElementById('page_id');
    const pageNameInput = document.getElementById('page_name');
    const btnSubmit = document.getElementById('btnPageSubmit');
    const btnCancel = document.getElementById('btnCancelEdit');

    if (pageIdInput && pageNameInput) {
        // ล้างค่าฟอร์ม
        pageIdInput.value = '0';
        pageNameInput.value = '';
        
        // คืนค่าปุ่มเป็นโหมดสร้างใหม่
        if (btnSubmit) {
            btnSubmit.innerHTML = '<i class="fa-solid fa-plus"></i> สร้างหน้า';
            btnSubmit.style.background = '#10b981'; // สีเขียวเดิม
        }
        
        // ซ่อนปุ่มยกเลิก
        if (btnCancel) {
            btnCancel.style.display = 'none';
        }
    }
};

// 3. Theme Switcher (เปลี่ยนธีม Dark/Light)
document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');

    // โหลดค่าธีมเดิม
    const savedTheme = localStorage.getItem('theme') || 'light';
    root.setAttribute('data-theme', savedTheme);
    updateIcon(savedTheme);

    // ฟังก์ชันเปลี่ยนธีม
    window.toggleTheme = function() {
        const currentTheme = root.getAttribute('data-theme');
        const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        root.setAttribute('data-theme', targetTheme);
        localStorage.setItem('theme', targetTheme);
        updateIcon(targetTheme);
    };

    // ฟังก์ชันเปลี่ยนไอคอน
    function updateIcon(theme) {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
    }
});