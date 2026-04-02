<?php
/**
 * File: resources/views/character_boost/codes.php
 * Purpose: Admin tool UI to generate redeem codes for boost templates.
 */

use Acme\Panel\Support\Csrf;

$endpoint = url('/character-boost/api/redeem-codes/generate');
$endpointStats = url('/character-boost/api/redeem-codes/stats');
$endpointList = url('/character-boost/api/redeem-codes/list');
$endpointDeleteUnused = url('/character-boost/api/redeem-codes/delete-unused');
$endpointPurgeUnused = url('/character-boost/api/redeem-codes/purge-unused');
$realmId = (int) ($realm_id ?? 1);
$templates = is_array($templates ?? null) ? $templates : [];
?>

<h1 class="page-title"><?= htmlspecialchars(__('app.character_boost.codes.title')) ?></h1>

<div class="panel" style="margin-bottom:12px">
  <div class="flex between center" style="gap:12px;flex-wrap:wrap">
    <div></div>
    <div class="flex center" style="gap:10px;flex-wrap:wrap">
      <a class="btn" href="<?= url('/character-boost/templates') ?>"><?= htmlspecialchars(__('app.character_boost.templates.title')) ?></a>
    </div>
  </div>
</div>

<div class="panel" style="margin-bottom:12px">
  <div id="boostCodesFlash" class="panel-flash panel-flash--inline" style="display:none"></div>

  <form id="boostCodesForm" class="form" data-endpoint="<?= htmlspecialchars($endpoint) ?>">
    <?= Csrf::field() ?>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.fields.realm')) ?></label>
      <div class="input" style="display:flex;align-items:center;gap:8px">
        <strong><?= htmlspecialchars((string)$realmId) ?></strong>
        <span style="opacity:.7"><?= htmlspecialchars(__('app.character_boost.codes.hint.realm_from_server')) ?></span>
      </div>
    </div>

    <div class="form-row">
      <label class="label" for="boostCodesTemplate"><?= htmlspecialchars(__('app.character_boost.codes.fields.template')) ?></label>
      <select id="boostCodesTemplate" name="template_id" class="input" required>
        <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?= (int)($tpl['id'] ?? 0) ?>">
            <?= htmlspecialchars((string)($tpl['name'] ?? '')) ?> (Lv.<?= (int)($tpl['target_level'] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label class="label" for="boostCodesCount"><?= htmlspecialchars(__('app.character_boost.codes.fields.count')) ?></label>
      <input id="boostCodesCount" name="count" type="number" class="input" min="1" max="10000" value="10" required />
      <div class="help" style="margin-top:6px;opacity:.75;font-size:13px">
        <?= htmlspecialchars(__('app.character_boost.codes.hint.count_limit')) ?>
      </div>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.fields.output')) ?></label>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="download" value="1" />
        <span><?= htmlspecialchars(__('app.character_boost.codes.fields.download')) ?></span>
      </label>
      <div class="help" style="margin-top:6px;opacity:.75;font-size:13px">
        <?= htmlspecialchars(__('app.character_boost.codes.hint.download')) ?>
      </div>
    </div>

    <div class="form-row">
      <button class="btn btn-primary" type="submit"><?= htmlspecialchars(__('app.character_boost.codes.actions.generate')) ?></button>
    </div>
  </form>
</div>

<div class="panel">
  <h3 style="margin:0 0 10px"><?= htmlspecialchars(__('app.character_boost.codes.generated.title')) ?></h3>
  <textarea id="boostCodesOutput" class="input" style="width:100%;min-height:220px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace" readonly></textarea>
  <div class="help" style="margin-top:6px;opacity:.75;font-size:13px">
    <?= htmlspecialchars(__('app.character_boost.codes.generated.hint')) ?>
  </div>
</div>

<div class="panel" style="margin-top:12px">
  <h3 style="margin:0 0 10px"><?= htmlspecialchars(__('app.character_boost.codes.manage.title')) ?></h3>

  <div id="boostCodesManageFlash" class="panel-flash panel-flash--inline" style="display:none"></div>

  <form id="boostCodesManageForm" class="form"
        data-endpoint-stats="<?= htmlspecialchars($endpointStats) ?>"
        data-endpoint-list="<?= htmlspecialchars($endpointList) ?>"
        data-endpoint-delete-unused="<?= htmlspecialchars($endpointDeleteUnused) ?>"
        data-endpoint-purge-unused="<?= htmlspecialchars($endpointPurgeUnused) ?>">
    <?= Csrf::field() ?>

    <div class="form-row">
      <label class="label" for="boostCodesManageTemplate"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.template')) ?></label>
      <select id="boostCodesManageTemplate" name="template_id" class="input">
        <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?= (int)($tpl['id'] ?? 0) ?>">
            <?= htmlspecialchars((string)($tpl['name'] ?? '')) ?> (Lv.<?= (int)($tpl['target_level'] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.unused_only')) ?></label>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" id="boostCodesManageUnusedOnly" name="unused_only" value="1" checked />
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.unused_only_label')) ?></span>
      </label>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.title')) ?></label>
      <div class="input" style="display:flex;gap:12px;flex-wrap:wrap">
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.total')) ?>: <strong id="boostCodesStatTotal">-</strong></span>
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.unused')) ?>: <strong id="boostCodesStatUnused">-</strong></span>
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.used')) ?>: <strong id="boostCodesStatUsed">-</strong></span>
      </div>
    </div>

    <div class="form-row" style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn" id="boostCodesManageRefresh" type="button"><?= htmlspecialchars(__('app.character_boost.codes.manage.actions.refresh')) ?></button>
      <button class="btn btn-danger" id="boostCodesManagePurgeUnused" type="button"><?= htmlspecialchars(__('app.character_boost.codes.manage.actions.purge_unused')) ?></button>
    </div>
  </form>

  <div style="margin-top:10px;overflow:auto">
    <table class="table table--compact" style="min-width:820px">
      <thead>
        <tr>
          <th>
            <a href="#" id="boostCodesSortId" style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
              <span><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.id')) ?></span>
              <span id="boostCodesSortIdIcon" style="opacity:.7"></span>
            </a>
          </th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.template')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.code')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.status')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.used_by')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.created_at')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.actions')) ?></th>
        </tr>
      </thead>
      <tbody id="boostCodesManageTbody">
        <tr><td colspan="7" style="text-align:center;opacity:.75"><?= htmlspecialchars(__('app.common.loading')) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="help" style="margin-top:8px;opacity:.75;font-size:13px">
    <?= htmlspecialchars(__('app.character_boost.codes.manage.hint')) ?>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;align-items:center">
    <button class="btn" id="boostCodesPagePrev" type="button">&larr;</button>
    <span id="boostCodesPageInfo" style="opacity:.8"></span>
    <button class="btn" id="boostCodesPageNext" type="button">&rarr;</button>
  </div>
</div>
