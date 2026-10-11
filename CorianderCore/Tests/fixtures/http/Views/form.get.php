<?php
return static fn($request) => \CorianderCore\Core\Http\Responses::html(\CorianderCore\Core\Security\Csrf::token());
