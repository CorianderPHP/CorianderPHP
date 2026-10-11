<?php
file_put_contents(PROJECT_ROOT . '/CorianderCore/Tests/_tmp_http_page_counter', 'x', FILE_APPEND);
?>
<h1><?= $metadata['title'] ?></h1>
<p><?= htmlspecialchars($request->getAttribute('section') . ':' . $request->getAttribute('id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
