<?php
/*
 * Bizuno Software Update methods
 *
 * Checks GitHub for the latest *production* Bizuno release, surfaces a
 * notification on the settings (gear) icon, renders the "Software Updates"
 * settings panel, and applies an admin-requested update in-place through a
 * stepped progress modal while the site is held in maintenance mode.
 *
 * The file swap runs entirely within the PHP web request (no shell-out, so it
 * works on shared hosting): download -> extract -> validate -> back up -> atomic
 * rename swap -> clear cache + reset opcache -> lift maintenance. Swap AND
 * finalize happen in the SAME step request — which is still executing the
 * current in-memory code — so finalization never depends on code shipped by the
 * newly installed release.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-06-20
 * @filesource /controllers/administrate/updater.php
 */

namespace bizuno;

class administrateUpdater
{
    public    $moduleID = 'administrate';
    public    $pageID   = 'updater';
    protected $secID    = 'admin';

    // GitHub source of truth. releases/latest natively excludes drafts and
    // pre-releases, so it always points at a production release.
    const GH_OWNER   = 'phreesoft';
    const GH_REPO    = 'bizuno';
    const CHECK_TTL  = 86400; // re-poll GitHub at most once per day (see bizRegistry::initRegistry — refresh rides the cache reload)

    function __construct() { }

    /**
     * Returns the cached update status, re-polling GitHub no more than once per
     * CHECK_TTL unless $force is set.
     * @param bool $force - skip the cache and hit GitHub now
     * @return array ['current','latest','updateAvailable','html_url','body','published_at','zipball_url','checked_at']
     */
    public function checkVersion($force=false)
    {
        $cache = getModuleCache('bizuno', 'updates');
        $now   = time();
        if (!$force && !empty($cache['checked_at']) && isset($cache['latest'])
            && ($now - $cache['checked_at']) < self::CHECK_TTL) {
            return $cache; // still fresh, no network call
        }
        $release = $this->fetchLatestRelease();
        if ($release === false) { // GitHub unreachable — keep last known good, flag the error
            if (!empty($cache)) { $cache['error'] = true; return $cache; }
            return ['current'=>MODULE_BIZUNO_VERSION, 'latest'=>'', 'updateAvailable'=>false, 'checked_at'=>$now, 'error'=>true];
        }
        $latest = $release['version'];
        $data   = [
            'current'        => MODULE_BIZUNO_VERSION,
            'latest'         => $latest,
            'updateAvailable'=> version_compare($latest, MODULE_BIZUNO_VERSION, '>'),
            'html_url'       => $release['html_url'],
            'body'           => mb_substr((string)$release['body'], 0, 4000), // keep the config row lean
            'published_at'   => $release['published_at'],
            'zipball_url'    => $release['zipball_url'],
            'checked_at'     => $now];
        setModuleCache('bizuno', 'updates', false, $data);
        return $data;
    }

    /**
     * Hits the GitHub REST API for the latest production release.
     * @return array|false - normalized release fields, or false on any failure
     */
    private function fetchLatestRelease()
    {
        global $io;
        $url  = 'https://api.github.com/repos/'.self::GH_OWNER.'/'.self::GH_REPO.'/releases/latest';
        // Short timeout — this can run during a cache reload (a normal page load), so a slow/unreachable GitHub must not stall the request.
        $opts = ['headers'=>['Accept'=>'application/vnd.github+json', 'X-GitHub-Api-Version'=>'2022-11-28'], 'useragent'=>true, 'CURLOPT_TIMEOUT'=>10];
        $resp = $io->cURL($url, [], 'get', $opts);
        if (empty($resp)) { msgDebug("\nupdater: empty GitHub response"); return false; }
        $json = json_decode($resp, true);
        if (empty($json['tag_name'])) { msgDebug("\nupdater: unexpected GitHub payload = ".print_r($json, true)); return false; }
        return [
            'version'     => ltrim($json['tag_name'], 'vV'), // tags are vX.Y.Z, VERSION file is X.Y.Z
            'html_url'    => isset($json['html_url'])    ? $json['html_url']    : '',
            'body'        => isset($json['body'])        ? $json['body']        : '',
            'published_at'=> isset($json['published_at'])? $json['published_at']: '',
            'zipball_url' => isset($json['zipball_url']) ? $json['zipball_url'] : ''];
    }

