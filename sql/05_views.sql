CREATE OR REPLACE VIEW v_rooms_available_per_area AS
SELECT
    h.area,
    COUNT(*)::bigint AS available_rooms
FROM room r
JOIN hotel h ON h.hotel_id = r.hotel_id
WHERE r.has_problems IS FALSE
  AND NOT EXISTS (
      SELECT 1 FROM booking b
      WHERE b.hotel_id = r.hotel_id
        AND b.room_number = r.room_number
        AND b.status = 'active'
        AND b.start_date <= CURRENT_DATE AND b.end_date > CURRENT_DATE
  )
  AND NOT EXISTS (
      SELECT 1 FROM renting x
      WHERE x.hotel_id = r.hotel_id
        AND x.room_number = r.room_number
        AND x.start_date <= CURRENT_DATE AND x.end_date > CURRENT_DATE
  )
GROUP BY h.area;

CREATE OR REPLACE VIEW v_hotel_room_capacity AS
SELECT
    h.hotel_id,
    h.chain_name,
    h.area,
    SUM(
        CASE r.room_capacity
            WHEN 'single' THEN 1
            WHEN 'twin' THEN 2
            WHEN 'double' THEN 2
            WHEN 'suite' THEN 4
            WHEN 'family' THEN 5
            ELSE 1
        END
    )::integer AS total_capacity_units,
    COUNT(*)::integer AS room_count
FROM room r
JOIN hotel h ON h.hotel_id = r.hotel_id
GROUP BY h.hotel_id, h.chain_name, h.area;
