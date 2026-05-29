<?php
/*
 * Inventory item model class
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
 * @filesource /controllers/inventory/inventory.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'inventoryProcess', 'function');

/**
 * Object wrapper for a single inventory item, mirroring the journal class.
 *
 * Centralizes the dozens of scattered direct hits to the inventory table so
 * callers work with an object instead of raw SQL. READ methods are open (they
 * only return data); every WRITE method runs validateAccess() so that no public
 * entry point can mutate the item without the proper permission level.
 *
 *   $inv = new inventory($rID);            // load by record id
 *   $inv = new inventory(0, 'WIDGET-01');  // or load by sku
 *   $qty = $inv->qtyAvailable();
 *   $inv->save(['full_price'=>9.99]);      // gated write (merges into the row)
 */
class inventory
{
    public  $rID   = 0;       // inventory.id of the loaded item (0 = none/new)
    public  $sku   = '';      // inventory.sku of the loaded item
    public  $item  = [];      // the inventory table row, associative
    public  $secID = 'inv_mgr'; // security key checked by every write method

    private $table;

    /**
     * @param integer $rID - inventory record id to load, or 0
     * @param string  $sku - alternatively load by SKU (used when $rID is 0)
     */
    function __construct($rID=0, $sku='')
    {
        $this->table = BIZUNO_DB_PREFIX.'inventory';
        if (!empty($rID) || !empty($sku)) { $this->read($rID, $sku); }
    }

    /************************************ READ ************************************/
    /**
     * (Re)load the inventory row into $this->item by id or sku.
     * @param integer $rID - inventory record id
     * @param string  $sku - sku (used when $rID is empty)
     * @return array - the loaded row (empty array if not found)
     */
    public function read($rID=0, $sku='')
    {
        if     (!empty($rID)) { $filter = "id=".intval($rID); }
        elseif (!empty($sku)) { $filter = "sku='".addslashes($sku)."'"; }
        else                  { $this->item = []; $this->rID = 0; $this->sku = ''; return []; }
        $this->item = dbGetRow($this->table, $filter);
        if (empty($this->item)) { $this->item = []; $this->rID = 0; $this->sku = ''; return []; }
        $this->rID = $this->item['id'];
        $this->sku = $this->item['sku'];
        return $this->item;
    }

    /**
     * @return boolean - true if an item is currently loaded
     */
    public function exists()
    {
        return !empty($this->item) && !empty($this->rID);
    }

    /**
     * Return a single field from the loaded row, or the whole row when $field is null.
     * @param string $field - column name, or null for the entire row
     * @param mixed  $default - value returned when the field is not present
     * @return mixed
     */
    public function get($field=null, $default=null)
    {
        if ($field===null) { return $this->item; }
        return isset($this->item[$field]) ? $this->item[$field] : $default;
    }

    /******************************* QUANTITIES / COST ****************************/
    /**
     * Quantity available to sell (stock less commitments, plus buildable assemblies).
     * Delegates to availableQty() so the long-standing calculation is unchanged.
     * @param array $args - overrides: incAssy, incCommit
     * @return float
     */
    public function qtyAvailable($args=[])
    {
        if (!$this->exists()) { return 0; }
        return availableQty($this->item, $args);
    }

    /**
     * Current stock levels broken out by store (wraps getStoreStock()).
     * @param mixed $newCost - optional known cost to avoid an extra query
     * @return array
     */
    public function storeStock($newCost=false)
    {
        if (empty($this->sku)) { return []; }
        return getStoreStock($this->sku, $newCost);
    }

    /**
     * Quantity on open SO (jID 10) / PO (jID 6) by store (wraps getStoreOnOrder()).
     * @param integer $jID - journal id, 10 = sales orders, 6 = purchases
     * @return array
     */
    public function storeOnOrder($jID=10)
    {
        if (empty($this->sku)) { return []; }
        return getStoreOnOrder($this->sku, $jID);
    }

    /**
     * Net quantity in stock for a single store (wraps dbGetStoreQtyStock()).
     * @param integer $storeID - store id, -1 = all stores
     * @return float
     */
    public function storeQtyStock($storeID=-1)
    {
        if (empty($this->sku)) { return 0; }
        return dbGetStoreQtyStock($this->sku, $storeID);
    }

    /**
     * Item unit cost (inventory.item_cost).
     * @return float
     */
    public function cost()
    {
        return $this->exists() ? (float)$this->get('item_cost', 0) : 0;
    }

    /**
     * Rolled-up cost of an assembly's bill of materials (wraps dbGetInvAssyCost()).
     * @return float
     */
    public function assemblyCost()
    {
        return $this->exists() ? dbGetInvAssyCost($this->rID) : 0;
    }

    /************************************* META ***********************************/
    /**
     * Read a meta record for this item from inventory_meta (wraps getMetaInventory()).
     * @param string $key - meta key, e.g. 'bill_of_materials'
     * @return array
     */
    public function getMeta($key)
    {
        return $this->exists() ? getMetaInventory($this->rID, $key) : [];
    }

    /**
     * Read this item's bill of materials.
     * @return array
     */
    public function getBOM()
    {
        return $this->getMeta('bill_of_materials');
    }