    /**
     * Lightweight JSON endpoint polled by the settings (gear) icon after page
     * load to light the update badge. Read-through: it returns the cached status
     * and only hits GitHub if the daily cache reload hasn't seeded it yet
     * (checkVersion self-throttles to once per CHECK_TTL), so the badge poll
     * never drives a network call on a warm cache.
     * Route: administrate/updater/statusJson
     */
    public function statusJson(&$layout=[])
    {
        if (!validateAccess('admin', 3)) { $layout = ['type'=>'raw', 'content'=>json_encode(['available'=>false])]; return; }
        $status = $this->checkVersion();
        $layout = ['type'=>'raw', 'content'=>json_encode([
            'available'=> !empty($status['updateAvailable']),
            'current'  => isset($status['current']) ? $status['current'] : MODULE_BIZUNO_VERSION,
            'latest'   => isset($status['latest'])  ? $status['latest']  : ''])];
    }

    /**
     * Renders the "Software Updates" settings panel, lazy-loaded into a tab on
     * the Bizuno admin settings page.
     * Route: administrate/updater/adminPanel
     */
    public function adminPanel(&$layout=[])
    {
        if (!validateAccess('admin', 3)) { return; }
        $status = $this->checkVersion(clean('force', 'boolean', 'get'));
        $avail  = !empty($status['updateAvailable']);
        $latest = !empty($status['latest']) ? $status['latest'] : '—';
        $notes  = !empty($status['body']) ? nl2br(htmlspecialchars($status['body'])) : '';
        $pubRaw = !empty($status['published_at']) ? substr($status['published_at'], 0, 10) : '';
        if (!empty($status['error'])) {
            $banner = '<div style="padding:8px;background:#fdecea;color:#611a15;border-radius:4px;">'.lang('update_check_failed', $this->moduleID).'</div>';
        } elseif ($avail) {
            $banner = '<div style="padding:8px;background:#fff4e5;color:#663c00;border-radius:4px;font-weight:bold;">'.sprintf(lang('update_available', $this->moduleID), $latest).'</div>';
        } else {
            $banner = '<div style="padding:8px;background:#edf7ed;color:#1e4620;border-radius:4px;">'.lang('update_up_to_date', $this->moduleID).'</div>';
        }
        $rows  = '<table class="ui-widget" style="border-collapse:collapse;margin:10px 0;">';
        $rows .= '<tr><td style="padding:4px 12px;font-weight:bold;">'.lang('update_current_version', $this->moduleID).'</td><td style="padding:4px 12px;">'.MODULE_BIZUNO_VERSION.'</td></tr>';
        $rows .= '<tr><td style="padding:4px 12px;font-weight:bold;">'.lang('update_latest_version', $this->moduleID).'</td><td style="padding:4px 12px;">'.htmlspecialchars($latest);
        if (!empty($status['html_url'])) { $rows .= ' &nbsp;<a href="'.htmlspecialchars($status['html_url']).'" target="_blank">'.lang('view').'</a>'; }
        $rows .= '</td></tr>';
        if ($pubRaw) { $rows .= '<tr><td style="padding:4px 12px;font-weight:bold;">'.lang('update_published', $this->moduleID).'</td><td style="padding:4px 12px;">'.$pubRaw.'</td></tr>'; }
        $rows .= '</table>';
        $html  = '<div style="padding:10px;max-width:760px;">';
        $html .= '<p>'.lang('update_desc', $this->moduleID).'</p>';
        $html .= $banner.$rows;
        if ($notes) { $html .= '<fieldset style="margin-top:10px;"><legend>'.lang('update_release_notes', $this->moduleID).'</legend><div style="max-height:260px;overflow:auto;font-size:13px;">'.$notes.'</div></fieldset>'; }
        $html .= '</div>';
        // "Check now" refreshes this tab's panel in place (no full-page reload).
        $icons = ['refresh'=> ['order'=>10,'label'=>lang('update_check_now', $this->moduleID),'icon'=>'refresh',
            'events'=>['onClick'=>"jqBiz('#tabAdmin').tabs('getSelected').panel('refresh', bizunoAjax+'&bizRt=administrate/updater/adminPanel&force=1');"]]];
        if ($avail) {
            $confirm = sprintf(lang('update_confirm', $this->moduleID), $latest);
            $icons['update'] = ['order'=>20,'label'=>lang('update_now_btn', $this->moduleID),'icon'=>'download',
                'events'=>['onClick'=>"if (confirm('".addslashes($confirm)."')) { cronInit('bizUpdate','administrate/updater/updateStep&step=1'); }"]];
        }
        $data = ['type'=>'divHTML',
            'divs'  => ['body'=>['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbUpdate'],
                'content'=> ['order'=>50,'type'=>'html','html'=>$html]]]],
            'toolbars'=> ['tbUpdate'=>['icons'=>$icons]]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Stepped, progress-bar-driven in-place update. Driven by the cronInit()
     * modal: each call performs one step and returns {percent,msg,baseID,urlID}.
     * The site is held in maintenance mode (see portalCtl) from step 1 until the
     * swap+finalize of step 3 completes or any step aborts.
     * Route: administrate/updater/updateStep&step=N
     */
    public function updateStep(&$layout=[])
    {
        if (!validateAccess('admin', 4)) { return; }
        $step = clean('step', 'cmd', 'get');
        try {
            switch ($step) {
                case '1': return $this->stepPrepare($layout);
                case '2': return $this->stepDownload($layout);
                case '3': return $this->stepInstall($layout);
            }
            return $this->finish($layout, sprintf(lang('update_failed', $this->moduleID), "unknown step '$step'"));
        } catch (\Throwable $e) {
            msgDebug("\nupdater step $step failed: ".$e->getMessage());
            $this->maintenanceOff();
            return $this->finish($layout, sprintf(lang('update_failed', $this->moduleID), $e->getMessage()));
        }
    }

