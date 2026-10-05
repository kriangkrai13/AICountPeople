USE ai_count_people;

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
  ('Administrator', 'admin@example.com', '$2y$12$/8ppebddgbqDNv.JLT6EOergKshSCYaVv22tiuOHaDBNNfVaxECxa', 'admin', 1);

INSERT INTO rooms (name, code, description, is_active)
VALUES
  ('Meeting Room', 'MEETING_ROOM', 'General meeting room', 1),
  ('Training Room', 'TRAINING_ROOM', 'General training room', 1),
  ('Laboratory Room', 'LAB_ROOM', 'General laboratory room', 1);
