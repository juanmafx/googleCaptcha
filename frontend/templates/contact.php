<h1>Contact</h1>
<form action="/contact" method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <label for="name">Name</label>
    <input id="name" name="name" type="text" maxlength="100" autocomplete="name" required value="<?= $e($values['name']) ?>">
<?php if (isset($errors['name'])): ?><p class="error" role="alert"><?= $e($errors['name']) ?></p><?php endif; ?>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= $e($values['email']) ?>">
<?php if (isset($errors['email'])): ?><p class="error" role="alert"><?= $e($errors['email']) ?></p><?php endif; ?>
    <label for="message">Message</label>
    <textarea id="message" name="message" rows="6" maxlength="5000" required><?= $e($values['message']) ?></textarea>
<?php if (isset($errors['message'])): ?><p class="error" role="alert"><?= $e($errors['message']) ?></p><?php endif; ?>
    <div class="g-recaptcha" data-sitekey="<?= $e($captchaSiteKey) ?>"></div>
<?php if (isset($errors['captcha'])): ?><p class="error" role="alert"><?= $e($errors['captcha']) ?></p><?php endif; ?>
    <button type="submit">Send</button>
</form>
