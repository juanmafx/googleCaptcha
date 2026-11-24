<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
<?php if ($captchaSiteKey !== null): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</head>
<body>
    <main class="card">
<?= $content ?>
    </main>
</body>
</html>
