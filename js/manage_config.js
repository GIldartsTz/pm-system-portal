// 1. ฟังก์ชันกรองตาราง 
function filterTable(tableId, filterValue) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var rows = table.getElementsByTagName("tr");


    for (var i = 1; i < rows.length; i++) { 
        var row = rows[i];
        

        var sys = row.getAttribute("data-sys");
        var cat = row.getAttribute("data-cat");


        if (!sys && !row.classList.contains('section-header')) continue;

       
        if (filterValue === "all") {
            row.style.display = "";
        } 
        
        
        else if (filterValue === "hardware") {
            if (cat === "hardware") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
        
        else if (filterValue === "software") {
            if (cat === "software") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
        
        else if (filterValue === "hardsoft") {
            if (sys === "hardsoft") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
        
       
        else {
            if (sys === filterValue) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }
}

// 2. ฟังก์ชันจัดการหน้าอิสระ
window.editPage = function(id, name) {
    const pageIdInput = document.getElementById('page_id');
    const pageNameInput = document.getElementById('page_name');
    const btnSubmit = document.getElementById('btnPageSubmit');
    const btnCancel = document.getElementById('btnCancelEdit');

    if (pageIdInput && pageNameInput) {
        
        pageIdInput.value = id;
        pageNameInput.value = name;
        
        
        if (btnSubmit) {
            btnSubmit.innerHTML = '<i class="fa-solid fa-save"></i> บันทึกการแก้ไข';
            btnSubmit.style.background = '#f59e0b'; // 
        }
        
        
        if (btnCancel) {
            btnCancel.style.display = 'inline-block';
        }

        
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
        
        pageIdInput.value = '0';
        pageNameInput.value = '';
        
        
        if (btnSubmit) {
            btnSubmit.innerHTML = '<i class="fa-solid fa-plus"></i> สร้างหน้า';
            btnSubmit.style.background = '#10b981'; 
        }
        
        
        if (btnCancel) {
            btnCancel.style.display = 'none';
        }
    }
};

// 3. เปลี่ยนธีม Dark/Light
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