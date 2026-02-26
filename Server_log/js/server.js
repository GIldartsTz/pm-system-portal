// server.js

// ตัวแปร Global (currentTool, isMouseDown)
let currentTool = 'safe';
let isMouseDown = false;
const scrollBox = document.getElementById('scrollBox');

// ฟังก์ชันเปลี่ยนเครื่องมือ (View / Check / Cross / Eraser)
function setTool(val, btn) {
    if (currentTool === val && val !== 'safe') val = 'safe';
    
    // Reset ปุ่มทั้งหมด
    document.querySelectorAll('.actions button').forEach(b => b.className = '');
    
    // ตั้ง Class ให้ปุ่มที่เลือก
    if (val === 'safe') btn.className = 'active-view';
    else if (val === 1) btn.className = 'active-check';
    else if (val === 0) btn.className = 'active-cross';
    else btn.className = 'active-clear';
    
    currentTool = val;
    scrollBox.style.cursor = (val === 'safe') ? 'default' : 'crosshair';
}

// ฟังก์ชันอัปเดตเซลล์ (หัวใจหลัก)
function updateCell(el) {
    let oldVal = el.dataset.val;
    // แปลงค่าให้เป็นมาตรฐาน (รองรับ Null สำหรับ Eraser)
    if (oldVal === "" || oldVal === "null") oldVal = null;
    if (oldVal == "1") oldVal = 1;
    if (oldVal == "0") oldVal = 0;

    if (oldVal === currentTool) return; // ค่าเดิมไม่ต้องแก้

    // Update Visual (DOM)
    let cell = el.querySelector('.cell-btn');
    cell.className = 'cell-btn ' + (currentTool === 1 ? 'st-ok' : (currentTool === 0 ? 'st-fail' : 'st-null'));
    cell.innerHTML = currentTool === 1 ? '<i class="fa-solid fa-check"></i>' : (currentTool === 0 ? '<i class="fa-solid fa-xmark"></i>' : '');

    // Update Dataset
    el.dataset.val = (currentTool === null) ? "" : currentTool;

    // Recalculate Row Sum (รวมคะแนนแถวแนวนอน)
    let row = el.parentElement;
    let sumSpan = row.querySelector('.row-sum');
    let sum = parseInt(sumSpan.innerText) || 0;
    if (oldVal === 1) sum--;
    if (currentTool === 1) sum++;
    sumSpan.innerText = sum;

    // Update Grand Total (รวมคะแนนทั้งหมด)
    let gt = document.getElementById('grandTotal');
    let g = parseInt(gt.innerText.replace(/,/g, '')) || 0;
    if (oldVal === 1) g--;
    if (currentTool === 1) g++;
    gt.innerText = g.toLocaleString();

    // AJAX: ส่งข้อมูลไปบันทึกที่ Database
    // ใช้ตัวแปร Global: APP_CONFIG (ที่ส่งมาจาก PHP)
    // ⚠️ เช็ค path: ไฟล์ update.php อยู่ที่ ../Backup_log/update.php
    fetch('../Server_log/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            table: APP_CONFIG.tableType, // ใช้ค่าจาก Config
            name: el.dataset.sys,
            column: el.dataset.col,
            value: currentTool,
            month: APP_CONFIG.curM, // ใช้ค่าจาก Config
            year: APP_CONFIG.curY   // ใช้ค่าจาก Config
        })
    }).then(r => r.json()).then(d => {
        if (d.success && d.time) document.getElementById('lastUpd').innerText = d.time;
    }).catch(err => console.error('Error updating:', err));
}

// ฟังก์ชัน Fill Column (ดับเบิ้ลคลิกหัวตาราง)
function fillCol(c) {
    if (currentTool === 'safe') return alert('Please select a tool first (Check/Cross/Eraser)!');
    if (confirm('Apply to ALL rows in this column?')) {
        document.querySelectorAll(`td[data-col="${c}"]`).forEach(el => updateCell(el));
    }
}

// --- Event Listeners (ทำงานเมื่อโหลดหน้าเว็บเสร็จ) ---
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('tbl');

    // Mouse Down: เริ่มลาก
    table.addEventListener('mousedown', e => {
        if (currentTool !== 'safe' && e.target.closest('.c-wrap')) {
            e.preventDefault();
            isMouseDown = true;
            updateCell(e.target.closest('.c-wrap'));
        }
    });

    // Mouse Over: ลากผ่านเซลล์อื่น
    table.addEventListener('mouseover', e => {
        if (isMouseDown && currentTool !== 'safe' && e.target.closest('.c-wrap')) {
            updateCell(e.target.closest('.c-wrap'));
        }
    });

    // Mouse Up: หยุดลาก
    document.addEventListener('mouseup', () => isMouseDown = false);
});