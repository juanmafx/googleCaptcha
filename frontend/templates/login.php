<h1>Admin sign in</h1>
<?php if ($error !== null): ?><p class="error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form action="/admin/login" method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <label for="username">Username</label>
    <input id="username" name="username" type="text" maxlength="64" autocomplete="username" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" maxlength="1024" autocomplete="current-password" required>
    <button type="submit">Continue</button>
</form>
