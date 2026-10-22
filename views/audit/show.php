<p class="crumb"><a href="<?= e(url('/audit')) ?>">Audit</a> <span>/</span> Event <?= (int)$event['id'] ?></p>
<section class="card">
  <h2><?= e($event['summary']) ?></h2>
  <p class="meta">
    <?= e(audit_action_label((string)$event['action'])) ?>
    · <?= e($event['display_name'] ?: ($event['username'] ?: 'Unknown')) ?>
    · <?= e(format_when($event['created_at'])) ?>
    <?php if (!empty($event['ip'])): ?> · <?= e($event['ip']) ?><?php endif; ?>
  </p>
  <?php if (!$event['groups']): ?>
    <p class="muted">This event has no field-by-field changes.</p>
  <?php endif; ?>
  <?php foreach ($event['groups'] as $group): ?>
    <?php
      $exists = $group['entity_type'] === 'app_vm'
          ? in_array($group['entity_id'], $event['existing_app_ids'], true)
          : in_array($group['entity_id'], $event['existing_db_ids'], true);
      $href = $group['entity_type'] === 'app_vm' ? '/app-vms/' . $group['entity_id'] : '/db-vms/' . $group['entity_id'];
      $kindLabel = $group['entity_type'] === 'app_vm' ? 'APP VM' : 'DB VM';
    ?>
    <h3>
      <?= e($kindLabel) ?>
      <?php if ($exists): ?>
        <a href="<?= e(url($href)) ?>"><?= e($group['entity_name']) ?></a>
      <?php else: ?>
        <?= e($group['entity_name']) ?> <span class="badge decom">Deleted</span>
      <?php endif; ?>
    </h3>
    <table class="diff">
      <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
      <tbody>
        <?php foreach ($group['fields'] as $field): ?>
          <tr>
            <th><?= e($field['field_name']) ?></th>
            <td><?= or_dash($field['old_value']) ?></td>
            <td><?= or_dash($field['new_value']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
</section>
