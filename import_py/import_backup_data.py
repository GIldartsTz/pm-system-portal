import mysql.connector
import csv
import sys

db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'pm_system'
}

def import_backup_logs(csv_file):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        
        with open(csv_file, mode='r', encoding='utf-8') as file:
            reader = csv.DictReader(file)
            
            for row in reader:
                eq_name = row['equipment_name']
                year = row['year']
                month = row['month']
                
                # เตรียมข้อมูลวัน (day_1 ถึง day_31)
                updates = []
                values = []
                
                for d in range(1, 32):
                    day_key = f"day_{d}"
                    if day_key in row and row[day_key] != '':
                        updates.append(f"{day_key} = %s")
                        values.append(row[day_key])
                
                if not updates:
                    continue
                    
                # สร้าง SQL Update
                sql = f"UPDATE backup_logs SET {', '.join(updates)} WHERE equipment_name=%s AND year=%s AND month=%s"
                values.extend([eq_name, year, month])
                
                cursor.execute(sql, tuple(values))
                print(f"Updated {eq_name} - {month}/{year}")
                
            conn.commit()
            print("✅ Backup Logs Imported!")

    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        import_backup_logs(sys.argv[1])
    else:
        print("Usage: python import_backup_data.py <backup_data.csv>")