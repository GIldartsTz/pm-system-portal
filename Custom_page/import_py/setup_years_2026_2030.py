import pandas as pd
from sqlalchemy import create_engine, text

# --- Config ---
DB_USER = 'root'
DB_PASS = ''
DB_HOST = 'localhost'
DB_NAME = 'monitor_db'

YEARS_TO_ADD = [2027, 2028, 2029, 2030]
TABLES = [
    'server_logs', 
    'network_logs', 
    'hardsoft_logs', 
    'backup_logs'
]

def extend_years():
    print(f"--- ขยายฐานข้อมูลปี {YEARS_TO_ADD} ---")
    conn_str = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}/{DB_NAME}"
    engine = create_engine(conn_str)
    
    with engine.connect() as conn:
        for table in TABLES:
            print(f" >> กำลังประมวลผลตาราง: {table}")
            
            # 1. ดึงรายชื่ออุปกรณ์ที่มีอยู่แล้ว (จากปี 2026)
            sql_get_equip = f"SELECT DISTINCT equipment_name FROM {table}"
            equip_list = pd.read_sql(sql_get_equip, conn)['equipment_name'].tolist()
            
            print(f"    พบอุปกรณ์ {len(equip_list)} รายการ")
            
            # 2. เตรียมข้อมูลปีใหม่
            new_data = []
            for year in YEARS_TO_ADD:
                for eq in equip_list:
                    # เช็คก่อนว่ามีข้อมูลปีนี้หรือยัง (กันซ้ำ)
                    check_sql = text(f"SELECT id FROM {table} WHERE equipment_name=:eq AND year=:yr LIMIT 1")
                    exists = conn.execute(check_sql, {"eq": eq, "yr": year}).fetchone()
                    
                    if not exists:
                        for m in range(1, 13):
                            row = { 'equipment_name': eq, 'month': m, 'year': year }
                            
                            # ถ้าเป็น backup ต้องมี day_1 ถึง day_31 + 6M
                            if table == 'backup_logs':
                                for d in range(1, 32): row[f'day_{d}'] = None
                                row['check_backup_6m'] = None
                                row['check_recovery_6m'] = None
                            
                            # ถ้าเป็นตารางอื่น ต้องมี task_1 ถึง task_11
                            else:
                                for t in range(1, 12): row[f'task_{t}'] = None
                                
                            new_data.append(row)
            
            # 3. บันทึกลง DB
            if new_data:
                pd.DataFrame(new_data).to_sql(table, con=engine, if_exists='append', index=False)
                print(f" เพิ่มข้อมูลใหม่ {len(new_data)} แถว")
            else:
                print(f" ไม่มีข้อมูลใหม่   ")

    print("\n--- complete---")

if __name__ == "__main__":
    extend_years()