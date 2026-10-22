<?php if (!$history): ?>
  <p class="muted">No field history yet.</p>
<?php else: ?>
  <ol class="timeline">
    <?php foreach ($history as $group): ?>
      <li>
        <div class="when">
          <strong><?= e($group['summary']) ?></strong>
          <span><?= e($group['display_name']) ?> · <?= e(format_when($group['created_at'])) ?></span>
        </div>
        <table class="diff">
          <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
          <tbody>
            <?php foreach ($group['fields'] as $field): ?>
              <tr>
                <th><?= e($field['field']) ?></th>
                <td><?= or_dash($field['old']) ?></td>
                <td><?= or_dash($field['new']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </li>
    <?php endforeach; ?>
  </ol>
  <p class="hint">Times shown in <?= e(display_timezone_name()) ?>.</p>
<?php endif; ?>
