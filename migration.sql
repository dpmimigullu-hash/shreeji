-- Migration script to rename 'cars' table to 'vehicles'
-- Run this script to update the database schema

USE employee_transportation;

-- Rename the table
ALTER TABLE cars RENAME TO vehicles;

-- Update foreign key references in trips table
ALTER TABLE trips DROP FOREIGN KEY trips_ibfk_2;
ALTER TABLE trips ADD CONSTRAINT trips_ibfk_2 FOREIGN KEY (vehicle_id) REFERENCES vehicles(id);

-- Update foreign key references in users table (if exists)
-- Note: The users table might not have a direct foreign key to cars, but driver assignments are handled differently

-- Update any other references if needed
-- The billing table column name was already updated in the schema

-- Insert sample data with new table name (if needed)
-- The sample data insertion was already updated in database_schema.sql

SELECT 'Migration completed successfully' as status;