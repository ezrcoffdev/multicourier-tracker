<!DOCTYPE html>
<!--
  index.php
  ----------------------------------------------------------------------
  Минимален изглед/шаблон, който включва track.php и визуализира формата
  и резултатите за проследяване. Този файл може да се вгради в поддомейн
  като tracking.example.com или в поддиректория /tracking.
  - Няма външни зависимости.
  - Лесен за брандиране чрез style.css.
-->
<html lang="bg">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo SITE_TITLE; ?></title>
  <link rel="canonical" href="<?php echo SITE_URL; ?>" />
  <link rel="stylesheet" href="./style.css" />
</head>
<body>
  <main class="ezar-main">
    <h1 class="title"><?php echo SITE_TITLE; ?></h1>
    <?php require_once($_SERVER['DOCUMENT_ROOT'].'/track.php'); ?>
  </main>
</body>
</html>