    /*********************************** WRITES ***********************************/
    /**
     * Insert or update the inventory row. Every save is permission-gated:
     * level 2 (add) when creating, level 3 (edit) when updating an existing item.
     *
     * @param array $values - field=>value pairs to write. When empty the data is
     *        pulled from the request (back-compat with the controller save path).
     * @param boolean $makeTransaction - wrap the write in a DB transaction
     * @return integer|false - the inventory record id on success, false on failure
     */
    public function save($values=[], $makeTransaction=true)
    {
        if (empty($values)) { $values = requestData(dbLoadStructure($this->table)); }
        $rID = !empty($values['id']) ? intval($values['id']) : $this->rID;
        if (!$security = validateAccess($this->secID, $rID ? 3 : 2)) { return false; }
        // merge over the currently loaded row so partial updates keep existing values
        if ($this->exists()) { $values = array_replace($this->item, $values); }
        if (empty($values['sku'])) { msgAdd(lang('err_inv_sku_blank', 'inventory')); return false; }
        $dup = dbGetValue($this->table, 'sku', "sku='".addslashes($values['sku'])."' AND id<>".intval($rID));
        if ($dup) { msgAdd(lang('error_duplicate_id')); return false; }
        // these columns are maintained by the journal/posting engine, never by a direct save
        foreach (['qty_stock','qty_po','qty_so','qty_alloc','last_journal_date'] as $field) { unset($values[$field]); }
        if (!$rID) { $values['creation_date'] = biz_date('Y-m-d h:i:s'); }
        else       { $values['last_update']   = biz_date('Y-m-d h:i:s'); }
        if ($makeTransaction) { dbTransactionStart(); }
        $result = dbWrite($this->table, $values, $rID ? 'update' : 'insert', "id=".intval($rID));
        if (!$rID) { $rID = $result; }
        if ($makeTransaction) { dbTransactionCommit(); }
        msgLog(lang('inventory').'-'.lang('save')." - ".$values['sku']." (rID=$rID)");
        $this->read($rID); // refresh the loaded row
        return $rID;
    }

    /**
     * Delete the loaded inventory item (and its meta + attachments). Level 4 (full).
     * Blocks the delete when the SKU is a component of an assembly BOM or has GL
     * (journal_item) history for a stock-tracked type, matching the manager screen.
     *
     * @return boolean - true on success, false when blocked or not permitted
     */
    public function delete()
    {
        if (!$security = validateAccess($this->secID, 4)) { return false; }
        if (!$this->exists()) { msgAdd('Bad Record ID!'); return false; }
        $rID = $this->rID;
        $sku = $this->sku;
        // block if this SKU is a component in any assembly bill of materials
        $boms = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_meta', "meta_key='bill_of_materials'");
        foreach ($boms as $row) {
            $bom = json_decode($row['meta_value'], true);
            if (empty($bom)) { continue; }
            foreach ($bom as $value) {
                if (is_array($value) && isset($value['sku']) && $value['sku']==$sku) {
                    msgAdd(sprintf(lang('err_inv_delete_assy', 'inventory'), $sku));
                    return false;
                }
            }
        }
        // block if the SKU has GL history and is a stock-tracked inventory type
        $hasGL = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='".addslashes($sku)."'");
        if ($sku && $hasGL && in_array($this->get('inventory_type'), INVENTORY_COGS_TYPES)) {
            msgAdd(sprintf(lang('err_inv_delete_gl_entry', 'inventory'), $sku));
            return false;
        }
        dbTransactionStart();
        dbDelete($this->table, "id=$rID");
        dbDelete(BIZUNO_DB_PREFIX.'inventory_meta', "ref_id=$rID");
        dbTransactionCommit();
        $files = glob(getModuleCache('inventory', 'properties', 'attachPath', 'inventory')."rID_{$rID}_*.*");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
        msgLog(lang('inventory').' '.lang('delete')." - $sku ($rID)");
        $this->item = []; $this->rID = 0; $this->sku = '';
        return true;
    }

    /**
     * Set the allocated quantity (inventory.qty_alloc). Level 3 (edit).
     * Centralizes the previously ungated tools/qtyAllocRepair write.
     * @param float $qty - new allocated quantity
     * @return boolean
     */
    public function setQtyAlloc($qty)
    {
        if (!$security = validateAccess($this->secID, 3)) { return false; }
        if (!$this->exists()) { return false; }
        dbWrite($this->table, ['qty_alloc'=>(float)$qty], 'update', "id=".$this->rID);
        $this->item['qty_alloc'] = (float)$qty;
        return true;
    }

    /**
     * Save the list of image paths (inventory.invImages). Level 3 (edit).
     * Centralizes the previously ungated images/imagesLoad write. Only paths that
     * resolve to a real file under BIZUNO_DATA/images are stored.
     * @param array $paths - relative image paths
     * @return boolean
     */
    public function setImages($paths=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return false; }
        if (!$this->exists()) { return false; }
        $output = [];
        foreach ((array)$paths as $path) {
            if (!empty($path) && file_exists(BIZUNO_DATA."images/$path")) { $output[] = $path; }
        }
        dbWrite($this->table, ['invImages'=>json_encode($output)], 'update', "id=".$this->rID);
        $this->item['invImages'] = json_encode($output);
        return true;
    }

    /**
     * Save the product attribute set (inventory.bizProAttr). Level 3 (edit).
     * Centralizes the previously ungated attributes/adminAttrSave write.
     * @param array $attr - ['category'=>..., 'attrs'=>[...]]
     * @return boolean
     */
    public function setAttributes($attr=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return false; }
        if (!$this->exists()) { return false; }
        dbWrite($this->table, ['bizProAttr'=>json_encode($attr)], 'update', "id=".$this->rID);
        $this->item['bizProAttr'] = json_encode($attr);
        return true;
    }
}
