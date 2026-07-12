<?php
$requestedView = isset($__corianderRequestedView) ? $__corianderRequestedView : 'home';
$metaDataPath = PROJECT_ROOT . '/public/public_views/' . $requestedView . '/metadata.php';
if (file_exists($metaDataPath)) {
    require_once $metaDataPath;
}
?>

<!DOCTYPE html>
<html lang="<?= isset($lang) ? $lang : 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    if (isset($metadata)) {
        echo $metadata;
    } else {
        echo '<title>No configured title</title>';
        echo '<meta name="description" content="No configured description.">';
    }
    ?>

    <link rel="stylesheet" href="<?= \CorianderCore\Core\Support\PublicUrl::versionedAsset('assets/css/output.css') ?>">
</head>

<body class="flex min-h-screen w-full flex-col bg-white text-black scrollbar dark:bg-black dark:text-white">
    <header class="fixed bottom-0 z-50 flex w-full flex-col-reverse font-concert-one pointer-events-none md:sticky md:top-0 md:bottom-auto md:flex-col">
        <div class="w-full border-t border-dark-green/15 bg-true-white/95 text-sm shadow-sm backdrop-blur dark:border-mint/15 dark:bg-black/90 sm:text-lg md:border-b md:border-t-0 md:text-2xl">
            <nav class="relative mx-auto flex h-16 w-full max-w-screen-2xl justify-center overflow-x-auto px-4 pointer-events-auto sm:px-6 md:overflow-visible lg:px-8">
                <div class="flex min-w-max items-center justify-center gap-5 text-sm sm:gap-6 sm:text-base sm:tracking-1 md:gap-10 md:text-2xl">
                    <a href="/home" title="Go to the Home Page" class="relative flex h-16 items-center whitespace-nowrap after:absolute after:content-[''] after:bottom-3 after:h-[2px] md:after:h-[3px] after:inset-x-0 after:mx-auto after:bg-dark-green dark:after:bg-mint <?= $requestedView === 'home' ? "after:w-full" : "after:w-0 hover:after:w-full after:transition-['width']" ?>">Home</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="relative mx-auto w-full max-w-screen-2xl flex-1 pb-20 md:pb-0">
