<?php
/*
 * Contact model class
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
 * @version    7.x Last Update: 2026-05-29
 * @filesource /controllers/contacts/contacts.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/contacts/functions.php', 'contactsProcess', 'function');

/**
 * Object wrapper for a single contact, mirroring the journal/inventory classes.
 *
 * A contact is Bizuno's universal entity: one row can hold several role flags
 * at once (ctype_c customer, ctype_v vendor, ctype_b branch, ctype_i CRM,
 * ctype_e employee, ctype_j project, ctype_u user). This v1 class wraps those
 * columns AS-IS - it does not migrate them to *_meta. Addresses and notes live
 * in contacts_meta; CRM log entries in contacts_log.
 *
 * READ methods are open; every WRITE method runs validateAccess() against the
 * security key for the contact type so no public entry point can mutate a
 * contact without the proper permission level.
 *
 *   $c = new contacts($cID);                 // load by record id
 *   $c = new contacts(0, 'ACME', 'v');       // load a vendor by short_name
 *   if ($c->isCustomer()) { ... }
 *   $c->save(['email'=>'a@b.com']);          // gated write (merges into the row)
 */
class contacts
{
    // single-char contact role types mapped to their ctype_* column
    const ROLES = ['c'=>'ctype_c','v'=>'ctype_v','b'=>'ctype_b','i'=>'ctype_i','e'=>'ctype_e','j'=>'ctype_j','u'=>'ctype_u'];

    public  $cID     = 0;     // contacts.id of the loaded contact (0 = none/new)
    public  $contact = [];    // the contacts table row, associative
    public  $type    = 'c';   // single-char context type used for routing/auth
    public  $secID   = 'mgr_c'; // security key checked by every write method

    private $table;

    /**
     * @param integer $cID - contacts record id to load, or 0
     * @param string  $short_name - alternatively load by short_name (when $cID is 0)
     * @param string  $type - single-char role context (c/v/b/i/e/j/u) for auth
     */
    function __construct($cID=0, $short_name='', $type='c')
    {
        $this->table = BIZUNO_DB_PREFIX.'contacts';
        $this->setType($type);
        if (!empty($cID) || !empty($short_name)) { $this->read($cID, $short_name); }
    }

    /**
     * Set the role context used to pick the security key for write operations.
     * @param string $type - single-char role (c/v/b/i/e/j/u)
     */
    public function setType($type='c')
    {
        $this->type  = $type;
        $this->secID = getContactSecID($type);
    }

    /************************************ READ ************************************/
    /**
     * (Re)load the contact row into $this->contact by id or short_name.
     * @param integer $cID - contacts record id
     * @param string  $short_name - short_name (used when $cID is empty)
     * @return array - the loaded row (empty array if not found)
     */
    public function read($cID=0, $short_name='')
    {
        if     (!empty($cID))        { $filter = "id=".intval($cID); }
        elseif (!empty($short_name)) { $filter = "short_name='".addslashes($short_name)."'"; }
        else                         { $this->contact = []; $this->cID = 0; return []; }
        $this->contact = dbGetRow($this->table, $filter);
        if (empty($this->contact)) { $this->contact = []; $this->cID = 0; return []; }
        $this->cID = $this->contact['id'];
        return $this->contact;
    }

    /**
     * @return boolean - true if a contact is currently loaded
     */
    public function exists()
    {
        return !empty($this->contact) && !empty($this->cID);
    }

    /**
     * Return a single field from the loaded row, or the whole row when $field is null.
     * @param string $field - column name, or null for the entire row
     * @param mixed  $default - value returned when the field is not present
     * @return mixed
     */
    public function get($field=null, $default=null)
    {
        if ($field===null) { return $this->contact; }
        return isset($this->contact[$field]) ? $this->contact[$field] : $default;
    }

    /*********************************** ROLES ***********************************/
    /**
     * Test whether the loaded contact carries a given role flag.
     * @param string $type - single-char role (c/v/b/i/e/j/u)
     * @return boolean
     */
    public function hasRole($type)
    {
        if (empty(self::ROLES[$type])) { return false; }
        return $this->exists() && $this->get(self::ROLES[$type])=='1';
    }

    public function isCustomer() { return $this->hasRole('c'); }
    public function isVendor()   { return $this->hasRole('v'); }
    public function isBranch()   { return $this->hasRole('b'); }
    public function isCRM()      { return $this->hasRole('i'); }
    public function isEmployee() { return $this->hasRole('e'); }
    public function isProject()  { return $this->hasRole('j'); }
    public function isUser()     { return $this->hasRole('u'); }

    /**
     * List the single-char role types currently set on the contact.
     * @return array - e.g. ['c','v']
     */
    public function getRoles()
    {
        $out = [];
        foreach (self::ROLES as $type=>$col) { if ($this->get($col)=='1') { $out[] = $type; } }
        return $out;
    }

    /********************************* ADDRESSES *********************************/
    /**
     * Read address records for this contact (stored in contacts_meta as address_*).
     * @param string $type - address type: 'b' billing, 's' shipping
     * @return array
     */
    public function getAddresses($type='b')
    {
        if (!$this->exists()) { return []; }
        $meta = dbMetaGet(0, "address_$type", 'contacts', $this->cID);
        metaIdxClean($meta);
        return $meta;
    }

