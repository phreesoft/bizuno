<?php
/*
 * This method handles user profiles
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
 * @version    7.x Last Update: 2026-05-26 (in-app password change in profile editor)
 * @filesource /controllers/bizuno/profile.php
 */

namespace bizuno;

class bizunoProfile extends mgrJournal
{
    public    $moduleID  = 'bizuno';
    public    $pageID    = 'profile';
    protected $domSuffix = 'Profile';
    protected $metaPrefix= 'user_profile';

    function __construct()
    {
        parent::__construct();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $periods= viewKeyDropdown(localeDates(true, false, true, true, false));
        $values = [10,20,30,40,50]; // This must match the values set in the UI (EasyUI for now), [10,20,30,40,50] is the default
        foreach ($values as $value) {$rows[] = ['id'=>$value, 'text'=>$value]; }
        $mail = new bizunoMailer();
        $this->struc = [
            'title'      => ['tab'=>'options','panel'=>'general','order'=>10, 'clean'=>'text',     'attr'=>['value'=>getUserCache('profile', 'userName'),'readonly'=>true]],
            'email'      => ['tab'=>'options','panel'=>'general','order'=>15, 'clean'=>'email',    'attr'=>['value'=>getUserCache('profile', 'email'),   'readonly'=>true]],
            'user_pin'   => ['tab'=>'options','panel'=>'general','order'=>20, 'clean'=>'integer',  'attr'=>['type'=>'password','value'=>'']],
            'language'   => ['tab'=>'options','panel'=>'general','order'=>25, 'clean'=>'db_field', 'attr'=>['type'=>'select',  'value'=>'en_US'],  'values'=>viewLanguages(),'options'=>['width'=>300]],
            'def_periods'=> ['tab'=>'options','panel'=>'general','order'=>30, 'clean'=>'db_field', 'attr'=>['type'=>'select',  'value'=>'l'],      'values'=>$periods],
            'grid_rows'  => ['tab'=>'options','panel'=>'general','order'=>35, 'clean'=>'integer',  'attr'=>['type'=>'select',  'value'=>20],       'values'=>$rows],
            'icons'      => ['tab'=>'options','panel'=>'general','order'=>40, 'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>'default'],'values'=>portalIcons()],
            'theme'      => ['tab'=>'options','panel'=>'general','order'=>45, 'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>'auto'],   'values'=>portalSkins()],
            // ─── Change password ─────────────────────────────────────────
            // These three fields live in their own "password" panel under
            // the same "options" tab. They are NEVER persisted to the
            // user_profile meta — save() handles them separately, hashing
            // the new value into user_auth meta (the same place login
            // verification reads from), then unsets them before the rest
            // of the profile fields get written. metaUpdate() in turn
            // already refuses to refill values into password-typed inputs
            // (functions.php ~line 687), so the new-password fields render
            // empty on every page load — write-only behaviour by default.
            'bizPassCur' => ['tab'=>'options','panel'=>'password','order'=>10, 'clean'=>'text', 'label'=>lang('password_current'),  'attr'=>['type'=>'password','value'=>'','autocomplete'=>'current-password','placeholder'=>lang('password_current')]],
            'bizPass0'   => ['tab'=>'options','panel'=>'password','order'=>20, 'clean'=>'text', 'label'=>lang('password_new'),      'attr'=>['type'=>'password','value'=>'','autocomplete'=>'new-password',    'placeholder'=>lang('password_new')]],
            'bizPass1'   => ['tab'=>'options','panel'=>'password','order'=>30, 'clean'=>'text', 'label'=>lang('password_retype'),   'attr'=>['type'=>'password','value'=>'','autocomplete'=>'new-password',    'placeholder'=>lang('password_retype')]],
        ];
        langFillLabels($this->struc);
        $this->struc = array_replace($this->struc, $mail->struc); // bring in the mail settings
    }
    
    public function edit(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        $metaVal = dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nRead meta pre process = ".print_r($metaVal, true));
        $rID  = metaIdxClean($metaVal); // need a rID since we enter directly from a menu selection
        $args = ['dom'=>'page', '_rID'=>$rID, '_table'=>'contacts', '_refID'=>getUserCache('profile', 'userID'), 'title'=>lang('edit_profile')];
        parent::editMeta($layout, 3, $args);
        msgDebug("\nAfter editMeta, layout = ".msgPrint($layout));
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['reminders'] = ['order'=>50,'label'=>lang('reminders', $this->moduleID),'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=bizuno/reminder/manager'"]];
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow new, copy here
        $layout['fields']['gmail_app_pw2']['attr']['type'] = 'hidden'; // not used for profile
        $layout['fields']['gmail_app_pw3']['attr']['type'] = 'hidden';
        $layout['fields']['gmail_app_pw4']['attr']['type'] = 'hidden';
    }
        
    public function save()
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        if (empty(clean('user_pin', 'text', 'post'))) { unset($this->struc['user_pin']); } // only update password if it there is a value, otherwise keep the value
        // Process password change (if any) BEFORE persisting profile meta.
        // The password fields are unset from $this->struc after this so they
        // never get written into the user_profile blob — they belong in
        // user_auth meta instead, written via the same encryptPassword()
        // helper viewMaint.php and the recovery script both use.
        $this->savePasswordChange();
        $metaVal= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
//if (!isset($metaVal['title']) && isset($metaVal[0])) { msgDebug("\nMeta profile is malformed, fixing it."); $metaVal = array_shift($metaVal); }
        $rID    = metaIdxClean($metaVal);
        $newVal = metaUpdate($metaVal, $this->struc);
        $output = array_replace($metaVal, $newVal);
        msgDebug("\nWriting fetched metaVal = ".print_r($output, true));
        dbMetaSet($rID, $this->metaPrefix, $output, 'contacts', getUserCache('profile', 'userID'));
        dbSetBizunoUsers();
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("$this->mgrTitle - ".lang('save').": {$output['title']}");
    }

    /**
     * Handle the password-change fields on profile save.
     *
     * The three fields (bizPassCur / bizPass0 / bizPass1) are transient —
     * they exist for the form but never persist into user_profile meta.
     * This method:
     *
     *   1. Removes bizPass* from $this->struc unconditionally so the caller's
     *      metaUpdate() doesn't try to round-trip them into user_profile.
     *   2. If the user didn't fill any of them, return early — they're
     *      just saving other profile settings.
     *   3. Otherwise, validate: current must match the stored hash, new
     *      must equal confirm, new must be ≥ 8 chars. Any failure adds a
     *      message to $msgStack and returns without writing — the rest
     *      of the profile save still completes (the user's typo
     *      shouldn't lose their theme change).
     *   4. On success, write the new hash into user_auth meta — same
     *      shape Bizuno's login validator + the lost-password reset both
     *      use, so the next login picks it up cleanly.
     */
    protected function savePasswordChange()
    {
        // Always strip the password fields from $this->struc so they don't
        // leak into the user_profile blob.
        //
        // Cleaner type 'password' — verbatim string (no trim, no slash-strip
        // — significant whitespace in a password should not be silently
        // mangled) AND returns '' for missing/empty POST values, not null.
        // The text cleaner returns null for empty input, which broke an
        // earlier `=== ''` check and surfaced as a spurious "too short"
        // error on profile saves where the user didn't touch the password.
        $cur = (string) clean('bizPassCur', 'password', 'post');
        $new = (string) clean('bizPass0',   'password', 'post');
        $cnf = (string) clean('bizPass1',   'password', 'post');
        unset($this->struc['bizPassCur'], $this->struc['bizPass0'], $this->struc['bizPass1']);
        // Nothing submitted → nothing to do. empty() handles both '' and
        // null defensively in case a future cleaner change shifts the
        // representation again.
        if (empty($cur) && empty($new) && empty($cnf)) { return; }
        // Partial submission → tell the user, abort password update but let
        // the rest of the profile save continue.
        if (empty($cur) || empty($new) || empty($cnf)) {
            return msgAdd(lang('err_password_fields_required'));
        }
        if ($new !== $cnf)        { return msgAdd(lang('err_password_mismatch')); }
        if (strlen($new) < 8)     { return msgAdd(lang('err_password_short')); }
        // Verify the current password against the stored hash. Same shape
        // as src/portal/viewAuth.php validateUser(): pepper with BIZUNO_KEY,
        // then password_verify against the user_auth meta value.
        $uID    = getUserCache('profile', 'userID');
        $stored = dbMetaGet(0, 'user_auth', 'contacts', $uID);
        $rID    = metaIdxClean($stored);
        $peppered = hash_hmac('sha256', $cur, BIZUNO_KEY);
        if (!password_verify($peppered, $stored['value'] ?? '')) {
            return msgAdd(lang('err_password_current_wrong'));
        }
        // All checks passed → write the new hash.
        dbMetaSet($rID, 'user_auth', encryptPassword($new), 'contacts', $uID);
        msgAdd(lang('msg_password_changed'), 'success');
        msgLog("$this->mgrTitle - ".lang('msg_password_changed'));
    }
    public function update()
    {
        $menuSize = clean('menuSize', 'cmd', 'get');
        msgDebug("\nread menuSize = $menuSize");
        $metaVal= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
        $rID    = metaIdxClean($metaVal);
        if (!empty($menuSize)) { $metaVal['menuSize'] = $menuSize; }
        msgDebug("\nWriting fetched metaVal = ".print_r($metaVal, true));
        dbMetaSet($rID, $this->metaPrefix, $metaVal, 'contacts', getUserCache('profile', 'userID'));
    }
}
