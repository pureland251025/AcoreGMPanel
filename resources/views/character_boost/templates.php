<?php
/**
 * File: resources/views/character_boost/templates.php
 * Purpose: Admin list for boost templates.
 */

$templates = is_array($templates ?? null) ? $templates : [];
$realmId = (int) ($realm_id ?? 1);
$deleteEndpoint = url('/character-boost/api/templates/delete');
?>

<h1 class="page-title"><?= htmlspecialchars(__('app.character_boost.templates.title')) ?></h1>

<div class="panel" style="margin-bottom:12px">
  <div class="flex between center" style="gap:12px;flex-wrap:wrap">
    <div style="opacity:.8"><?= htmlspecialchars(__('app.character_boost.templates.hint.realm', ['id' => $realmId])) ?></div>
    <div class="flex center" style="gap:10px;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?= url('/character-boost/templates/edit') ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.create')) ?></a>
      <a class="btn" href="<?= url('/character-boost/redeem-codes') ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.codes')) ?></a>
      <a class="btn" href="<?= url('/public/character-boost') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(__('app.character_boost.templates.actions.public_redeem')) ?></a>
    </div>
  </div>
</div>

<div class="panel">
  <div id="boostTplFlash" class="panel-flash panel-flash--inline" style="display:none"></div>

  <table class="table" id="boostTplTable" data-delete-endpoint="<?= htmlspecialchars($deleteEndpoint) ?>">
    <thead>
      <tr>
        <th>ID</th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.name')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.target_level')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.money_gold')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.items')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.class_rewards')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.require_match')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$templates): ?>
        <tr class="js-empty-row"><td colspan="8" style="text-align:center;opacity:.7"><?= htmlspecialchars(__('app.character_boost.templates.empty')) ?></td></tr>
      <?php else: ?>
        <?php foreach($templates as $tpl): ?>
          <?php
            $items = is_array($tpl['items'] ?? null) ? $tpl['items'] : [];
            $tiers = is_array($tpl['class_rewards'] ?? null) ? $tpl['class_rewards'] : [];
          ?>
          <tr data-id="<?= (int)($tpl['id'] ?? 0) ?>">
            <td><?= (int)($tpl['id'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($tpl['name'] ?? '')) ?></td>
            <td><?= (int)($tpl['target_level'] ?? 0) ?></td>
            <td><?= (int)($tpl['money_gold'] ?? 0) ?></td>
            <td style="font-size:13px;line-height:1.35">
              <?php if(!$items): ?>
                <span style="opacity:.7">-</span>
              <?php else: ?>
                <?php foreach($items as $it): ?>
                  <?php
                    $entry = (int)($it['item_entry'] ?? 0);
                    $qty = (int)($it['quantity'] ?? 0);
                    $nm = (string)($it['item_name'] ?? '');
                    if($entry<=0 || $qty<=0) continue;
                  ?>
                  <div><?= htmlspecialchars($nm !== '' ? $nm : ('#'.$entry)) ?> ×<?= $qty ?> <span style="opacity:.65">(#<?= $entry ?>)</span></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td style="font-size:13px;line-height:1.35">
              <?php if(!$tiers): ?>
                <span style="opacity:.7">-</span>
              <?php else: ?>
                <?= htmlspecialchars(implode(', ', array_values(array_unique(array_map('strval', $tiers))))) ?>
              <?php endif; ?>
            </td>
            <td><?= ((int)($tpl['require_account_level_match'] ?? 0) === 1) ? 'Y' : 'N' ?></td>
            <td class="flex" style="gap:8px;flex-wrap:wrap">
              <a class="btn" href="<?= url('/character-boost/templates/edit?id='.(int)($tpl['id'] ?? 0)) ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.edit')) ?></a>
              <button class="btn btn-danger js-boost-tpl-delete" type="button" data-id="<?= (int)($tpl['id'] ?? 0) ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.delete')) ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
