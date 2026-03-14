// ฟังก์ชันกรองการแสดงผล
function applyFilter() {
    const sysVal = document.getElementById('filterSys').value;
    const freqVal = document.getElementById('filterFreq').value;
    const cards = document.querySelectorAll('.card');
    
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardSys = card.getAttribute('data-sys');
        const cardFreq = card.getAttribute('data-freq');
        
        
        const matchSys = (sysVal === 'all' || sysVal === cardSys);
        const matchFreq = (freqVal === 'all' || freqVal === cardFreq);
        
        if (matchSys && matchFreq) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });
    
    const noDataMsg = document.getElementById('noDataMsg');
    if (noDataMsg) {
        noDataMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}


document.addEventListener('DOMContentLoaded', () => {
    applyFilter();
});