INSERT INTO hotel_chain (chain_name, address_central_offices, number_hotels)
VALUES
('NorthStar Hotels', '200 Bay Street, Toronto, ON M5J 2J1', 8),
('Pacific Coast Inns', '700 W Georgia Street, Vancouver, BC V7Y 1K8', 8),
('MetroStay Group', '350 N Orleans Street, Chicago, IL 60654', 8),
('Summit Lodges', '1700 Broadway, Denver, CO 80290', 8),
('Atlantic Suites', '1 Marina Park Drive, Boston, MA 02210', 8);

INSERT INTO chain_email (chain_name, contact_email)
VALUES
('NorthStar Hotels', 'hq@northstar-hotels.example.com'),
('Pacific Coast Inns', 'hq@pacificcoast.example.com'),
('MetroStay Group', 'hq@metrostay.example.com'),
('Summit Lodges', 'hq@summitlodges.example.com'),
('Atlantic Suites', 'hq@atlanticsuites.example.com');

INSERT INTO chain_phone (chain_name, phone_numbers)
VALUES
('NorthStar Hotels', '416-555-0100'),
('Pacific Coast Inns', '604-555-0200'),
('MetroStay Group', '312-555-0300'),
('Summit Lodges', '303-555-0400'),
('Atlantic Suites', '617-555-0500');

INSERT INTO hotel (chain_name, hotel_rating, number_rooms, street_number, street_name, area, contact_email)
SELECT
    (ARRAY[
        'NorthStar Hotels',
        'Pacific Coast Inns',
        'MetroStay Group',
        'Summit Lodges',
        'Atlantic Suites'
    ])[c],
    1 + (((c - 1) * 8 + h - 1) % 5),
    5,
    h::text,
    'Main Street',
    CASE
        WHEN c = 1 AND h IN (1, 2) THEN 'Toronto, ON'
        ELSE (ARRAY[
            'Vancouver, BC', 'Montreal, QC', 'Calgary, AB', 'Chicago, IL',
            'New York, NY', 'Los Angeles, CA', 'Mexico City, MX', 'Houston, TX',
            'Miami, FL', 'Seattle, WA', 'Boston, MA', 'Denver, CO', 'Phoenix, AZ',
            'Atlanta, GA', 'Dallas, TX', 'San Diego, CA', 'Philadelphia, PA'
        ])[1 + (((c - 1) * 8 + h - 1) % 16)]
    END,
    'frontdesk' || c::text || '_' || h::text || '@hotel.example.com'
FROM generate_series(1, 5) AS c
CROSS JOIN generate_series(1, 8) AS h;

INSERT INTO hotel_phone (hotel_id, phone_numbers)
SELECT hotel_id, '416-555-' || LPAD((hotel_id % 10000)::text, 4, '0')
FROM hotel;

INSERT INTO room (hotel_id, room_number, price, TV, air_condition, fridge, room_capacity, view_type, extendable, has_problems)
SELECT
    h.hotel_id,
    rnum::text,
    (90 + (h.hotel_id % 6) * 15 + rnum)::numeric(10,2),
    TRUE,
    TRUE,
    (rnum % 3 = 0),
    (ARRAY['single', 'double', 'suite', 'family', 'twin'])[rnum],
    (ARRAY['sea', 'mountain', 'city', 'garden'])[1 + (h.hotel_id % 4)],
    (rnum % 2 = 0),
    FALSE
FROM hotel h
CROSS JOIN generate_series(1, 5) AS rnum;

INSERT INTO room (hotel_id, room_number, price, TV, air_condition, fridge, room_capacity, view_type, extendable, has_problems)
VALUES
(1, '6', 118.00, TRUE, TRUE, FALSE, 'twin', 'city', FALSE, FALSE),
(1, '7', 122.00, TRUE, TRUE, TRUE, 'single', 'garden', TRUE, FALSE);

UPDATE hotel ho
SET number_rooms = (SELECT COUNT(*)::integer FROM room r WHERE r.hotel_id = ho.hotel_id);

