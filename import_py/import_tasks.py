import mysql.connector
import csv
import sys
import re

db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'pm_system'
}

TABLE_MAP = {
    'server': 'server_logs',
    'network': 'network_logs',
    'hardsoft': 'hardsoft_logs',
    'hardware': 'hardsoft_logs', # Map ลง hardsoft
    'software': 'hardsoft_logs'  # Map ลง hardsoft
}

def get_next_column(cursor, system_type):
    # หา task_X ตัวล่าสุด
    search_sys = 'hardsoft' if system_type in ['hardware', 'software'] else system_type
    sql = f"SELECT column_name FROM master_tasks WHERE system_type='{search_sys}' ORDER BY id DESC LIMIT 1"
    cursor.execute(sql)
    result = cursor.fetchone()
    
    if result:
        last_col = result[0]
        match = re.search(r'task_(\d+)', last_col)
        if match:
            return f"task_{int(match.group(1)) + 1}"
    return "task_1"

def import_tasks(csv_file):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        
        with open(csv_file, mode='r', encoding='utf-8') as file:
            reader = csv.DictReader(file)
            
            for row in reader:
                raw_sys = row['system_type'].strip() # server, network, hardware, software
                label = row['task_label'].strip()
                freq = row['frequency'].strip() # M, 3M, 6M, Y
                
                # จัดการ Logic Hard/Soft
                if raw_sys in ['hardware', 'software']:
                    sys_db = 'hardsoft'
                    category = raw_sys # hardware or software
                else:
                    sys_db = raw_sys
                    category = None
                
                table_name = TABLE_MAP.get(raw_sys)
                
                if not table_name:
                    print(f"❌ Unknown system: {raw_sys}")
                    continue

                # 1. หาชื่อคอลัมน์ใหม่ (เช่น task_5)
                new_col = get_next_column(cursor, raw_sys)
                
                # 2. Insert ลง master_tasks
                insert_sql = "INSERT INTO master_tasks (system_type, category, column_name, task_label, frequency) VALUES (%s, %s, %s, %s, %s)"
                cursor.execute(insert_sql, (sys_db, category, new_col, label, freq))
                
                # 3. Alter Table เพิ่มคอลัมน์จริง
                alter_sql = f"ALTER TABLE {table_name} ADD COLUMN {new_col} TINYINT(1) DEFAULT NULL COMMENT '{label}'"
                try:
                    cursor.execute(alter_sql)
                    conn.commit()
                    print(f"✅ Added Task: {label} -> {new_col} (in {table_name})")
                except mysql.connector.Error as err:
                    print(f"⚠️ Alter Error (Column might exist): {err}")

    except Exception as e:
        print(f"❌ Error: {e}")
    finally:
        if conn.is_connected():
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) > 1:
        import_tasks(sys.argv[1])
    else:
        print("Usage: python import_tasks.py <tasks.csv>")