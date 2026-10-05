# AICountPeople

ระบบนับคนเข้าออกห้องด้วย Python + OpenCV และหลังบ้าน CodeIgniter 4 สำหรับดูสถิติ/จัดการห้อง

## โครงสร้าง

- `python/` โปรแกรม GUI สำหรับเลือกกล้อง เลือกห้อง เลือกด้านทางเข้า และนับคนเข้าออก
- `web/` Backoffice CodeIgniter 4 + Tailwind CDN + Vue CDN + SweetAlert2
- `database/` schema และ seed data สำหรับ MySQL

## Database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

บัญชีหลังบ้านตัวอย่าง:

- Email: `admin@example.com`
- Password: `admin1234`

## Web

```bash
cd web
cp env.example .env
composer install
php spark serve
```

เปิดเว็บที่ `http://localhost:8080`

## Docker

ไฟล์ Docker อยู่ที่ root โปรเจค และตั้งชื่อ Docker Compose project เป็น `AI_Count_People`

```bash
docker compose up -d --build mysql web
```

เปิดเว็บที่ `http://localhost:8080`

คำสั่งที่ใช้บ่อย:

```bash
docker compose logs -f web
docker compose logs -f mysql
docker compose down
docker compose down -v
```

หมายเหตุ: `docker compose down -v` จะลบข้อมูล MySQL volume ด้วย

ถ้าต้องการลองรัน Python ผ่าน Docker:

```bash
docker compose --profile python up --build python
```

การใช้กล้องและ GUI ผ่าน Docker ขึ้นกับ OS/driver ของเครื่องจริง บน macOS แนะนำให้รัน Python ตรงบนเครื่อง host จะเสถียรกว่า ส่วน Docker เหมาะกับ MySQL และเว็บหลังบ้าน

## Python

```bash
cd python
cp config.example.json config.json
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python main.py
```

ค่า `detector` ใน `python/config.json` เลือกได้:

- `hog` ใช้ OpenCV HOG detector ไม่ต้องโหลดโมเดลเพิ่ม เหมาะสำหรับเริ่มทดสอบ
- `yolo` ใช้ YOLO ผ่าน `ultralytics` พร้อม ByteTrack แม่นกว่า โดยเฉพาะคนหันหลัง/ด้านข้าง แต่ครั้งแรกอาจดาวน์โหลดโมเดล `yolov8n.pt`

ค่า default ตอนนี้ใช้:

```json
"detector": "yolo",
"yolo_tracker": "bytetrack.yaml",
"detection_confidence": 0.35,
"left_line_position_ratio": 0.35,
"right_line_position_ratio": 0.75
```

ถ้าต้องการจำกัดพื้นที่นับเฉพาะบริเวณประตู ให้เปิด `roi.enabled` และปรับค่า `x1`, `y1`, `x2`, `y2` เป็นสัดส่วนของภาพ เช่น `0.0` ถึง `1.0`

## แนวคิดการนับ

โปรแกรมใช้เส้นแนวตั้ง 2 เส้น แทนการใช้เส้นกลางเส้นเดียว คนจะถูกนับเมื่อ track เดินผ่านเส้นทั้งสองตามลำดับ:

- ผ่านเส้นซ้าย แล้วผ่านเส้นขวา = เคลื่อนที่ไปทางขวา
- ผ่านเส้นขวา แล้วผ่านเส้นซ้าย = เคลื่อนที่ไปทางซ้าย

ถ้าเลือก `right` เป็นทางเข้า การเคลื่อนที่ไปทางขวาจะนับเป็น `เข้า` และทางซ้ายเป็น `ออก` ถ้าเลือก `left` จะกลับทิศทางกัน

โปรแกรมจะอัปเดตตาราง `daily_room_counts` ทุก 30 วินาที และเก็บประวัติการ sync ไว้ที่ `count_sync_logs`
