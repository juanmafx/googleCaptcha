<h1>Submissions</h1>
<form action="/admin/logout" method="post">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <button type="submit">Sign out</button>
</form>
<?php if ($submissions === []): ?>
<p>No submissions yet.</p>
<?php endif; ?>
<?php foreach ($submissions as $submission): ?>
<article class="submission">
    <h2><?= $e($submission->createdAt) ?> UTC</h2>
<?php if ($submission->readable): ?>
    <p><strong><?= $e($submission->name) ?></strong> &lt;<?= $e($submission->email) ?>&gt;</p>
    <p class="message"><?= $e($submission->message) ?></p>
<?php else: ?>
    <p class="error">This record could not be decrypted.</p>
<?php endif; ?>
</article>
<?php endforeach; ?>
<nav aria-label="Pagination">
<?php if ($page > 1): ?><a href="/admin?page=<?= $e($page - 1) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $e($page) ?> of <?= $e($pageCount) ?></span>
<?php if ($page < $pageCount): ?><a href="/admin?page=<?= $e($page + 1) ?>">Next</a><?php endif; ?>
</nav>
