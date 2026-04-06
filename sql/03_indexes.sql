CREATE INDEX idx_hotel_area_chain ON hotel (area, chain_name);

CREATE INDEX idx_booking_room_dates ON booking (hotel_id, room_number, start_date, end_date)
    WHERE status = 'active';
CREATE INDEX idx_renting_room_dates ON renting (hotel_id, room_number, start_date, end_date);
