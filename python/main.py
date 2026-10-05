import json
import logging
import math
import os
import queue
import threading
import time
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from tkinter import BOTH, LEFT, RIGHT, StringVar, Tk, ttk

import cv2
import mysql.connector
from PIL import Image, ImageTk


BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.json"
LOG_PATH = BASE_DIR / "app.log"
os.environ.setdefault("YOLO_CONFIG_DIR", str(BASE_DIR / ".ultralytics"))

logging.basicConfig(
    filename=LOG_PATH,
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(threadName)s %(message)s",
)


def load_config() -> dict:
    path = CONFIG_PATH if CONFIG_PATH.exists() else BASE_DIR / "config.example.json"
    with path.open("r", encoding="utf-8") as file:
        config = json.load(file)

    mysql_config = config.setdefault("mysql", {})
    env_map = {
        "host": "AICOUNT_DB_HOST",
        "port": "AICOUNT_DB_PORT",
        "database": "AICOUNT_DB_DATABASE",
        "user": "AICOUNT_DB_USER",
        "password": "AICOUNT_DB_PASSWORD",
    }
    for key, env_name in env_map.items():
        value = os.getenv(env_name)
        if value:
            mysql_config[key] = int(value) if key == "port" else value

    return config


class Database:
    def __init__(self, config: dict):
        self.config = config["mysql"]

    def connect(self):
        return mysql.connector.connect(**self.config)

    def get_rooms(self) -> list[dict]:
        with self.connect() as conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT id, name, code FROM rooms WHERE is_active = 1 ORDER BY name ASC")
            return cursor.fetchall()

    def sync_counts(self, payload: dict) -> None:
        sql_select = "SELECT id FROM daily_room_counts WHERE room_id = %s AND count_date = %s"
        sql_insert = """
            INSERT INTO daily_room_counts
            (room_id, count_date, total_in, total_out, last_recorded_at, camera_name, entrance_side)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        sql_update = """
            UPDATE daily_room_counts
            SET total_in = %s, total_out = %s, last_recorded_at = %s, camera_name = %s, entrance_side = %s
            WHERE id = %s
        """
        sql_log = """
            INSERT INTO count_sync_logs
            (room_id, count_date, total_in, total_out, payload)
            VALUES (%s, %s, %s, %s, %s)
        """
        with self.connect() as conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute(sql_select, (payload["room_id"], payload["count_date"]))
            existing = cursor.fetchone()
            if existing:
                cursor.execute(sql_update, (
                    payload["total_in"],
                    payload["total_out"],
                    payload["last_recorded_at"],
                    payload["camera_name"],
                    payload["entrance_side"],
                    existing["id"],
                ))
            else:
                cursor.execute(sql_insert, (
                    payload["room_id"],
                    payload["count_date"],
                    payload["total_in"],
                    payload["total_out"],
                    payload["last_recorded_at"],
                    payload["camera_name"],
                    payload["entrance_side"],
                ))
            cursor.execute(sql_log, (
                payload["room_id"],
                payload["count_date"],
                payload["total_in"],
                payload["total_out"],
                json.dumps(payload, ensure_ascii=False),
            ))
            conn.commit()


class PersonDetector:
    def __init__(self, config: dict):
        self.mode = config.get("detector", "yolo")
        self.yolo = None
        self.confidence = float(config.get("detection_confidence", 0.35))
        self.iou = float(config.get("detection_iou", 0.45))
        self.tracker_config = config.get("yolo_tracker", "bytetrack.yaml")
        if self.mode == "yolo":
            try:
                from ultralytics import YOLO
                self.yolo = YOLO(config.get("yolo_model", "yolov8n.pt"))
            except Exception:
                logging.exception("YOLO failed to initialize, falling back to HOG")
                self.mode = "hog"
        self.hog = cv2.HOGDescriptor()
        self.hog.setSVMDetector(cv2.HOGDescriptor_getDefaultPeopleDetector())

    def detect(self, frame):
        if self.mode == "yolo" and self.yolo:
            result = self.yolo.track(
                frame,
                persist=True,
                tracker=self.tracker_config,
                classes=[0],
                conf=self.confidence,
                iou=self.iou,
                verbose=False,
            )[0]
            detections = []
            for box in result.boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
                conf = float(box.conf[0])
                track_id = None
                if box.id is not None:
                    track_id = int(box.id[0])
                if conf >= self.confidence:
                    detections.append(Detection(x1, y1, x2 - x1, y2 - y1, conf, track_id))
            return detections

        resized = cv2.resize(frame, (min(frame.shape[1], 900), int(frame.shape[0] * min(frame.shape[1], 900) / frame.shape[1])))
        scale_x = frame.shape[1] / resized.shape[1]
        scale_y = frame.shape[0] / resized.shape[0]
        boxes, _ = self.hog.detectMultiScale(resized, winStride=(8, 8), padding=(8, 8), scale=1.05)
        return [
            Detection(int(x * scale_x), int(y * scale_y), int(w * scale_x), int(h * scale_y), 1.0, None)
            for x, y, w, h in boxes
        ]


@dataclass
class Detection:
    x: int
    y: int
    w: int
    h: int
    confidence: float
    track_id: int | None = None

    @property
    def box(self) -> tuple[int, int, int, int]:
        return self.x, self.y, self.w, self.h

    @property
    def center(self) -> tuple[int, int]:
        return self.x + self.w // 2, self.y + self.h // 2


@dataclass
class Track:
    track_id: int
    center: tuple[int, int]
    first_x: int
    last_x: int
    ttl: int
    counted: bool = False
    history: list[tuple[int, int]] = field(default_factory=list)
    line_events: list[str] = field(default_factory=list)


class CentroidTracker:
    def __init__(self, max_distance: int, ttl_frames: int):
        self.max_distance = max_distance
        self.ttl_frames = ttl_frames
        self.next_id = 1
        self.tracks: dict[int, Track] = {}

    def update(self, detections: list[Detection]) -> list[Track]:
        centers = [detection.center for detection in detections]
        unmatched = set(range(len(centers)))

        for track in self.tracks.values():
            track.ttl -= 1
            best_index = None
            best_distance = self.max_distance
            for index in list(unmatched):
                dist = math.dist(track.center, centers[index])
                if dist < best_distance:
                    best_index = index
                    best_distance = dist
            if best_index is not None:
                center = centers[best_index]
                track.center = center
                track.last_x = center[0]
                track.history.append(center)
                track.ttl = self.ttl_frames
                unmatched.remove(best_index)

        for index in unmatched:
            center = centers[index]
            self.tracks[self.next_id] = Track(
                track_id=self.next_id,
                center=center,
                first_x=center[0],
                last_x=center[0],
                ttl=self.ttl_frames,
                history=[center],
            )
            self.next_id += 1

        expired = [track_id for track_id, track in self.tracks.items() if track.ttl <= 0]
        for track_id in expired:
            del self.tracks[track_id]

        return list(self.tracks.values())


class CounterApp:
    def __init__(self):
        self.config = load_config()
        self.db = Database(self.config)
        self.detector = PersonDetector(self.config)
        self.tracker = CentroidTracker(
            self.config.get("max_tracking_distance", 90),
            self.config.get("track_ttl_frames", 18),
        )
        self.external_tracks: dict[int, Track] = {}
        self.root = Tk()
        self.root.title("AI Count People")
        self.root.geometry("1120x760")

        self.frame_queue = queue.Queue(maxsize=2)
        self.status_queue = queue.Queue()
        self.stop_event = threading.Event()
        self.capture_thread = None
        self.cap = None
        self.initial_status = "พร้อมทำงาน"
        self.is_counting = False
        self.active_camera_index = None

        self.cameras = self.scan_cameras()
        self.rooms = self.safe_rooms()
        self.camera_var = StringVar(value=self.cameras[0][0] if self.cameras else "")
        self.room_var = StringVar(value=self.room_display(self.rooms[0]) if self.rooms else "")
        self.entrance_var = StringVar(value="right")
        self.status_var = StringVar(value=self.initial_status)
        self.camera_status_var = StringVar(value="ยังไม่ได้เลือกกล้อง")
        self.total_in = 0
        self.total_out = 0
        self.last_sync = 0.0
        self.camera_name = ""
        self.current_room = None

        self.build_ui()
        self.root.protocol("WM_DELETE_WINDOW", self.close)
        self.root.after(30, self.render_frame)
        if self.cameras:
            self.root.after(150, self.preview_selected_camera)
        else:
            self.camera_status_var.set("ไม่พบกล้อง")

    def scan_cameras(self):
        cameras = []
        for index in range(self.config.get("camera_scan_limit", 6)):
            cap = self.open_camera(index)
            if cap.isOpened():
                ok, _frame = cap.read()
                if ok:
                    cameras.append((str(index), f"Camera {index}"))
            cap.release()
        return cameras

    @staticmethod
    def open_camera(index: int):
        if os.name == "posix" and os.uname().sysname == "Darwin":
            return cv2.VideoCapture(index, cv2.CAP_AVFOUNDATION)

        return cv2.VideoCapture(index)

    def safe_rooms(self):
        try:
            return self.db.get_rooms()
        except Exception as exc:
            self.initial_status = f"เชื่อมต่อฐานข้อมูลไม่ได้: {exc}"
            return []

    @staticmethod
    def room_display(room: dict) -> str:
        return f'{room["id"]}: {room["name"]}'

    def build_ui(self):
        shell = ttk.Frame(self.root, padding=12)
        shell.pack(fill=BOTH, expand=True)

        toolbar = ttk.Frame(shell)
        toolbar.pack(fill="x")

        ttk.Label(toolbar, text="กล้อง").pack(side=LEFT, padx=(0, 6))
        self.camera_combo = ttk.Combobox(toolbar, textvariable=self.camera_var, values=[camera[0] for camera in self.cameras], width=10, state="readonly")
        self.camera_combo.bind("<<ComboboxSelected>>", self.on_camera_selected)
        self.camera_combo.pack(side=LEFT, padx=(0, 16))

        ttk.Label(toolbar, text="ห้อง").pack(side=LEFT, padx=(0, 6))
        room_values = [self.room_display(room) for room in self.rooms]
        self.room_combo = ttk.Combobox(toolbar, textvariable=self.room_var, values=room_values, width=34, state="readonly")
        if room_values:
            self.room_combo.current(0)
        self.room_combo.pack(side=LEFT, padx=(0, 16))

        ttk.Label(toolbar, text="ทางเข้าอยู่ด้าน").pack(side=LEFT, padx=(0, 6))
        ttk.Combobox(toolbar, textvariable=self.entrance_var, values=["left", "right"], width=10, state="readonly").pack(side=LEFT, padx=(0, 16))

        ttk.Button(toolbar, text="เริ่ม", command=self.start).pack(side=LEFT, padx=(0, 8))
        ttk.Button(toolbar, text="หยุด", command=self.stop).pack(side=LEFT)

        stats = ttk.Frame(shell, padding=(0, 12))
        stats.pack(fill="x")
        self.room_label = ttk.Label(stats, text="ห้อง: -", font=("Arial", 16, "bold"))
        self.room_label.pack(side=LEFT, padx=(0, 24))
        self.in_label = ttk.Label(stats, text="เข้า: 0", font=("Arial", 16, "bold"))
        self.in_label.pack(side=LEFT, padx=(0, 24))
        self.out_label = ttk.Label(stats, text="ออก: 0", font=("Arial", 16, "bold"))
        self.out_label.pack(side=LEFT, padx=(0, 24))
        ttk.Label(stats, textvariable=self.camera_status_var, font=("Arial", 13, "bold")).pack(side=LEFT, padx=(0, 24))
        ttk.Label(stats, textvariable=self.status_var).pack(side=RIGHT)

        self.video_label = ttk.Label(shell)
        self.video_label.pack(fill=BOTH, expand=True)

    def on_camera_selected(self, _event=None):
        self.preview_selected_camera()

    def preview_selected_camera(self):
        if not self.camera_var.get():
            self.camera_status_var.set("ไม่พบกล้อง")
            self.status_var.set("เลือกกล้องไม่ได้")
            return

        self.start_camera(counting=False)

    def start(self):
        if not self.cameras or not self.rooms:
            self.status_var.set("ไม่พบกล้องหรือไม่พบห้องที่เปิดใช้งาน")
            return

        room_text = self.room_combo.get()
        room_id = int(room_text.split(":", 1)[0])
        self.current_room = next(room for room in self.rooms if int(room["id"]) == room_id)
        self.camera_name = f"Camera {self.camera_var.get()}"
        self.total_in = 0
        self.total_out = 0
        self.last_sync = 0.0
        self.tracker = CentroidTracker(
            self.config.get("max_tracking_distance", 90),
            self.config.get("track_ttl_frames", 18),
        )
        self.external_tracks = {}
        self.room_label.config(text=f'ห้อง: {self.current_room["name"]}')
        self.start_camera(counting=True)

    def start_camera(self, counting: bool):
        camera_index = int(self.camera_var.get())

        if self.capture_thread and self.capture_thread.is_alive() and self.active_camera_index == camera_index:
            self.is_counting = counting
            self.camera_status_var.set(f"เชื่อมต่อกล้อง Camera {camera_index} แล้ว")
            self.status_var.set("กำลังนับคนเข้าออก" if counting else "กำลัง preview กล้อง")
            return

        self.stop_camera_thread()
        self.clear_frame_queue()
        self.stop_event.clear()
        self.is_counting = counting
        self.active_camera_index = camera_index
        self.cap = self.open_camera(camera_index)

        if not self.cap.isOpened():
            self.cap.release()
            self.cap = None
            self.active_camera_index = None
            self.camera_status_var.set(f"เชื่อมต่อกล้อง Camera {camera_index} ไม่สำเร็จ")
            self.status_var.set("เปิดกล้องไม่ได้ กรุณาเลือกกล้องอื่นหรือตรวจสิทธิ์กล้อง")
            return

        self.camera_status_var.set(f"เชื่อมต่อกล้อง Camera {camera_index} แล้ว")
        self.status_var.set("กำลังนับคนเข้าออก" if counting else "กำลัง preview กล้อง")
        self.capture_thread = threading.Thread(target=self.capture_loop, daemon=True)
        self.capture_thread.start()

    def stop(self):
        self.sync_now()
        self.is_counting = False
        self.current_room = None
        self.room_label.config(text="ห้อง: -")
        if self.active_camera_index is not None:
            self.status_var.set("หยุดนับ - กล้องยัง preview อยู่")
        else:
            self.status_var.set("หยุดทำงาน")

    def stop_camera_thread(self):
        self.stop_event.set()
        if self.capture_thread and self.capture_thread.is_alive():
            self.capture_thread.join(timeout=1.0)
        if self.cap:
            self.cap.release()
            self.cap = None
        self.capture_thread = None
        self.active_camera_index = None

    def clear_frame_queue(self):
        while not self.frame_queue.empty():
            try:
                self.frame_queue.get_nowait()
            except queue.Empty:
                break

    def capture_loop(self):
        cap = self.cap
        try:
            while not self.stop_event.is_set() and cap and cap.isOpened():
                ok, frame = cap.read()
                if not ok:
                    self.queue_status("อ่านภาพจากกล้องไม่ได้")
                    self.queue_camera_status("กล้องขาดการเชื่อมต่อ")
                    break

                try:
                    if self.is_counting:
                        frame = self.process_frame(frame)
                        self.sync_if_needed()
                    else:
                        frame = self.draw_preview_frame(frame)
                except Exception:
                    logging.exception("Frame processing failed")
                    self.queue_status("ประมวลผลภาพไม่สำเร็จ ดูรายละเอียดใน app.log")
                    frame = self.draw_preview_frame(frame)

                self.enqueue_frame(frame)
        except Exception:
            logging.exception("Camera loop crashed")
            self.queue_status("กล้องหยุดทำงานผิดปกติ ดูรายละเอียดใน app.log")
            self.queue_camera_status("กล้องหยุดทำงาน")
        finally:
            if cap:
                cap.release()

    def draw_preview_frame(self, frame):
        self.draw_counter_overlay(frame, boxes_count=0, tracks_count=0)
        return frame

    def draw_counter_overlay(self, frame, boxes_count: int, tracks_count: int):
        height, width = frame.shape[:2]
        left_x = int(width * self.config.get("left_line_position_ratio", 0.35))
        right_x = int(width * self.config.get("right_line_position_ratio", 0.75))
        roi = self.roi_rect(width, height)
        people_in_room = max(0, self.total_in - self.total_out)

        overlay = frame.copy()
        if roi:
            x1, y1, x2, y2 = roi
            cv2.rectangle(overlay, (0, 0), (width, y1), (0, 0, 0), -1)
            cv2.rectangle(overlay, (0, y2), (width, height), (0, 0, 0), -1)
            cv2.rectangle(overlay, (0, y1), (x1, y2), (0, 0, 0), -1)
            cv2.rectangle(overlay, (x2, y1), (width, y2), (0, 0, 0), -1)
            cv2.addWeighted(overlay, 0.32, frame, 0.68, 0, frame)
            cv2.rectangle(frame, (x1, y1), (x2, y2), (255, 255, 255), 1)

        overlay = frame.copy()
        cv2.rectangle(overlay, (0, 0), (width, 96), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.45, frame, 0.55, 0, frame)
        cv2.line(frame, (left_x, 0), (left_x, height), (0, 90, 255), 3)
        cv2.line(frame, (right_x, 0), (right_x, height), (0, 220, 255), 3)

        if self.entrance_var.get() == "right":
            left_label = "OUT"
            right_label = "IN"
        else:
            left_label = "IN"
            right_label = "OUT"

        detector_name = self.detector.mode.upper()
        cv2.putText(frame, f"People in room: {people_in_room}", (18, 36), cv2.FONT_HERSHEY_SIMPLEX, 0.95, (255, 255, 255), 3)
        cv2.putText(frame, f"{detector_name}  tracks: {tracks_count}  boxes: {boxes_count}", (18, 78), cv2.FONT_HERSHEY_SIMPLEX, 0.72, (255, 255, 255), 2)
        cv2.putText(frame, left_label, (max(12, left_x - 78), 36), cv2.FONT_HERSHEY_SIMPLEX, 0.85, (0, 120, 255), 3)
        cv2.putText(frame, right_label, (min(width - 96, right_x + 12), 36), cv2.FONT_HERSHEY_SIMPLEX, 0.85, (0, 240, 255), 3)
        cv2.putText(frame, f"IN: {self.total_in}", (max(12, right_x + 12), 78), cv2.FONT_HERSHEY_SIMPLEX, 0.68, (0, 240, 255), 2)
        cv2.putText(frame, f"OUT: {self.total_out}", (max(12, left_x - 110), 78), cv2.FONT_HERSHEY_SIMPLEX, 0.68, (0, 120, 255), 2)

    def roi_rect(self, width: int, height: int):
        roi = self.config.get("roi")
        if not roi or not roi.get("enabled", False):
            return None

        x1 = int(width * float(roi.get("x1", 0.0)))
        y1 = int(height * float(roi.get("y1", 0.0)))
        x2 = int(width * float(roi.get("x2", 1.0)))
        y2 = int(height * float(roi.get("y2", 1.0)))
        x1 = max(0, min(width - 1, x1))
        y1 = max(0, min(height - 1, y1))
        x2 = max(x1 + 1, min(width, x2))
        y2 = max(y1 + 1, min(height, y2))

        return x1, y1, x2, y2

    def filter_detections_by_roi(self, detections: list[Detection], frame):
        height, width = frame.shape[:2]
        roi = self.roi_rect(width, height)
        if not roi:
            return detections

        x1, y1, x2, y2 = roi
        filtered = []
        for detection in detections:
            cx, cy = detection.center
            if x1 <= cx <= x2 and y1 <= cy <= y2:
                filtered.append(detection)
        return filtered

    def enqueue_frame(self, frame):
        if self.frame_queue.full():
            try:
                self.frame_queue.get_nowait()
            except queue.Empty:
                pass
        self.frame_queue.put(frame)

    def process_frame(self, frame):
        detections = self.filter_detections_by_roi(self.detector.detect(frame), frame)
        height, width = frame.shape[:2]
        left_x = int(width * self.config.get("left_line_position_ratio", 0.35))
        right_x = int(width * self.config.get("right_line_position_ratio", 0.75))
        tracks = self.update_tracks(detections)

        for detection in detections:
            x, y, w, h = detection.box
            cv2.rectangle(frame, (x, y), (x + w, y + h), (30, 190, 255), 2)
            cv2.putText(frame, f"{detection.confidence:.2f}", (x, max(16, y - 6)), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (30, 190, 255), 1)

        for track in tracks:
            x, y = track.center
            cv2.circle(frame, (x, y), 5, (80, 255, 120), -1)
            cv2.putText(frame, str(track.track_id), (x + 8, y - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (80, 255, 120), 1)
            if len(track.history) > 1:
                for start, end in zip(track.history[-12:-1], track.history[-11:]):
                    cv2.line(frame, start, end, (80, 255, 120), 2)
            self.count_crossing(track, left_x, right_x)

        self.draw_counter_overlay(frame, boxes_count=len(detections), tracks_count=len(tracks))
        return frame

    def update_tracks(self, detections: list[Detection]) -> list[Track]:
        if detections and all(detection.track_id is not None for detection in detections):
            return self.update_external_tracks(detections)

        return self.tracker.update(detections)

    def update_external_tracks(self, detections: list[Detection]) -> list[Track]:
        ttl_frames = self.config.get("track_ttl_frames", 18)
        seen_ids = set()
        for detection in detections:
            if detection.track_id is None:
                continue

            seen_ids.add(detection.track_id)
            center = detection.center
            track = self.external_tracks.get(detection.track_id)
            if track:
                track.center = center
                track.last_x = center[0]
                track.history.append(center)
                track.ttl = ttl_frames
            else:
                self.external_tracks[detection.track_id] = Track(
                    track_id=detection.track_id,
                    center=center,
                    first_x=center[0],
                    last_x=center[0],
                    ttl=ttl_frames,
                    history=[center],
                )

        expired = []
        for track_id, track in self.external_tracks.items():
            if track_id not in seen_ids:
                track.ttl -= 1
            if track.ttl <= 0:
                expired.append(track_id)

        for track_id in expired:
            del self.external_tracks[track_id]

        return list(self.external_tracks.values())

    def count_crossing(self, track: Track, left_x: int, right_x: int):
        if track.counted or len(track.history) < 2:
            return

        previous_x = track.history[-2][0]
        current_x = track.history[-1][0]
        if previous_x == current_x:
            return

        new_events = []
        if previous_x < current_x:
            if previous_x < left_x <= current_x:
                new_events.append("left")
            if previous_x < right_x <= current_x:
                new_events.append("right")
        else:
            if previous_x > right_x >= current_x:
                new_events.append("right")
            if previous_x > left_x >= current_x:
                new_events.append("left")

        for event in new_events:
            if not track.line_events or track.line_events[-1] != event:
                track.line_events.append(event)
        track.line_events = track.line_events[-4:]

        if len(track.line_events) < 2:
            return

        sequence = track.line_events[-2:]
        if sequence == ["left", "right"]:
            direction = "right"
        elif sequence == ["right", "left"]:
            direction = "left"
        else:
            return

        if direction == self.entrance_var.get():
            self.total_in += 1
        else:
            self.total_out += 1
        track.counted = True

    def sync_if_needed(self):
        interval = self.config.get("sync_interval_seconds", 30)
        if time.time() - self.last_sync < interval:
            return
        self.sync_now()

    def sync_now(self):
        if not self.current_room:
            return
        now = datetime.now()
        payload = {
            "room_id": int(self.current_room["id"]),
            "count_date": now.strftime("%Y-%m-%d"),
            "total_in": self.total_in,
            "total_out": self.total_out,
            "last_recorded_at": now.strftime("%Y-%m-%d %H:%M:%S"),
            "camera_name": self.camera_name,
            "entrance_side": self.entrance_var.get(),
        }
        try:
            self.db.sync_counts(payload)
            self.last_sync = time.time()
            self.queue_status(f'อัปเดตฐานข้อมูลล่าสุด {payload["last_recorded_at"]}')
        except Exception as exc:
            logging.exception("Database sync failed")
            self.queue_status(f"บันทึกฐานข้อมูลไม่สำเร็จ: {exc}")

    def set_status(self, text: str):
        self.status_var.set(text)

    def queue_status(self, text: str):
        self.status_queue.put(("status", text))

    def queue_camera_status(self, text: str):
        self.status_queue.put(("camera", text))

    def flush_status_queue(self):
        while not self.status_queue.empty():
            try:
                kind, text = self.status_queue.get_nowait()
            except queue.Empty:
                break
            if kind == "camera":
                self.camera_status_var.set(text)
            else:
                self.status_var.set(text)

    def render_frame(self):
        self.flush_status_queue()
        try:
            frame = self.frame_queue.get_nowait()
            frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            image = Image.fromarray(frame)
            image.thumbnail((1060, 620))
            photo = ImageTk.PhotoImage(image=image)
            self.video_label.configure(image=photo)
            self.video_label.image = photo
        except queue.Empty:
            pass
        self.in_label.config(text=f"เข้า: {self.total_in}")
        self.out_label.config(text=f"ออก: {self.total_out}")
        if self.root.winfo_exists():
            self.root.after(30, self.render_frame)

    def close(self):
        self.stop()
        self.stop_camera_thread()
        self.root.destroy()

    def run(self):
        self.root.mainloop()


if __name__ == "__main__":
    CounterApp().run()
