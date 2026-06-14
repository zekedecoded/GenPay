<?php

echo password_hash(
    '$2y$10$abcdefghijklmnopqrstuuVwXyZ0123456789ABCDEFGHIJKLMNOP.',
    PASSWORD_BCRYPT,
);
