document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');

    // 1. ตรวจสอบค่า Theme ที่บันทึกไว้ใน LocalStorage
    if (localStorage.getItem('theme') === 'dark') {
        root.setAttribute('data-theme', 'dark');
        if (themeIcon) themeIcon.className = 'fa-solid fa-sun'; 
    }

    // 2. ฟังก์ชันสลับ Theme 
    window.toggleTheme = function() {
        const isDark = root.getAttribute('data-theme') === 'dark';
        
        
        root.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        
        
        if (themeIcon) {
            themeIcon.className = isDark ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }
    };
});