let currentTool = 'safe';
let isMouseDown = false;
const scrollBox = document.getElementById('scrollBox');

// ฟังก์ชันเปลี่ยนเครื่องมือ (View / Check / Cross / Eraser)
function setTool(val, btn) {
    if (currentTool === val && val !== 'safe') val = 'safe';
    
    
    document.querySelectorAll('.actions button').forEach(b => b.className = '');
    
    
    if (val === 'safe') btn.className = 'active-view';
    else if (val === 1) btn.className = 'active-check';
    else if (val === 0) btn.className = 'active-cross';
    else btn.className = 'active-clear';
    
    currentTool = val;
    scrollBox.style.cursor = (val === 'safe') ? 'default' : 'crosshair';
}

// ฟังก์ชันอัปเดตเซลล์ 
function updateCell(el) {
    let oldVal = el.dataset.val;
    if (oldVal === "" || oldVal === "null") oldVal = null;
    if (oldVal == "1") oldVal = 1;
    if (oldVal == "0") oldVal = 0;

    if (oldVal === currentTool) return; 

    
    let cell = el.querySelector('.cell-btn');
    cell.className = 'cell-btn ' + (currentTool === 1 ? 'st-ok' : (currentTool === 0 ? 'st-fail' : 'st-null'));
    cell.innerHTML = currentTool === 1 ? '<i class="fa-solid fa-check"></i>' : (currentTool === 0 ? '<i class="fa-solid fa-xmark"></i>' : '');

    
    el.dataset.val = (currentTool === null) ? "" : currentTool;

    // Recalculate Row Sum 
    let row = el.parentElement;
    let sumSpan = row.querySelector('.row-sum');
    let sum = parseInt(sumSpan.innerText) || 0;
    if (oldVal === 1) sum--;
    if (currentTool === 1) sum++;
    sumSpan.innerText = sum;

    // Update Grand Total 
    let gt = document.getElementById('grandTotal');
    let g = parseInt(gt.innerText.replace(/,/g, '')) || 0;
    if (oldVal === 1) g--;
    if (currentTool === 1) g++;
    gt.innerText = g.toLocaleString();

    fetch('../Backup_log/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            table: APP_CONFIG.tableType, 
            name: el.dataset.sys,
            column: el.dataset.col,
            value: currentTool,
            month: APP_CONFIG.curM, 
            year: APP_CONFIG.curY   
        })
    }).then(r => r.json()).then(d => {
        if (d.success && d.time) document.getElementById('lastUpd').innerText = d.time;
    }).catch(err => console.error('Error updating:', err));
}

// ฟังก์ชัน Fill Column 
function fillCol(c) {
    if (currentTool === 'safe') return alert('Please select a tool first (Check/Cross/Eraser)!');
    if (confirm('Apply to ALL rows in this column?')) {
        document.querySelectorAll(`td[data-col="${c}"]`).forEach(el => updateCell(el));
    }
}


document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('tbl');

    // Mouse Down
    table.addEventListener('mousedown', e => {
        if (currentTool !== 'safe' && e.target.closest('.c-wrap')) {
            e.preventDefault();
            isMouseDown = true;
            updateCell(e.target.closest('.c-wrap'));
        }
    });

    // Mouse Over
    table.addEventListener('mouseover', e => {
        if (isMouseDown && currentTool !== 'safe' && e.target.closest('.c-wrap')) {
            updateCell(e.target.closest('.c-wrap'));
        }
    });

    // Mouse Up
    document.addEventListener('mouseup', () => isMouseDown = false);
});