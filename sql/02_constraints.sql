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
