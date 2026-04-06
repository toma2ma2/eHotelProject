SELECT h.chain_name, ROUND(AVG(r.price)::numeric, 2) AS avg_room_price
FROM room r
JOIN hotel h ON h.hotel_id = r.hotel_id
GROUP BY h.chain_name
ORDER BY h.chain_name;

SELECT h.hotel_id, h.area, h.number_rooms, h.chain_name
FROM hotel h
WHERE h.number_rooms > (
    SELECT AVG(h2.number_rooms)
    FROM hotel h2
    WHERE h2.chain_name = h.chain_name
);

SELECT b.booking_id, c.first_name, c.last_name, h.area, b.start_date, b.end_date
FROM booking b
JOIN customer c ON c.customer_id = b.customer_id
JOIN hotel h ON h.hotel_id = b.hotel_id
WHERE b.status = 'active' AND b.end_date > CURRENT_DATE
ORDER BY b.start_date;

SELECT e.employee_id, e.first_name, e.last_name, COUNT(*) AS rentings_handled
FROM renting r
JOIN employee e ON e.employee_id = r.employee_id
GROUP BY e.employee_id, e.first_name, e.last_name
ORDER BY rentings_handled DESC;
