
CREATE TABLE IF NOT EXISTS acceptance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    responder_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_id) REFERENCES users(id) ON DELETE CASCADE
);


ALTER TABLE incidents ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL;


ALTER TABLE incidents ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL;


ALTER TABLE incidents MODIFY COLUMN status ENUM('pending', 'accepted', 'done', 'resolved', 'completed', 'accept and complete') DEFAULT 'pending';


CREATE INDEX IF NOT EXISTS idx_acceptance_log_incident ON acceptance_log(incident_id);
CREATE INDEX IF NOT EXISTS idx_acceptance_log_responder ON acceptance_log(responder_id);
CREATE INDEX IF NOT EXISTS idx_incidents_accepted_at ON incidents(accepted_at);
CREATE INDEX IF NOT EXISTS idx_incidents_completed_at ON incidents(completed_at);
