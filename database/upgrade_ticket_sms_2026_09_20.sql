-- Run after the arrival check-in upgrade. Each ticket/event is claimed once.
CREATE TABLE IF NOT EXISTS ticket_sms_events (
  ticket_id INT NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  status ENUM('pending','sent','failed','simulated') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id, event_type),
  FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id) ON DELETE CASCADE
) ENGINE=InnoDB;
