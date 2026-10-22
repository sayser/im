<?php $query = (string)$result['query']; ?>
<p class="lede">
  <?php if ($query === ''): ?>
    Search by VM name, website URL, full IP, or the last octets of an IP. For example, <span class="mono">1.21</span> finds <span class="mono">10.20.1.21</span>.
  <?php elseif ((int)$result['count'] === 0): ?>
    No VMs match “<?= e($query) ?>”.
  <?php else: ?>
    <?= (int)$result['count'] ?> <?= (int)$result['count'] === 1 ? 'match' : 'matches' ?> for “<?= e($query) ?>”. Linked VMs in the same stack stay beside the match.
  <?php endif; ?>
</p>
<?php if ($query !== '' && $result['groups']): ?>
  <?php
    $groups = $result['groups'];
    $hits = $result['hits'];
    $showZone = true;
    require APP_ROOT . '/views/partials/pair_board.php';
  ?>
<?php endif; ?>
