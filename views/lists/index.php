<p class="lede">Admins maintain environment tabs and every dropdown on an inventory record. Adding an environment creates a new tab. OPs and view-only users can only pick from these values.</p>
<div class="panels">
  <?php foreach (['zone', 'client', 'environment', 'application', 'os'] as $kind): ?>
    <section class="card">
      <h2><?= e(lookup_kind_heading($kind)) ?></h2>
      <form class="inline-add" method="post" action="<?= e(url('/lists')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <input name="name" maxlength="120" required placeholder="Add a <?= e(lookup_kind_label($kind)) ?>" aria-label="New <?= e(lookup_kind_label($kind)) ?>">
        <button class="btn small" type="submit">Add</button>
      </form>
      <?php if (!$groups[$kind]): ?>
        <p class="muted">None yet.</p>
      <?php else: ?>
        <ul class="option-list">
          <?php foreach ($groups[$kind] as $row): ?>
            <li>
              <form method="post" action="<?= e(url('/lists/' . (int)$row['id'])) ?>" data-confirm-delete="Delete this unused value?">
                <?= csrf_field() ?>
                <input name="name" maxlength="120" required value="<?= e($row['name']) ?>" aria-label="Name">
                <span class="muted"><?= (int)$row['used'] ?> in use</span>
                <?php if ((int)$row['active'] !== 1): ?><span class="badge decom">Retired</span><?php endif; ?>
                <button class="btn small ghost" name="action" value="rename" type="submit">Rename</button>
                <?php if ((int)$row['used'] === 0): ?>
                  <button class="btn small danger ghost" name="action" value="delete" type="submit">Delete</button>
                <?php elseif ((int)$row['active'] === 1): ?>
                  <button class="btn small ghost" name="action" value="retire" type="submit">Retire</button>
                <?php else: ?>
                  <button class="btn small ghost" name="action" value="restore" type="submit">Restore</button>
                <?php endif; ?>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
