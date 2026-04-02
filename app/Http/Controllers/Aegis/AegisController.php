<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Aegis;

use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Aegis\AegisRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Auth;
use Acme\Panel\Support\GameMaps;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\ServerList;
use Acme\Panel\Support\SoapExecutor;

class AegisController extends Controller
{
    private ?AegisRepository $repo = null;

    private function repo(): AegisRepository
    {
        if ($this->repo === null) {
            $this->repo = new AegisRepository();
        }

        return $this->repo;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $requestedServer = $request->input('server', null);
        if ($requestedServer === null) {
            return;
        }

        $serverId = (int) $requestedServer;
        if ($serverId !== ServerContext::currentId() && ServerList::valid($serverId)) {
            ServerContext::set($serverId);
            if ($this->repo !== null) {
                $this->repo->rebind($serverId);
            }
        }
    }

    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/account/login');
        }

        $this->maybeSwitchServer($request);

        return $this->view('aegis.index', [
            'title' => Lang::get('app.aegis.page_title'),
            'module' => 'aegis',
            'current_server' => ServerContext::currentId(),
            'servers' => ServerList::options(),
            'aegis_data' => [
                'options' => [
                    'cheat_types' => $this->cheatTypes(),
                    'evidence_levels' => $this->evidenceLevels(),
                    'punish_stages' => $this->punishStages(),
                    'status_filters' => $this->statusFilters(),
                    'manual_actions' => $this->manualActions(),
                ],
                'defaults' => [
                    'overview_days' => 7,
                    'events_days' => 7,
                    'per_page' => 20,
                    'log_limit' => 80,
                ],
            ],
        ]);
    }

    public function apiOverview(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);

        $overview = $this->repo()->overview($request->int('days', 7));
        return $this->json([
            'success' => true,
            'payload' => $overview,
        ]);
    }

    public function apiOffenses(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);

        $pager = $this->repo()->searchOffenses([
            'query' => (string) $request->input('query', ''),
            'stage' => (string) $request->input('stage', ''),
            'cheat_type' => $request->int('cheat_type', 0),
            'status' => (string) $request->input('status', 'all'),
            'sort' => (string) $request->input('sort', 'last_offense_desc'),
        ], $request->int('page', 1), $request->int('per_page', 20));

        return $this->json([
            'success' => true,
            'payload' => [
                'page' => $pager->page,
                'pages' => $pager->pages,
                'total' => $pager->total,
                'items' => $pager->items,
            ],
        ]);
    }

    public function apiEvents(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);

        $pager = $this->repo()->searchEvents([
            'query' => (string) $request->input('query', ''),
            'cheat_type' => $request->int('cheat_type', 0),
            'evidence_level' => (string) $request->input('evidence_level', ''),
            'days' => $request->int('days', 7),
        ], $request->int('page', 1), $request->int('per_page', 20));

        return $this->json([
            'success' => true,
            'payload' => [
                'page' => $pager->page,
                'pages' => $pager->pages,
                'total' => $pager->total,
                'items' => $pager->items,
            ],
        ]);
    }

    public function apiPlayer(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);

        $guid = $request->int('guid', 0);
        $name = trim((string) $request->input('name', ''));
        if ($guid <= 0 && $name === '') {
            return $this->json(['success' => false, 'message' => Lang::get('app.aegis.errors.player_required')], 422);
        }

        $payload = $this->repo()->findPlayerSnapshot($guid > 0 ? $guid : null, $name !== '' ? $name : null);
        if ($payload === null) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 404);
        }

        return $this->json([
            'success' => true,
            'payload' => $payload,
        ]);
    }

    public function apiAction(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);

        $action = strtolower(trim((string) $request->input('action', '')));
        $target = trim((string) $request->input('target', ''));
        $allowed = ['clear', 'delete', 'purge', 'reload'];
        if (!in_array($action, $allowed, true)) {
            return $this->json(['success' => false, 'message' => Lang::get('app.aegis.errors.invalid_action')], 422);
        }

        $resolvedTarget = null;
        if (in_array($action, ['clear', 'delete'], true)) {
            if ($target === '') {
                return $this->json(['success' => false, 'message' => Lang::get('app.aegis.errors.target_required')], 422);
            }

            $resolvedTarget = $this->repo()->resolveCommandTarget($target);
            if ($resolvedTarget === null || ($resolvedTarget['name'] ?? '') === '') {
                return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 404);
            }
        }

        $command = '.aegis ' . $action;
        if ($resolvedTarget !== null) {
            $command .= ' ' . $resolvedTarget['name'];
        }

        $executor = new SoapExecutor();
        $result = $executor->execute($command, [
            'server_id' => ServerContext::currentId(),
            'audit' => true,
        ]);

        Audit::log('aegis', 'command', $action, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'target' => $resolvedTarget['name'] ?? null,
            'target_guid' => $resolvedTarget['guid'] ?? null,
            'success' => $result['success'] ?? false,
            'code' => $result['code'] ?? 'ok',
            'message' => $result['message'] ?? null,
        ]);

        return $this->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => ($result['success'] ?? false)
                ? Lang::get('app.aegis.actions.success')
                : ($result['message'] ?? Lang::get('app.aegis.actions.failure')),
            'payload' => [
                'command' => $command,
                'target' => $resolvedTarget,
                'execution' => $result,
            ],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function apiLog(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auth.errors.not_logged_in')], 403);
        }

        $this->maybeSwitchServer($request);
        $payload = $this->repo()->recentLog($request->int('limit', 80));
        return $this->json([
            'success' => true,
            'payload' => $payload,
        ]);
    }

    private function cheatTypes(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.cheat_type.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.cheat_type.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.cheat_type.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.cheat_type.3')],
            ['value' => 4, 'label' => Lang::get('app.aegis.enums.cheat_type.4')],
            ['value' => 5, 'label' => Lang::get('app.aegis.enums.cheat_type.5')],
            ['value' => 6, 'label' => Lang::get('app.aegis.enums.cheat_type.6')],
            ['value' => 7, 'label' => Lang::get('app.aegis.enums.cheat_type.7')],
            ['value' => 8, 'label' => Lang::get('app.aegis.enums.cheat_type.8')],
            ['value' => 9, 'label' => Lang::get('app.aegis.enums.cheat_type.9')],
        ];
    }

    private function evidenceLevels(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.evidence_level.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.evidence_level.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.evidence_level.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.evidence_level.3')],
        ];
    }

    private function punishStages(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.punish_stage.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.punish_stage.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.punish_stage.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.punish_stage.3')],
            ['value' => 4, 'label' => Lang::get('app.aegis.enums.punish_stage.4')],
            ['value' => 5, 'label' => Lang::get('app.aegis.enums.punish_stage.5')],
        ];
    }

    private function statusFilters(): array
    {
        return [
            ['value' => 'all', 'label' => Lang::get('app.aegis.filters.status.all')],
            ['value' => 'tracked', 'label' => Lang::get('app.aegis.filters.status.tracked')],
            ['value' => 'debuffed', 'label' => Lang::get('app.aegis.filters.status.debuffed')],
            ['value' => 'jailed', 'label' => Lang::get('app.aegis.filters.status.jailed')],
            ['value' => 'banned', 'label' => Lang::get('app.aegis.filters.status.banned')],
            ['value' => 'permanent', 'label' => Lang::get('app.aegis.filters.status.permanent')],
        ];
    }

    private function manualActions(): array
    {
        return [
            ['value' => 'clear', 'label' => Lang::get('app.aegis.manual.actions.clear'), 'needs_target' => true],
            ['value' => 'delete', 'label' => Lang::get('app.aegis.manual.actions.delete'), 'needs_target' => true],
            ['value' => 'reload', 'label' => Lang::get('app.aegis.manual.actions.reload'), 'needs_target' => false],
            ['value' => 'purge', 'label' => Lang::get('app.aegis.manual.actions.purge'), 'needs_target' => false],
        ];
    }
}