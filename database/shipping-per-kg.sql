-- Run once on an existing installation; existing prices remain flat.
ALTER TABLE shipping_rates ADD COLUMN rate_basis VARCHAR(10) NOT NULL DEFAULT 'flat';