UPDATE hotel_chain hc
SET number_hotels = (SELECT COUNT(*)::integer FROM hotel h WHERE h.chain_name = hc.chain_name);

INSERT INTO employee (hotel_ID, first_name, last_name, address, ssn_sin, is_manager)
SELECT
    hotel_id,
    'Chris',
    'Lee' || hotel_id::text,
    street_number || ' ' || street_name || ', ' || area,
    'SSN-' || LPAD(hotel_id::text, 6, '0'),
    TRUE
FROM hotel;

INSERT INTO employee_role (employee_ID, role_name)
SELECT employee_ID, 'manager' FROM employee WHERE is_manager IS TRUE;

INSERT INTO employee (hotel_ID, first_name, last_name, address, ssn_sin, is_manager)
SELECT
    h.hotel_id,
    'Alex',
    'Desk' || h.hotel_id,
    'Front desk, ' || h.street_name || ', ' || h.area,
    'SSN-D' || LPAD(h.hotel_id::text, 6, '0'),
    FALSE
FROM hotel h;

INSERT INTO employee_role (employee_ID, role_name)
SELECT employee_ID, 'front_desk' FROM employee WHERE is_manager IS FALSE;

INSERT INTO customer (first_name, last_name, street_address, city, ID_type, ID_number, registration_date)
SELECT
    'Cust',
    'User' || g,
    g || ' Oak Avenue',
    'Toronto',
    'SIN',
    'SIN-' || LPAD(g::text, 6, '0'),
    CURRENT_DATE - (g % 30)
FROM generate_series(1, 12) AS g;

INSERT INTO booking (customer_id, hotel_id, room_number, booking_date, start_date, end_date, status)
VALUES
(1, 1, '1', CURRENT_DATE, CURRENT_DATE + 20, CURRENT_DATE + 25, 'active'),
(2, 1, '2', CURRENT_DATE, CURRENT_DATE + 30, CURRENT_DATE + 35, 'active'),
(3, 2, '1', CURRENT_DATE, CURRENT_DATE + 40, CURRENT_DATE + 45, 'active'),
(4, 3, '3', CURRENT_DATE, CURRENT_DATE + 10, CURRENT_DATE + 12, 'active'),
(5, 1, '3', CURRENT_DATE, CURRENT_DATE + 50, CURRENT_DATE + 55, 'active');

INSERT INTO renting (booking_id, customer_id, employee_id, hotel_id, room_number, start_date, end_date)
SELECT
    bk.booking_id,
    bk.customer_id,
    e.employee_ID,
    bk.hotel_id,
    bk.room_number,
    bk.start_date,
    bk.end_date
FROM booking bk
JOIN employee e ON e.hotel_ID = bk.hotel_id AND e.is_manager IS TRUE
WHERE bk.booking_id = (SELECT MIN(booking_id) FROM booking);

INSERT INTO renting (booking_id, customer_id, employee_id, hotel_id, room_number, start_date, end_date)
SELECT
    NULL,
    2,
    e.employee_ID,
    2,
    '4',
    CURRENT_DATE,
    CURRENT_DATE + 2
FROM employee e
WHERE e.hotel_ID = 2 AND e.is_manager IS FALSE
LIMIT 1;

INSERT INTO renting (booking_id, customer_id, employee_id, hotel_id, room_number, start_date, end_date)
SELECT
    (SELECT booking_id FROM booking WHERE customer_id = 3 AND hotel_id = 2 LIMIT 1),
    3,
    e.employee_ID,
    2,
    '2',
    CURRENT_DATE + 1,
    CURRENT_DATE + 5
FROM employee e
WHERE e.hotel_ID = 2 AND e.is_manager IS TRUE
LIMIT 1;

INSERT INTO customer_payment (renting_id, amount)
SELECT renting_id, 220.00 FROM renting ORDER BY renting_id DESC LIMIT 1;
