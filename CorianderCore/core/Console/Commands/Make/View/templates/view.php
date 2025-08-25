<!-- View: {{viewName}} -->
<section>
    <h1>{{viewName}}</h1>
    <p>This is the {{viewName}} view.</p>
    <form method="POST">
        <?= \CorianderCore\Core\Security\Csrf::input() ?>
        <!-- Add your form fields here -->
    </form>
</section>