    /** Step 1: pre-flight write checks, enable maintenance, resolve the target. */
    private function stepPrepare(&$layout)
    {
        $status = $this->checkVersion(true);
        if (empty($status['updateAvailable'])) { return $this->finish($layout, lang('update_none', $this->moduleID)); }
        $libDir = rtrim(BIZUNO_FS_LIBRARY, '/\\');
        $parent = dirname($libDir);
        $work   = BIZUNO_DATA.'updates/';
        if (!is_dir($work)) { @mkdir($work, 0750, true); }
        if (!is_writable($parent)) { return $this->finish($layout, sprintf(lang('update_failed', $this->moduleID), "no write permission on $parent")); }
        if (!is_writable($work))   { return $this->finish($layout, sprintf(lang('update_failed', $this->moduleID), "no write permission on $work")); }
        $state = ['target'=>$status['latest'], 'zipball_url'=>$status['zipball_url'], 'libDir'=>$libDir,
            'parent'=>$parent, 'base'=>basename($libDir), 'ts'=>date('Ymd-His'), 'work'=>$work];
        $this->putState($state);
        $this->maintenanceOn($status['latest']);
        return $this->stepResponse($layout, 20, sprintf(lang('update_step_prep', $this->moduleID), $status['latest']), 2);
    }

    /** Step 2: download the release zipball to the work dir. */
    private function stepDownload(&$layout)
    {
        global $io;
        $st = $this->getState();
        if (empty($st['zipball_url'])) { throw new \Exception('missing update state'); }
        $zipPath = $st['work'].'bizuno-'.$st['target'].'-'.$st['ts'].'.zip';
        // GitHub's zipball_url 302-redirects to codeload, so follow redirects.
        $bytes = $io->cURL($st['zipball_url'], [], 'get', ['CURLOPT_FOLLOWLOCATION'=>true, 'useragent'=>true]);
        if (empty($bytes) || strlen($bytes) < 1024) { throw new \Exception('download failed or archive too small'); }
        if (file_put_contents($zipPath, $bytes) === false) { throw new \Exception("could not write $zipPath"); }
        $st['zipPath'] = $zipPath;
        $this->putState($st);
        return $this->stepResponse($layout, 55, sprintf(lang('update_step_download', $this->moduleID), $st['target']), 3);
    }

