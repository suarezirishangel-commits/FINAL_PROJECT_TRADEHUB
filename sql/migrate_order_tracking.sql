-- Order tracking: per-order status timeline and courier tracking number.
ALTER TABLE transactions
  ADD COLUMN tracking_number VARCHAR(64) DEFAULT NULL AFTER status,
  ADD COLUMN paid_at DATETIME DEFAULT NULL AFTER tracking_number,
  ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER paid_at,
  ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER completed_at;

-- Backfill timestamps for orders that already reached a status.
UPDATE transactions SET paid_at = created_at WHERE status IN ('paid', 'completed') AND paid_at IS NULL;
UPDATE transactions SET completed_at = created_at WHERE status = 'completed' AND completed_at IS NULL;
UPDATE transactions SET cancelled_at = created_at WHERE status = 'cancelled' AND cancelled_at IS NULL;
