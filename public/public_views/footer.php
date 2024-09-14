</main>
<footer id="footer" class="w-full absolute bottom-0 h-auto border-t-2 border-dark-green dark:border-peach bg-white dark:bg-black">
    <div class="relative w-full max-w-screen-2xl text-center m-auto h-full inset-x-0 font-poppins md:text-lg sm:text-lg text-sm pb-2 md:pb-0">
        <div class="pt-4 md:pb-4 pb-16 flex justify-center">
            <span>© CorianderPHP - <?php echo date("Y"); ?></span>
        </div>
    </div>
</footer>

<?php
if (file_exists(PROJECT_ROOT . '/public/public_views/' . REQUESTED_VIEW . '/index.php') && file_exists(PROJECT_ROOT . '/public/assets/js/' . REQUESTED_VIEW . '/index.js')) {
?>
    <script type="module" src="/public/assets/js/<?= REQUESTED_VIEW ?>/index.js?<?php echo filemtime(PROJECT_ROOT . '/public/assets/js/' . REQUESTED_VIEW . '/index.js'); ?>" defer></script>
<?php
}
?>
</body>

</html>