    /**
     * Read a meta record for this contact from contacts_meta.
     * @param string $key - meta key, e.g. 'notes'
     * @return array
     */
    public function getMeta($key)
    {
        if (!$this->exists()) { return []; }
        $meta = dbMetaGet(0, $key, 'contacts', $this->cID);
        metaIdxClean($meta);
        return $meta;
    }

    /*********************************** WRITES ***********************************/
    /**
     * Insert or update the contact row. Every save is permission-gated:
     * level 2 (add) when creating, level 3 (edit) when updating an existing contact.
     * Always forces the context role flag on (ctype_$type='1'). Auto-generates a
     * short_name for new contacts when none is supplied.
     *
     * @param array $values - field=>value pairs to write. When empty the data is
     *        pulled from the request (back-compat with the controller save path).
     * @param boolean $makeTransaction - wrap the write in a DB transaction
     * @return integer|false - the contacts record id on success, false on failure
     */
    public function save($values=[], $makeTransaction=true)
    {
        if (empty($values)) { $values = requestData(dbLoadStructure($this->table)); }
        $cID = !empty($values['id']) ? intval($values['id']) : $this->cID;
        if (!$security = validateAccess($this->secID, $cID ? 3 : 2)) { return false; }
        if ($this->exists()) { $values = array_replace($this->contact, $values); }
        $values['ctype_'.$this->type] = '1'; // force the context role on
        if (!$cID) { $values['first_date'] = empty($values['first_date']) ? biz_date('Y-m-d') : $values['first_date']; }
        else       { $values['date_last']  = biz_date('Y-m-d'); }
        if (!$cID && empty($values['short_name'])) {
            $values['short_name'] = getNextReference($this->type=='v' ? 'next_vend_id_num' : 'next_cust_id_num');
        }
        if (!empty($values['short_name'])) {
            $dup = dbGetValue($this->table, 'id', "short_name='".addslashes($values['short_name'])."' AND id<>".intval($cID));
            if ($dup) { msgAdd(lang('error_duplicate_id')); return false; }
        } else { unset($values['short_name']); } // existing record, no id passed -> leave as is
        if (empty($values['inactive'])) { $values['inactive'] = 0; }
        if ($makeTransaction) { dbTransactionStart(); }
        $result = dbWrite($this->table, $values, $cID ? 'update' : 'insert', "id=".intval($cID));
        if (!$cID) { $cID = $result; }
        if ($makeTransaction) { dbTransactionCommit(); }
        msgLog(sprintf(lang('tbd_manager'), lang("ctype_{$this->type}"))." - ".lang('save')." - ".(isset($values['short_name'])?$values['short_name']:'')." (rID=$cID)");
        $this->read($cID); // refresh the loaded row
        return $cID;
    }

    /**
     * Delete the loaded contact (and its meta + log + attachments). Level 4 (full).
     * Blocks the delete when the contact is referenced by any journal entry
     * (as buyer, seller, or store), matching the manager screen.
     *
     * @return boolean - true on success, false when blocked or not permitted
     */
    public function delete()
    {
        if (!$security = validateAccess($this->secID, 4)) { return false; }
        if (!$this->exists()) { msgAdd('The record was not deleted, the proper id was not passed!'); return false; }
        $cID = $this->cID;
        $block = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "contact_id_b='$cID' OR contact_id_s='$cID' OR store_id='$cID'");
        if ($block) { msgAdd(lang('err_contacts_delete', 'contacts')); return false; }
        $short_name = $this->get('short_name');
        dbTransactionStart();
        dbDelete($this->table, "id=$cID");
        dbDelete(BIZUNO_DB_PREFIX.'contacts_meta', "ref_id=$cID");
        dbDelete(BIZUNO_DB_PREFIX.'contacts_log',  "contact_id=$cID");
        dbTransactionCommit();
        $files = glob(getModuleCache('contacts', 'properties', 'attachPath', 'contacts')."rID_{$cID}_*.zip");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
        msgLog(sprintf(lang('tbd_manager'), lang("ctype_{$this->type}"))." - ".lang('delete')." - $short_name (rID=$cID)");
        $this->contact = []; $this->cID = 0;
        return true;
    }

    /**
     * Set or clear a role flag on the loaded contact (ctype_$type). Level 3 (edit).
     * @param string  $type - single-char role (c/v/b/i/e/j/u)
     * @param boolean $on - true to set the role, false to clear it
     * @return boolean
     */
    public function setRole($type, $on=true)
    {
        if (empty(self::ROLES[$type])) { return false; }
        if (!$security = validateAccess($this->secID, 3)) { return false; }
        if (!$this->exists()) { return false; }
        $val = $on ? '1' : '0';
        dbWrite($this->table, [self::ROLES[$type]=>$val], 'update', "id=".$this->cID);
        $this->contact[self::ROLES[$type]] = $val;
        return true;
    }
}