    /**
     * Step 3: extract, validate, back up, swap, then finalize — all in this one
     * request so finalization runs the current (in-memory) code, never the code
     * shipped by the release just installed.
     */
    private function stepInstall(&$layout)
    {
        $st = $this->getState();
        if (empty($st['zipPath']) || !is_file($st['zipPath'])) { throw new \Exception('downloaded archive missing'); }
        $stageDir = $st['work'].'stage-'.$st['ts'];
        $zip = new \ZipArchive();
        if ($zip->open($st['zipPath']) !== true) { throw new \Exception('could not open the downloaded archive'); }
        $this->rrmdir($stageDir);
        @mkdir($stageDir, 0750, true);
        $zip->extractTo($stageDir);
        $zip->close();
        // GitHub zipballs wrap everything in a single top folder: <owner>-<repo>-<sha>/
        $tops = array_values(array_filter(glob($stageDir.'/*'), 'is_dir'));
        if (count($tops) !== 1) { $this->rrmdir($stageDir); throw new \Exception('unexpected archive layout'); }
        $newSrc = $tops[0].'/src';
        if (!is_dir($newSrc) || !is_file($newSrc.'/bizunoCFG.php') || !is_file($newSrc.'/VERSION')) {
            $this->rrmdir($stageDir);
            throw new \Exception('the release archive does not contain a valid src/ library');
        }
        // Copy the staged library next to the live one so the final swap is a
        // same-filesystem rename (data/ may be on a different mount than src/).
        $newDir = $st['parent'].'/.'.$st['base'].'-new-'.$st['ts'];
        $oldDir = $st['parent'].'/'.$st['base'].'.old-'.$st['ts'];
        $this->rrmdir($newDir);
        if (!$this->rcopy($newSrc, $newDir)) { $this->rrmdir($stageDir); $this->rrmdir($newDir); throw new \Exception('failed staging the new library'); }
        // --- the swap, with automatic rollback ---
        if (!@rename($st['libDir'], $oldDir)) { $this->rrmdir($stageDir); $this->rrmdir($newDir); throw new \Exception('could not move the current library aside'); }
        if (!@rename($newDir, $st['libDir'])) {
            @rename($oldDir, $st['libDir']); // put it back exactly as it was
            $this->rrmdir($stageDir); $this->rrmdir($newDir);
            throw new \Exception('could not install the new library — rolled back');
        }
        // --- finalize (still running the pre-swap code already loaded in memory) ---
        if (function_exists('\\bizuno\\bizCacheExpClear')) { bizCacheExpClear(); } // rebuild + run migrations next request
        if (function_exists('opcache_reset')) { @opcache_reset(); }               // so new PHP files actually load
        $this->rrmdir($stageDir);
        @unlink($st['zipPath']);
        $this->maintenanceOff();
        $this->clearState();
        msgLog(sprintf('Bizuno updated to %s (backup: %s)', $st['target'], $oldDir));
        return $this->finish($layout, sprintf(lang('update_complete', $this->moduleID), $st['target']), true);
    }

    /** Build the progress-bar JSON for an intermediate step. */
    private function stepResponse(&$layout, $percent, $msg, $next)
    {
        $layout = ['content'=>['percent'=>(int)$percent, 'msg'=>$msg, 'baseID'=>'bizUpdate',
            'urlID'=>'administrate/updater/updateStep&step='.$next]];
    }

    /** Build the terminal (100%) progress JSON with a Reload (success) or Close (error) button. */
    private function finish(&$layout, $msg, $reload=false)
    {
        $btn = $reload
            ? '<p style="text-align:center;margin-top:12px;"><a href="javascript:window.location.reload();" class="easyui-linkbutton" data-options="iconCls:\'icon-reload\'">'.lang('update_reload', $this->moduleID).'</a></p>'
            : '<p style="text-align:center;margin-top:12px;"><a href="javascript:bizWindowClose(\'winbizUpdate\');" class="easyui-linkbutton">'.lang('close').'</a></p>';
        $layout = ['content'=>['percent'=>100, 'msg'=>'<div style="text-align:center">'.$msg.'</div>'.$btn, 'baseID'=>'bizUpdate', 'urlID'=>'']];
    }

    // ───────────────────────── maintenance + state ─────────────────────────

    /** Write the maintenance lock; portalCtl gates every other user until it's cleared. */
    private function maintenanceOn($target)
    {
        @file_put_contents(BIZUNO_DATA.'updates/maintenance.json',
            json_encode(['by'=>getUserCache('profile', 'userID'), 'at'=>time(), 'target'=>$target]));
    }

    private function maintenanceOff() { @unlink(BIZUNO_DATA.'updates/maintenance.json'); }

    private function putState($state)  { @file_put_contents(BIZUNO_DATA.'updates/state.json', json_encode($state)); }
    private function clearState()      { @unlink(BIZUNO_DATA.'updates/state.json'); }
    private function getState()
    {
        $file = BIZUNO_DATA.'updates/state.json';
        if (!is_file($file)) { throw new \Exception('update state lost between steps'); }
        $st = json_decode((string)file_get_contents($file), true);
        if (empty($st['target'])) { throw new \Exception('update state is corrupt'); }
        return $st;
    }

    // ───────────────────────── filesystem helpers ─────────────────────────

    /** Recursively copy a directory tree. Returns false on any failure. */
    private function rcopy($src, $dst)
    {
        if (!is_dir($src)) { return false; }
        if (!is_dir($dst) && !@mkdir($dst, 0750, true) && !is_dir($dst)) { return false; }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $to = $dst.'/'.substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) { if (!is_dir($to) && !@mkdir($to, 0750, true) && !is_dir($to)) { return false; } }
            else                { if (!@copy($item->getPathname(), $to)) { return false; } }
        }
        return true;
    }

    /** Recursively delete a directory tree (best effort). */
    private function rrmdir($dir)
    {
        if (!is_dir($dir)) { return; }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($dir);
    }
}
