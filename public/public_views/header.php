<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dynamically generated meta tags -->
    <title><?= htmlspecialchars($metaTitle); ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription); ?>">

    <link rel="stylesheet" href="/public/assets/css/output.css?<?php echo filemtime(PROJECT_ROOT . '/public/assets/css/output.css'); ?>">
</head>

<body class="bg-white dark:bg-black w-full absolute min-h-full scrollbar text-black dark:text-white">
    <header class="md:w-full w-screen fixed md:sticky md:top-0 h-auto bottom-0 z-50 font-concert-one pointer-events-none flex md:flex-col flex-col-reverse">
        <div class="w-full md:text-2xl sm:text-xl text-lg md:border-b-2 md:border-t-0 border-t-2 border-dark-green dark:border-peach bg-white dark:bg-black">
            <nav class="md:max-w-screen-2xl w-full mx-auto relative flex justify-end md:h-16 h-14 pointer-events-auto">
                <div class="flex sm:tracking-1 md:justify-end justify-around w-full">
                    <div class="w-auto flex">
                        <a href="/home" title="Go to the Home Page" class="relative md:mr-12 block m-auto after:absolute after:content-[''] after:-bottom-[2px] md:after:-bottom-1 after:h-[2px] md:after:h-[3px] after:inset-x-0 after:mx-auto after:bg-dark-green dark:after:bg-peach <?= REQUESTED_VIEW === 'home' ? "after:w-full" : "after:w-0 hover:after:w-full after:transition-['width']" ?>">Home</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="relative max-w-screen-2xl mx-auto pb-16 mb-[222px] sm:mb-[254px] md:mb-[134px]">