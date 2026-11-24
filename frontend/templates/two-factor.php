<h1>Two-factor verification</h1>
<?php if ($error !== null): ?><p class="error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form action="/admin/two-factor" method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <label for="code">Authenticator code</label>
    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
    <button type="submit">Verify</button>
</form>
