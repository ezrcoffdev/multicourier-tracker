<?php
/**
 * config.inc.php
 * ----------------------------------------------------------------------
 * Административни настройки и КРЕДЕНШЪЛИ за външните услуги.
 * Този файл се зарежда от track.php. Не го качвайте в публични репозитории.
 *
 * Попълнете:
 *  - SITE_*: заглавие/URL/контактна страница на вашия сайт.
 *  - SPEEDY_USER / SPEEDY_PASS: акаунт за Speedy API.
 *  - BOXNOW_CLIENT_ID / BOXNOW_CLIENT_SECRET + BOXNOW_API_BASE (вж. access.inc.php).
 *
 * Сигурност:
 *  - В production използвайте ENV променливи и/или .php файл извън web root.
 *  - Ограничете правата за четене (chmod 640) и собственост на уеб потребителя.
 */

# --- Administrative (EDIT THESE) ---
define('SITE_TITLE',       "EZAR: Проследяване на пратки");
define('SITE_URL',         "https://tracking.example.com");
define('SITE_CONTACT_URL', "https://www.example.com/contact");
define('LANGUAGE_DEFAULT', "bg"); // 'bg' or 'en'

# --- Review links (optional; leave empty to hide) ---
define('REVIEW_URL_GMB',   "");

# --- Speedy API credentials (required for Speedy) ---
define('SPEEDY_USER', "");
define('SPEEDY_PASS', "");


// --- BOX NOW OAuth2 (Client Credentials) ---
// Get these from BOX NOW support; also set BOXNOW_API_BASE above (env or const).
define('BOXNOW_CLIENT_ID', "");
define('BOXNOW_CLIENT_SECRET', "");
