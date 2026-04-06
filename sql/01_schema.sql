CREATE TABLE hotel_chain (
    chain_name       VARCHAR(120) PRIMARY KEY,
    address_central_offices TEXT NOT NULL,
    number_hotels    INTEGER NOT NULL CHECK (number_hotels >= 0)
);

CREATE TABLE chain_email (
    chain_name       VARCHAR(120) NOT NULL REFERENCES hotel_chain(chain_name) ON DELETE CASCADE,
    contact_email    VARCHAR(255) NOT NULL,
    PRIMARY KEY (chain_name, contact_email)
);

CREATE TABLE chain_phone (
    chain_name       VARCHAR(120) NOT NULL REFERENCES hotel_chain(chain_name) ON DELETE CASCADE,
    phone_numbers    VARCHAR(40) NOT NULL,
    PRIMARY KEY (chain_name, phone_numbers)
);

CREATE TABLE hotel (
    hotel_id         SERIAL PRIMARY KEY,
    chain_name       VARCHAR(120) NOT NULL REFERENCES hotel_chain(chain_name) ON DELETE RESTRICT,
    hotel_rating     SMALLINT NOT NULL,
    number_rooms     INTEGER NOT NULL CHECK (number_rooms >= 0),
    street_number    VARCHAR(20) NOT NULL,
    street_name      VARCHAR(200) NOT NULL,
    area             VARCHAR(120) NOT NULL,
    contact_email    VARCHAR(255) NOT NULL,
    UNIQUE (chain_name, street_number, street_name, area)
);

CREATE TABLE hotel_phone (
    hotel_id         INTEGER NOT NULL REFERENCES hotel(hotel_id) ON DELETE CASCADE,
    phone_numbers    VARCHAR(40) NOT NULL,
    PRIMARY KEY (hotel_id, phone_numbers)
);

CREATE TABLE room (
    hotel_id         INTEGER NOT NULL REFERENCES hotel(hotel_id) ON DELETE CASCADE,
    room_number      VARCHAR(20) NOT NULL,
    price            NUMERIC(10,2) NOT NULL CHECK (price > 0),
    TV               BOOLEAN NOT NULL DEFAULT FALSE,
    air_condition    BOOLEAN NOT NULL DEFAULT FALSE,
    fridge           BOOLEAN NOT NULL DEFAULT FALSE,
    room_capacity    VARCHAR(40) NOT NULL,
    view_type        VARCHAR(20) NOT NULL,
    extendable       BOOLEAN NOT NULL DEFAULT FALSE,
    has_problems     BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (hotel_id, room_number)
);

CREATE TABLE customer (
    customer_ID      SERIAL PRIMARY KEY,
    first_name       VARCHAR(80) NOT NULL,
    last_name        VARCHAR(80) NOT NULL,
    street_address   TEXT NOT NULL,
    city             VARCHAR(100) NOT NULL,
    ID_type          VARCHAR(40) NOT NULL,
    ID_number        VARCHAR(80) NOT NULL,
    registration_date DATE NOT NULL DEFAULT CURRENT_DATE,
    UNIQUE (ID_type, ID_number)
);

CREATE TABLE employee (
    employee_ID      SERIAL PRIMARY KEY,
    hotel_ID         INTEGER NOT NULL REFERENCES hotel(hotel_id) ON DELETE CASCADE,
    first_name       VARCHAR(80) NOT NULL,
    last_name        VARCHAR(80) NOT NULL,
    address          TEXT NOT NULL,
    ssn_sin          VARCHAR(20) NOT NULL UNIQUE,
    is_manager       BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE employee_role (
    employee_ID      INTEGER NOT NULL REFERENCES employee(employee_ID) ON DELETE CASCADE,
    role_name        VARCHAR(80) NOT NULL,
    PRIMARY KEY (employee_ID, role_name)
);

CREATE TABLE booking (
    booking_id       SERIAL PRIMARY KEY,
    customer_id      INTEGER NOT NULL REFERENCES customer(customer_ID) ON DELETE RESTRICT,
    hotel_id         INTEGER NOT NULL,
    room_number      VARCHAR(20) NOT NULL,
    booking_date     DATE NOT NULL DEFAULT CURRENT_DATE,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'active',
    FOREIGN KEY (hotel_id, room_number) REFERENCES room(hotel_id, room_number) ON DELETE RESTRICT,
    CHECK (end_date > start_date),
    CHECK (status IN ('active', 'converted', 'cancelled'))
);

CREATE TABLE renting (
    renting_id       SERIAL PRIMARY KEY,
    booking_id       INTEGER REFERENCES booking(booking_id) ON DELETE SET NULL,
    customer_id      INTEGER NOT NULL REFERENCES customer(customer_ID) ON DELETE RESTRICT,
    employee_id      INTEGER NOT NULL REFERENCES employee(employee_ID) ON DELETE RESTRICT,
    hotel_id         INTEGER NOT NULL,
    room_number      VARCHAR(20) NOT NULL,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    FOREIGN KEY (hotel_id, room_number) REFERENCES room(hotel_id, room_number) ON DELETE RESTRICT,
    CHECK (end_date >= start_date)
);

CREATE TABLE customer_payment (
    payment_id       SERIAL PRIMARY KEY,
    renting_id       INTEGER NOT NULL REFERENCES renting(renting_id) ON DELETE CASCADE,
    amount           NUMERIC(10,2) NOT NULL CHECK (amount > 0),
    paid_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE booking_archive (
    archive_id       SERIAL PRIMARY KEY,
    old_booking_id   INTEGER,
    chain_name       VARCHAR(120),
    hotel_id         INTEGER,
    room_number      VARCHAR(20),
    first_name       VARCHAR(80),
    last_name        VARCHAR(80),
    booking_date     DATE,
    start_date       DATE,
    end_date         DATE,
    archived_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE renting_archive (
    archive_id       SERIAL PRIMARY KEY,
    old_renting_id   INTEGER,
    chain_name       VARCHAR(120),
    hotel_id         INTEGER,
    room_number      VARCHAR(20),
    first_name       VARCHAR(80),
    last_name        VARCHAR(80),
    booking_id       INTEGER,
    start_date       DATE,
    end_date         DATE,
    archived_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
