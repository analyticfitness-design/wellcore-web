-- Migration 020: Add 'presencial' plan type
-- Hidden plan for in-person clients, invitation-only, no payment

ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise','presencial') DEFAULT 'esencial';
ALTER TABLE invitations MODIFY COLUMN plan ENUM('esencial','metodo','elite','presencial') NOT NULL;
