#!/usr/bin/env bash
cd "$(dirname "$0")"
command -v php >/dev/null || { echo "PHP not found. Install PHP 8.1+ first."; exit 1; }
echo "=============  SMART FARMER  ============="
echo " This computer : http://localhost:8000"
echo " Same Wi-Fi    : http://$(hostname -I 2>/dev/null | awk '{print $1}'):8000"
echo " Admin account : set SMARTFARMER_ADMIN_EMAIL and SMARTFARMER_ADMIN_PASSWORD before first run"
echo "========================================="
php -S 0.0.0.0:8000 router.php
