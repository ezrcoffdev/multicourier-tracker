<?php
/**
 * access.inc.php
 * ----------------------------------------------------------------------
 * Константи и помощни функции, които определят достъпите до API-тата
 * на куриерите (Speedy, Econt, BOX NOW), както и шаблони за автоматично
 * разпознаване на куриера по въведения номер на пратка.
 *
 * Този файл НЕ съдържа чувствителни ключове/пароли (виж config.inc.php).
 *
 * Принципи:
 *  - Всички заявки към API се правят от сървъра (server-side), за да не
 *    изтичат ключове в браузъра.
 *  - Авто-разпознаването по regex е помощно. Винаги има и ръчен избор.
 *  - Стремим се към минимална зависимост: без външни библиотеки.
 */

/* ====== Courier Endpoints (Speedy + Econt only) ====== */

/* Speedy */
define('SPEEDY_API_BASE',        "https://api.speedy.bg/v1/");
define('SPEEDY_API_CMD_TRACK',   "track");
define('SPEEDY_API_CMD_RCV_OFFICE','shipment/info');

/* Econt */
define('ECONT_API_BASE',         "https://ee.econt.com/services/");
define('ECONT_API_CMD_TRACK',    "Shipments/ShipmentService.getShipmentStatuses"); // JSON-RPC

/* Patterns to auto-detect courier */
define('PATTERN_SPEEDY', '/^[0-9]{10,12}$/');
define('PATTERN_ECONT',  '/^(10|53)[0-9]{11}$/'); // per changelog

/* Small helper */
function ezar_json($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }


/* BOX NOW */
define('BOXNOW_API_BASE',        getenv('BOXNOW_API_BASE') ?: ""); // e.g. https://partner.boxnow.bg
define('BOXNOW_OAUTH_PATH',      "/api/v1/auth-sessions");
define('BOXNOW_PARCELS_PATH',    "/api/v1/parcels");
define('BOXNOW_TRACK_PUBLIC',    "https://www.boxnow.bg/en/track");
/* Pattern is not strictly documented; avoid aggressive autodetect.
   We'll allow manual selection and a very loose pattern as hint. */
define('PATTERN_BOXNOW', '/^[A-Z0-9\-]{6,20}$/');
