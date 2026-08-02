CREATE TABLE IF NOT EXISTS uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_path TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending', -- pending | processing | processed | failed
    received_at TEXT NOT NULL,
    processed_at TEXT,
    processed_lines INTEGER,
    discarded_lines INTEGER
);

CREATE TABLE IF NOT EXISTS outbox_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    aggregate_type TEXT NOT NULL,       
    aggregate_id INTEGER NOT NULL,      
    event_type TEXT NOT NULL,           
    payload TEXT NOT NULL,              
    created_at TEXT NOT NULL,
    published_at TEXT                   
);

CREATE INDEX IF NOT EXISTS idx_outbox_unpublished ON outbox_events (published_at);
