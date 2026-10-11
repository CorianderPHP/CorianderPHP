<?php
return static function ($request) {
    throw new \RuntimeException('<script>fixture failure</script>');
};
