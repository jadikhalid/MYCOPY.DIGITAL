<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../protocol.php';
require __DIR__ . '/../protocol/intake.php';

protocol_reset_founder();
echo "Founder protocol reset to intake (phase 0).\n";
