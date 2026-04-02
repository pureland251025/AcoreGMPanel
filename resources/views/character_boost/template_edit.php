<?php
/**
 * File: resources/views/character_boost/template_edit.php
 * Purpose: Admin editor for a boost template.
 */

use Acme\Panel\Support\Csrf;

$realmId = (int) ($realm_id ?? 1);
$template = is_array($template ?? null) ? $template : null;
$error = $error ?? null;

$isEdit = $template && isset($template['id']);
$id = $isEdit ? (int) $template['id'] : 0;

$endpoint = url('/character-boost/api/templates/save');

$itemsLines = [];
if ($isEdit && !empty($template['items']) && is_array($template['items'])) {
    foreach ($template['items'] as $it) {
        $entry = (int) ($it['item_entry'] ?? 0);
        $qty = (int) ($it['quantity'] ?? 0);
        if ($entry > 0 && $qty > 0) {
            $itemsLines[] = $entry . ':' . $qty;
        }
    }
}

$tiers = [];
if ($isEdit && !empty($template['class_rewards']) && is_array($template['class_rewards'])) {
    foreach ($template['class_rewards'] as $rw) {
        $t = (string) ($rw['tier'] ?? '');
        $t = strtolower(trim($t));
        if ($t !== '') {
            $tiers[] = $t;
        }
    }
}
$tiers = array_values(array_unique($tiers));
?>

<?php if($error): ?>
  <div class="panel-flash panel-flash--danger panel-flash--inline is-visible"><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>

<h1 class="page-title">
  <?= htmlspecialchars($isEdit ? __('app.character_boost.templates.edit_heading', ['id' => $id]) : __('app.character_boost.templates.create_heading')) ?>
</h1>

<div class="panel" style="margin-bottom:12px">
  <div class="flex between center" style="gap:12px;flex-wrap:wrap">
    <div style="opacity:.8"><?= htmlspecialchars(__('app.character_boost.templates.hint.realm', ['id' => $realmId])) ?></div>
    <div class="flex center" style="gap:10px;flex-wrap:wrap">
      <a class="btn" href="<?= url('/character-boost/templates') ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.back')) ?></a>
      <a class="btn" href="<?= url('/character-boost/redeem-codes') ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.codes')) ?></a>
    </div>
  </div>
</div>

<div class="panel">
  <div id="boostTplEditFlash" class="panel-flash panel-flash--inline" style="display:none"></div>

  <form id="boostTplEditForm" class="form" data-endpoint="<?= htmlspecialchars($endpoint) ?>">
    <?= Csrf::field() ?>
    <?php if($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$id ?>" />
    <?php endif; ?>

    <div class="form-row">
      <label class="label" for="boostTplName"><?= htmlspecialchars(__('app.character_boost.templates.fields.name')) ?></label>
      <input id="boostTplName" name="name" class="input" value="<?= htmlspecialchars((string)($template['name'] ?? '')) ?>" required />
    </div>

    <div class="form-row">
      <label class="label" for="boostTplLevel"><?= htmlspecialchars(__('app.character_boost.templates.fields.target_level')) ?></label>
      <input id="boostTplLevel" name="target_level" type="number" class="input" min="1" max="255" value="<?= (int)($template['target_level'] ?? 80) ?>" required />
    </div>

    <div class="form-row">
      <label class="label" for="boostTplGold"><?= htmlspecialchars(__('app.character_boost.templates.fields.money_gold')) ?></label>
      <input id="boostTplGold" name="money_gold" type="number" class="input" min="0" value="<?= (int)($template['money_gold'] ?? 0) ?>" />
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.templates.fields.require_match')) ?></label>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="require_account_level_match" value="1" <?= ((int)($template['require_account_level_match'] ?? 0) === 1) ? 'checked' : '' ?> />
        <span><?= htmlspecialchars(__('app.character_boost.templates.fields.require_match_label')) ?></span>
      </label>
    </div>

    <div class="form-row">
      <label class="label" for="boostTplItems"><?= htmlspecialchars(__('app.character_boost.templates.fields.items')) ?></label>
      <textarea id="boostTplItems" name="items" class="input" style="min-height:160px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace"><?= htmlspecialchars(implode("\n", $itemsLines)) ?></textarea>
      <div class="help" style="margin-top:6px;opacity:.75;font-size:13px">
        <?= htmlspecialchars(__('app.character_boost.templates.hint.items_format')) ?>
      </div>
    </div>

    <div class="form-row">
      <label class="label" for="boostTplTiers"><?= htmlspecialchars(__('app.character_boost.templates.fields.class_rewards')) ?></label>
      <textarea id="boostTplTiers" name="class_rewards" class="input" style="min-height:90px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace"><?= htmlspecialchars(implode("\n", $tiers)) ?></textarea>
      <div class="help" style="margin-top:6px;opacity:.75;font-size:13px">
        <?= htmlspecialchars(__('app.character_boost.templates.hint.class_rewards')) ?>
      </div>
    </div>

    <div class="form-row">
      <button class="btn btn-primary" type="submit"><?= htmlspecialchars(__('app.character_boost.templates.actions.save')) ?></button>
    </div>
  </form>
</div>
