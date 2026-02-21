// dashboard.js

// ฟังก์ชันกรองการแสดงผล (Filter System & Frequency)
function applyFilter() {
    const sysVal = document.getElementById('filterSys').value;
    const freqVal = document.getElementById('filterFreq').value;
    const cards = document.querySelectorAll('.card');
    
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardSys = card.getAttribute('data-sys');
        const cardFreq = card.getAttribute('data-freq');
        
        // ตรวจสอบเงื่อนไข (Match System & Frequency)
        const matchSys = (sysVal === 'all' || sysVal === cardSys);
        const matchFreq = (freqVal === 'all' || freqVal === cardFreq);
        
        if (matchSys && matchFreq) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });
    
    // แสดงข้อความเมื่อไม่พบข้อมูล
    const noDataMsg = document.getElementById('noDataMsg');
    if (noDataMsg) {
        noDataMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

// (Optional) เรียก applyFilter ครั้งแรกเมื่อโหลดหน้าเว็บ
document.addEventListener('DOMContentLoaded', () => {
    applyFilter();
});