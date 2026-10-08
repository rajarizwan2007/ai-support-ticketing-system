-- Separate database for the test suite, so tests never touch development data
CREATE DATABASE ticketing_test;
\connect ticketing_test
CREATE EXTENSION IF NOT EXISTS vector;
