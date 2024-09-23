<?php

use CorianderCore\Image\ImageHandler;
?>
<h1 class="mt-6 md:mt-16 font-concert-one text-2xl sm:text-4xl hsm:text-5xl md:text-6xl text-dark-green dark:text-peach tracking-1 text-center">
    🎉 Installation Successful! 🎉
</h1>

<section class="m-auto w-11/12 mt-24">
    <h2 class="font-concert-one text-2xl sm:text-4xl text-dark-green dark:text-peach tracking-1 ml-2 sm:ml-6 md:ml-8 mt-16 align-bottom">
        <?= ImageHandler::render('/public/assets/img/home/coriander_logo.png', 'CorianderPHP logo', 'inline-block bottom-0 align-bottom', 'h-8 sm:h-10 w-auto', 60) ?>
        Welcome to CorianderPHP
    </h2>
    <p class="text-lg sm:text-xl mt-4 sm:mt-8">
        Congratulations! The installation is complete, and you're viewing the home page of CorianderPHP. Everything is working as expected.
    </p>
    <p class="text-lg sm:text-xl mt-2 sm:mt-4">
        You're ready to start building your next project with CorianderPHP.
    </p>
</section>