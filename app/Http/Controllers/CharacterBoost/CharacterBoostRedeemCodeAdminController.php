<?php
/**
 * File: app/Http/Controllers/CharacterBoost/CharacterBoostRedeemCodeAdminController.php
 * Purpose: Admin tool to generate character boost redeem codes.
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\CharacterBoost;

use Acme\Panel\Core\{Controller, Lang, Request, Response};
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Support\{Auth, Audit, ServerContext};

class CharacterBoostRedeemCodeAdminController extends Controller
{
    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/account/login');
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $tplRepo = new BoostTemplateRepository(ServerContext::currentId());
        $templates = $tplRepo->listForRealm($realmId);

        return $this->view('character_boost.codes', [
            'title' => Lang::get('app.character_boost.codes.title'),
            'module' => 'character_boost_codes',
            'realm_id' => $realmId,
            'templates' => $templates,
        ]);
    }

    public function apiGenerate(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $templateRaw = (string) $request->input('template_id', '');
        $count = (int) $request->input('count', 0);
        $download = $request->bool('download', false);

        if ($count <= 0 || $count > 10000) {
            return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.invalid_count')], 422);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $tplRepo = new BoostTemplateRepository(ServerContext::currentId());

        $generated = [];
        $total = 0;

        try {
            if ($templateRaw === 'all') {
                $templates = $tplRepo->listForRealm($realmId);
                if (!$templates) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.no_templates')], 422);
                }

                foreach ($templates as $tpl) {
                    $tid = (int) ($tpl['id'] ?? 0);
                    if ($tid <= 0) {
                        continue;
                    }
                    $codes = $tplRepo->generateRedeemCodes($tid, $count);
                    $generated[] = [
                        'template_id' => $tid,
                        'template_name' => (string) ($tpl['name'] ?? ''),
                        'count' => count($codes),
                        'codes' => $codes,
                    ];
                    $total += count($codes);
                }
            } else {
                $templateId = (int) $templateRaw;
                if ($templateId <= 0) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.common.validation.missing_params')], 422);
                }

                $tpl = $tplRepo->findForRealm($realmId, $templateId);
                if (!$tpl) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.invalid_template')], 422);
                }

                $codes = $tplRepo->generateRedeemCodes($templateId, $count);
                $generated[] = [
                    'template_id' => $templateId,
                    'template_name' => (string) ($tpl['name'] ?? ''),
                    'count' => count($codes),
                    'codes' => $codes,
                ];
                $total = count($codes);
            }
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        Audit::log('character_boost', 'generate_redeem_codes', 'realm_id=' . (string) $realmId, [
            'count' => $count,
            'requested_template' => $templateRaw,
            'generated_total' => $total,
            'download' => $download ? 1 : 0,
        ]);

        if ($download) {
            $lines = [];
            foreach ($generated as $g) {
                $header = ($g['template_name'] ?? '') !== ''
                    ? ($g['template_name'] . ' (#' . (string) ($g['template_id'] ?? '') . ')')
                    : ('Template #' . (string) ($g['template_id'] ?? ''));
                $lines[] = '[' . $header . ']';
                foreach (($g['codes'] ?? []) as $c) {
                    $lines[] = (string) $c;
                }
                $lines[] = '';
            }

            $filename = sprintf('boost-redeem-codes-realm%d-%s.txt', $realmId, date('Ymd_His'));
            $content = implode("\n", $lines);

            return $this->response(200, $content, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Generated-Count' => (string) $total,
            ]);
        }

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.character_boost.codes.success', ['count' => $total]),
            'payload' => [
                'realm_id' => $realmId,
                'generated' => $generated,
            ],
        ]);
    }

    public function apiStats(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $templateRaw = (string) $request->input('template_id', '');
        $templateId = $templateRaw === '' || $templateRaw === 'all' ? null : (int) $templateRaw;

        $repo = new BoostTemplateRepository(ServerContext::currentId());

        try {
            if ($templateId !== null && $templateId > 0) {
                $tpl = $repo->findForRealm($realmId, $templateId);
                if (!$tpl) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.invalid_template')], 422);
                }
            }

            $stats = $repo->redeemCodeStatsForRealm($realmId, $templateId);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        return $this->json([
            'success' => true,
            'payload' => [
                'realm_id' => $realmId,
                'template_id' => $templateId,
                'stats' => $stats,
            ],
        ]);
    }

    public function apiList(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $templateRaw = (string) $request->input('template_id', '');
        $templateId = $templateRaw === '' || $templateRaw === 'all' ? null : (int) $templateRaw;
        $unusedOnly = $request->bool('unused_only', false);

        $sort = strtolower(trim((string) $request->input('sort', 'id')));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        if ($sort !== 'id') {
            $sort = 'id';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 50);

        $repo = new BoostTemplateRepository(ServerContext::currentId());

        try {
            if ($templateId !== null && $templateId > 0) {
                $tpl = $repo->findForRealm($realmId, $templateId);
                if (!$tpl) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.invalid_template')], 422);
                }
            }

            $list = $repo->listRedeemCodesForRealm($realmId, $templateId, $unusedOnly ? true : null, $page, $perPage, $sort, $dir);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        return $this->json([
            'success' => true,
            'payload' => [
                'realm_id' => $realmId,
                'template_id' => $templateId,
                'unused_only' => $unusedOnly ? 1 : 0,
                'list' => $list,
            ],
        ]);
    }

    public function apiDeleteUnused(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.validation.missing_id')], 422);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $repo = new BoostTemplateRepository(ServerContext::currentId());

        try {
            $ok = $repo->deleteUnusedRedeemCode($realmId, $id);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        if (!$ok) {
            return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.manage.errors.delete_failed')], 422);
        }

        Audit::log('character_boost', 'delete_unused_redeem_code', 'realm_id=' . (string) $realmId, [
            'id' => $id,
        ]);

        return $this->json(['success' => true, 'message' => Lang::get('app.character_boost.codes.manage.deleted')]);
    }

    public function apiPurgeUnused(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $templateRaw = (string) $request->input('template_id', '');
        $templateId = $templateRaw === '' || $templateRaw === 'all' ? null : (int) $templateRaw;

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $repo = new BoostTemplateRepository(ServerContext::currentId());

        try {
            if ($templateId !== null && $templateId > 0) {
                $tpl = $repo->findForRealm($realmId, $templateId);
                if (!$tpl) {
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.codes.errors.invalid_template')], 422);
                }
            }

            $deleted = $repo->purgeUnusedRedeemCodes($realmId, $templateId);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        Audit::log('character_boost', 'purge_unused_redeem_codes', 'realm_id=' . (string) $realmId, [
            'template_id' => $templateId,
            'deleted' => $deleted,
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.character_boost.codes.manage.purged', ['count' => $deleted]),
            'payload' => [
                'deleted' => $deleted,
            ],
        ]);
    }
}
