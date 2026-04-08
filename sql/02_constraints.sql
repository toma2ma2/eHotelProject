CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE hotel
    ADD CONSTRAINT chk_hotel_rating_stars
    CHECK (hotel_rating BETWEEN 1 AND 5);

ALTER TABLE room
    ADD CONSTRAINT chk_view_type
    CHECK (view_type IN ('sea', 'mountain', 'city', 'garden'));

ALTER TABLE room
    ADD CONSTRAINT chk_room_capacity_label
    CHECK (room_capacity IN ('single', 'double', 'suite', 'family', 'twin'));
ALTER TABLE booking
    ADD CONSTRAINT chk_booking_not_in_past
    CHECK (start_date >= booking_date);

ALTER TABLE room
    ADD CONSTRAINT chk_room_price_range
    CHECK (price BETWEEN 1 AND 99999);

ALTER TABLE hotel
    ADD CONSTRAINT chk_hotel_email
    CHECK (contact_email LIKE '%@%.%');

ALTER TABLE employee
    ADD CONSTRAINT chk_ssn_format
    CHECK (ssn_sin LIKE 'SSN-%');

ALTER TABLE booking
    ADD CONSTRAINT chk_no_overlapping_bookings
    EXCLUDE USING gist (
        hotel_id WITH =,
        room_number WITH =,
        daterange(start_date, end_date) WITH &&
    )
    WHERE (status = 'active');