
    function handleAction(type, table, is_custom, id_val, year_val) {
        const actionNames = {
            'submit': 'Submit เอกสาร',
            'approve': 'Approve เอกสาร',
            'cancel_submit': 'ยกเลิกการ Submit',
            'cancel_approve': 'ยกเลิกการ Approve'
        };

        if(!confirm(`ยืนยันการทำรายการ: ${actionNames[type]} ใช่หรือไม่?`)) return;
        
        let payload = { type: type, table: table, is_custom: is_custom };
        if(is_custom === 1) {
            payload.page_id = id_val;
        } else {
            payload.month = id_val;
            payload.year = year_val;
        }

        fetch('update_workflow.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if(d.success) location.reload();
            else alert('Error: ' + d.error);
        })
        .catch(err => {
            console.error('Fetch Error:', err);
            alert('ไม่สามารถติดต่อไฟล์ update_workflow.php ได้ครับ');
        });
    }
    