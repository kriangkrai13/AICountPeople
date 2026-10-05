# Design

## System

Backoffice งานปฏิบัติการสำหรับดูจำนวนคนเข้าออกห้องและจัดการรายการห้อง ใช้โทนน้ำเงินขาวแบบ restrained product UI มี dark-mode leaning สำหรับ sidebar/header แต่พื้นที่อ่านข้อมูลหลักเป็นพื้นสว่างเพื่อให้อ่านตัวเลขและตารางง่าย

## Color

ใช้ OKLCH tokens เป็นหลัก:

```css
:root {
  --bg: oklch(1 0 0);
  --surface: oklch(0.972 0.006 255);
  --panel: oklch(0.935 0.018 255);
  --ink: oklch(0.205 0.035 258);
  --muted: oklch(0.435 0.035 258);
  --primary: oklch(0.48 0.19 255);
  --primary-strong: oklch(0.38 0.17 255);
  --accent: oklch(0.62 0.16 205);
  --success: oklch(0.55 0.14 154);
  --warning: oklch(0.72 0.14 78);
  --danger: oklch(0.58 0.18 28);
  --border: oklch(0.88 0.015 255);
}
```

## Typography

ใช้ `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif` ทั้งระบบ ขนาดตัวอักษรคงที่แบบ product UI หัวข้อหน้า 24-30px, หัวข้อส่วน 16-20px, body 14-16px, table 13-14px

## Layout

Desktop ใช้ sidebar ซ้าย + top summary + content table/form พื้นที่หลักกว้างและอ่านง่าย Mobile ยุบ sidebar เป็นแถบเมนูบน ตารางใช้ horizontal overflow อย่างเป็นระบบ

## Components

ปุ่ม primary สีน้ำเงินตัวอักษรขาว, secondary พื้นขาวขอบเทา, danger สีแดง ใช้ radius 8px หรือน้อยกว่า การ์ดใช้เฉพาะกล่อง metric, form panel, และรายการซ้ำ ไม่ซ้อนการ์ดในการ์ด

## Motion

ใช้ transition 150-200ms สำหรับ hover, focus, modal, toast เท่านั้น ปิด animation ที่ไม่จำเป็นเมื่อ `prefers-reduced-motion: reduce`
