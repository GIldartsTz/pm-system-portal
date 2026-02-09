import mysql.connector
import csv
import sys

# ⚙️ CONFIG DATABASE
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'pm_system'
}

def import_equipment(csv_file):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        
        print(f"📂 Reading {csv_file}...")
        
        with open(csv_file, mode='r', encoding='utf-8') as file:
            reader = csv.DictReader(file)
            count = 0
            for row in reader:
                sys_type = row['system_type'].strip() # server, network, backup, hardsoft
                eq_name = row['equipment_name'].strip()
                
                # เช็คก่อนว่ามีหรือยัง (กันซ้ำ)
                check_sql = "SELECT id FROM master_equipment WHERE equipment_name = %s AND system_type = %s"
                cursor.execute(check_sql, (eq_name, sys_type))
                
                if cursor.fetchone() is None:
                    insert_sql = "INSERT INTO master_equipment (system_type, equipment_name) VALUES (%s, %s)"
                    cursor.execute(insert_sql, (sys_type, eq_name))
                    print(f"✅ Added: {eq_name} ({sys_type})")
                    count += 1
                else:
                    print(f"⚠️ Skipped (Exists): {eq_name}")
            
            conn.commit()
            print(f"\n🎉 Import Completed! Total added: {count}")

    except Exception as e:
        print(f"❌ Error: {e}")
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    # วิธีใช้: python import_equipment.py my_equipment_list.csv
    if len(sys.argv) > 1:
        import_equipment(sys.argv[1])
    else:
        print("Usage: python import_equipment.py <your_csv_file.csv>")