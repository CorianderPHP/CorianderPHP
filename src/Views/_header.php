<!DOCTYPE html>
<html lang="<?= $lang ?? 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'CorianderPHP' ?></title>
    <meta name="description" content="<?= $description ?? '' ?>">
    <link rel="stylesheet" href="<?= \CorianderCore\Core\Support\PublicUrl::versionedAsset('assets/css/output.css') ?>">
</head>
<body class="min-h-screen bg-white text-black dark:bg-black dark:text-white">
<header><nav><a href="/">Home</a></nav></header>
<main>
