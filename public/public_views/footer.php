</main>
<footer id="footer" class="w-full border-t border-dark-green/15 bg-true-white/95 dark:border-mint/15 dark:bg-black/90">
    <div class="relative w-full max-w-screen-2xl text-center m-auto h-full inset-x-0 font-poppins md:text-lg sm:text-lg text-sm pb-2 md:pb-0">
        <div class="pt-4 md:pb-4 pb-16 flex justify-center">
            <span>&copy; CorianderPHP - <?php echo date("Y"); ?></span>
        </div>
    </div>
</footer>

<?php
$requestedView = isset($__corianderRequestedView) ? $__corianderRequestedView : 'home';
if (file_exists(PROJECT_ROOT . '/public/public_views/' . $requestedView . '/index.php') && file_exists(PROJECT_ROOT . '/public/assets/js/' . $requestedView . '/index.js')) {
?>
    <script type="module" src="<?= \CorianderCore\Core\Support\PublicUrl::versionedAsset('assets/js/' . $requestedView . '/index.js') ?>" defer></script>
<?php
}
?>
</body>

</html>
