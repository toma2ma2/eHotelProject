CREATE OR REPLACE FUNCTION fn_renting_marks_booking_converted()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.booking_id IS NOT NULL THEN
        UPDATE booking
        SET status = 'converted'
        WHERE booking_id = NEW.booking_id AND status = 'active';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_renting_after_insert
AFTER INSERT ON renting
FOR EACH ROW EXECUTE PROCEDURE fn_renting_marks_booking_converted();

CREATE OR REPLACE FUNCTION fn_block_removing_last_manager()
RETURNS TRIGGER AS $$
DECLARE
    mgr_cnt INTEGER;
BEGIN
    IF OLD.is_manager IS TRUE THEN
        SELECT COUNT(*) INTO mgr_cnt
        FROM employee
        WHERE hotel_ID = OLD.hotel_ID AND is_manager IS TRUE AND employee_ID <> OLD.employee_ID;
        IF mgr_cnt = 0 THEN
            RAISE EXCEPTION 'cannot remove last manager for hotel_id %', OLD.hotel_ID;
        END IF;
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_employee_before_delete_manager
BEFORE DELETE ON employee
FOR EACH ROW EXECUTE PROCEDURE fn_block_removing_last_manager();
