<nav class="env-tabs" aria-label="Tasks">
  <a class="<?= ($taskTab ?? '') === 'open' ? 'active' : '' ?>" href="<?= e(url('/tasks')) ?>"<?= ($taskTab ?? '') === 'open' ? ' aria-current="page"' : '' ?>>Tasks</a>
  <a class="<?= ($taskTab ?? '') === 'archived' ? 'active' : '' ?>" href="<?= e(url('/tasks/archived')) ?>"<?= ($taskTab ?? '') === 'archived' ? ' aria-current="page"' : '' ?>>Archived</a>
</nav>
