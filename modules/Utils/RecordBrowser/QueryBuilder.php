<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_QueryBuilder
{
    protected $tab;
    protected $fields;
    protected $fields_by_id;

    protected $applied_joins = array();
    protected $final_tab;
    protected $tab_alias;

    function __construct($tab, $tab_alias = 'rest')
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($tab);
        $this->fields_by_id = Utils_RecordBrowserCommon::$hash;
        $this->tab_alias = $tab_alias;
    }

    public function build_query(Utils_RecordBrowser_Crits $crits, $order = array(), $admin_filter = '')
    {
        $crits->replace_special_values();

        $tab_with_as = $this->tab.'_data_1 AS ' . $this->tab_alias;
        $this->final_tab = $tab_with_as;

        $callback = array($this, 'build_single_crit_query');
        list($having, $vals) = $crits->to_sql($callback);

        if (!$having) $having = 'true';

        $this->final_tab = str_replace('('. $tab_with_as .')', $tab_with_as, $this->final_tab);
        $where = $admin_filter . "($having)";
        $sql = ' ' . $this->final_tab . ' WHERE ' . $where;

        $order_sql = $this->build_order_part($order);

        return array('sql' => $sql, 'vals' => $vals, 'order' => $order_sql, 'tab' => $this->final_tab, 'where' => $where);
    }

    protected function build_order_part($order)
    {
        foreach ($order as $k => $v) {
            if (!is_string($k)) {
                break;
            }
            if ($k[0] == ':') {
                $order[] = array('column' => $k, 'order' => $k, 'direction' => $v);
            } else {
                $field_label = isset($this->fields_by_id[$k])
                    ?
                    $this->fields_by_id[$k]
                    :
                    $k;
                if (isset($this->fields[$field_label])) {
                    $order[] = array('column' => $field_label, 'order' => $field_label, 'direction' => $v);
                }
            }
            unset($order[$k]);
        }

        $orderby = array();
        $user_id = Base_AclCommon::get_user();

        foreach ($order as $v) {
            if ($v['order'][0] != ':' && !isset($this->fields[$v['order']])) continue;
            if ($v['order'][0] == ':') {
                switch ($v['order']) {
                    case ':id':
                        $orderby[] = ' id ' . $v['direction'];
                        break;
                    case ':Fav' :
                        $orderby[] = ' (SELECT COUNT(*) FROM '.$this->tab.'_favorite WHERE '.$this->tab.'_id='.$this->tab_alias.'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Visited_on'  :
                        $orderby[] = ' (SELECT MAX(visited_on) FROM '.$this->tab.'_recent WHERE '.$this->tab.'_id='.$this->tab_alias.'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Edited_on'   :
                        $orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id='.$this->tab_alias.'.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id='.$this->tab_alias.'.id) ELSE created_on END) '.$v['direction'];
                        break;
                    default     :
                        $orderby[] = ' '.substr($v['order'], 1).' ' . $v['direction'];
                }
            } else {
                $field_def = $this->get_field_definition($v['order']);
                $field_sql_id = 'f_' . $field_def['id'];
                if (isset($field_def['ref_table']) && $field_def['ref_table'] != '__COMMON__') {
                    $tab2 = $field_def['ref_table'];
                    $cols2 = $field_def['ref_field'];
                    $cols2 = explode('|', $cols2);
                    $cols2 = $cols2[0];
                    $field_id = Utils_RecordBrowserCommon::get_field_id($cols2);
                    $val = '(SELECT rdt.f_'.$field_id.' FROM '.$this->tab.'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.'.$field_sql_id.' WHERE '.$this->tab_alias.'.id=rd.id)';
                    $orderby[] = ' '.$val.' '.$v['direction'];
                } else {
                    if ($field_def['type'] == 'currency') {
                        if (DB::is_mysql()) {
                            $field_sql_id = "CAST($field_sql_id as DECIMAL(64,5))";
                        } elseif (DB::is_postgresql()) {
                            $field_sql_id = "CAST(split_part($field_sql_id, '__', 1) as DECIMAL)";
                        }
                    }
                    $orderby[] = ' '.$field_sql_id.' '.$v['direction'];
                }
            }
        }

        if (!empty($orderby)) $orderby = ' ORDER BY'.implode(', ',$orderby);
        else $orderby = '';

        return $orderby;
    }

    public function build_single_crit_query(Utils_RecordBrowser_CritsSingle $crit)
    {
        $special_ret = $this->handle_special_field_crit($crit);
        if ($special_ret) {
            return $special_ret;
        }

        list($field, $sub_field) = $this->parse_subfield_from_field($crit->get_field());

        $field_def = $this->get_field_definition($field);
        if (!$field_def) {
            return array('', array());
        }

        list($sql, $value) = $this->handle_normal_field_crit($field_def, $crit);
        if (!is_array($value)) $value = array($value);
        return array($sql, $value);
    }

    protected function handle_special_field_crit(Utils_RecordBrowser_CritsSingle $crit)
    {
        $field = $crit->get_field();
        $operator = $crit->get_operator();
        $value = $crit->get_value();
        $negation = $crit->get_negation();

        $special = $field[0] == ':' || $field == 'id';
        if ($special) {
            $sql = '';
            $vals = array();
            switch ($field) {
                case ':id' :
                case 'id' :
                    if (!is_array($value)) {
                        $sql = $this->tab_alias.".id $operator %d";
                        $vals[] = $value;
                    } else {
                        if ($operator != '=' && $operator != '==') {
                            throw new Exception("Cannot use array values for id field operator '$operator'");
                        }
                        $clean_vals = array();
                        foreach ($value as $v) {
                            if (is_numeric($v)) {
                                $clean_vals[] = $v;
                            }
                        }
                        if (empty($clean_vals)) {
                            $sql = 'false';
                        } else {
                            $sql = $this->tab_alias.".id IN (" . implode(',', $clean_vals) . ")";
                        }
                    }
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Fav' :
                    $fav = ($value == true);
                    if ($negation) $fav = !$fav;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_favorite AS '.$this->tab_alias.'_fav ON '.$this->tab_alias.'_fav.' . $this->tab . '_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_fav.user_id='. Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $fav ? 'IS NOT NULL' : 'IS NULL';
                    $sql= $this->tab_alias."_fav.fav_id $rule";
                    break;
                case ':Sub' :
                    $sub = ($value == true);
                    if ($negation) $sub = !$sub;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN utils_watchdog_subscription AS '.$this->tab_alias.'_sub ON '.$this->tab_alias.'_sub.internal_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_sub.category_id=' . Utils_WatchdogCommon::get_category_id($this->tab) . ' AND '.$this->tab_alias.'_sub.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $sub ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->tab_alias."_sub.internal_id $rule";
                    break;
                case ':Recent'  :
                    $rec = ($value == true);
                    if ($negation) $rec = !$rec;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_recent AS '.$this->tab_alias.'_rec ON '.$this->tab_alias.'_rec.' . $this->tab . '_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_rec.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $rec ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->tab_alias."_rec.user_id $rule";
                    break;
                case ':Created_on'  :
                    $vals[] = Base_RegionalSettingsCommon::reg2time($value, false);
                    $sql = $this->tab_alias.'.created_on ' . $operator . '%T';
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Created_by'  :
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $sql = array();
                    foreach ($value as $v) {
                        $vals[] = $v;
                        $sql[] = $this->tab_alias.'.created_by = %d';
                    }
                    $sql = implode(' OR ', $sql);
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Edited_on'   :
                    $inj = $operator . '%T';
                    $sql = '(((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id='.$this->tab_alias.'.id) ' . $inj . ') OR ' .
                               '((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id='.$this->tab_alias.'.id) IS NULL AND created_on ' . $inj . '))';
                    $timestamp = Base_RegionalSettingsCommon::reg2time($value, false);
                    if ($negation) {
                        $sql = "NOT (COALESCE($sql, FALSE))";
                    }
                    $vals[] = $timestamp;
                    $vals[] = $timestamp;
                    break;
            }
            return array($sql, $vals);
        }
        return false;
    }

    protected function get_field_definition($field_id_or_label)
    {
        $field_def = null;
        if (isset($this->fields[$field_id_or_label])) {
            $field_def = $this->fields[$field_id_or_label];
        } elseif (isset($this->fields_by_id[$field_id_or_label])) {
            $field_label = $this->fields_by_id[$field_id_or_label];
            $field_def = $this->fields[$field_label];
        }
        return $field_def;
    }

    protected function parse_subfield_from_field($field)
    {
        $field = explode('[', $field);
        $sub_field = isset($field[1]) ? trim($field[1], ']') : false;
        $field = $field[0];
        return array($field, $sub_field);
    }

    protected function get_field_sql($field_name, $cast = null)
    {
        return $this->tab_alias.".f_{$field_name}";
    }

    protected function hf_text($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_integer($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %d";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_float($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %f";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_boolean($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            if ($operator == '=') {
                $sql = "$field IS NULL OR $field=%b";
            } else {
                $sql = "$field IS NOT NULL OR $field!=%b";
            }
            $vals[] = false;
        } else {
            $sql = "$field $operator %b";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_date($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %D $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_timestamp($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %T $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_time($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $field = "CAST($field as time)";
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_currency($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $field_as_int = DB::is_postgresql() ?
                "CAST(split_part($field, '__', 1) AS DECIMAL)" :
                "CAST($field AS DECIMAL(64,5))";
            $value_with_cast = DB::is_postgresql() ?
                "CAST(%s AS DECIMAL)" :
                "CAST(%s AS DECIMAL(64,5))";
            $sql = "($field_as_int $operator $value_with_cast $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_select($field, $operator, $value, $raw_sql_val, $field_def)
    {
        $commondata = isset($field_def['commondata']) && $field_def['commondata'];
        if ($commondata) {
            return $this->hf_commondata($field, $operator, $value, $raw_sql_val, $field_def);
        }

        $sql = '';
        $vals = array();
        list($field, $sub_field) = $this->parse_subfield_from_field($field);
        $multiselect = ($field_def['type'] == 'multiselect');
        $tab2 = isset($field_def['ref_table']) ? $field_def['ref_table'] : false;

        $single_tab = !($tab2 == '__RECORDSETS__' || count(explode(',', $tab2)) > 1);

        if ($sub_field && $single_tab && $tab2) {
            $col2 = explode('|', $sub_field);
            $nested_tab_alias = $this->tab_alias . '_' . $tab2;
            $CB = new Utils_RecordBrowser_QueryBuilder($tab2, $nested_tab_alias);
            $crits = new Utils_RecordBrowser_Crits();
            foreach ($col2 as $col) {
                $col = Utils_RecordBrowserCommon::get_field_id(trim($col));
                if ($col) {
                    $crits->_or(new Utils_RecordBrowser_CritsSingle($col, $operator, $value, false, $raw_sql_val));
                }
            }
            if (!$crits->is_empty()) {
                $subquery = $CB->build_query($crits);
                $on_rule = $multiselect
                    ? "$field LIKE CONCAT('%\\_\\_', $nested_tab_alias.id, '\\_\\_%')"
                    : "$field = $nested_tab_alias.id";
                $this->final_tab .= ' LEFT JOIN (' . $subquery['tab'] . ") ON $on_rule";
                return array($subquery['where'], $subquery['vals']);
            }
        } else {
            if ($raw_sql_val) {
                $sql = "$field $operator $value";
            } elseif (!$value) {
                $sql = "$field IS NULL";
                if (!$single_tab || $multiselect) {
                    $sql .= " OR $field=''";
                }
            } else {
                if ($single_tab && !$multiselect && $operator != DB::like()) {
                    $operand = '%d';
                } else {
                    if (DB::is_postgresql()) {
                        $field .= '::varchar';
                    }
                    $operand = '%s';
                }
                if ($multiselect) {
                    $value = "%\\_\\_{$value}\\_\\_%";
                    $operator = DB::like();
                }
                $sql = "($field $operator $operand AND $field IS NOT NULL)";
                $vals[] = $value;
            }
        }
        return array($sql, $vals);
    }

    protected function hf_commondata($field, $operator, $value, $raw_sql_val, $field_def)
    {
        list($field, $sub_field) = $this->parse_subfield_from_field($field);
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($value === null || $value === false || $value === '') {
            return array("$field IS NULL OR $field=''", array());
        }

        if (!isset($field_def['ref_table'])) { // commondata type doesn't have this, only select/multiselect
            $field_def['ref_table'] = $field_def['param']['array_id'];
        }

        if ($sub_field !== false) { // may be empty string for value lookup with field[]
            $commondata_table = $field_def['ref_table'];
            $ret = Utils_CommonDataCommon::get_translated_array($commondata_table);
            $val_regex = $operator == DB::like() ?
                '/' . preg_quote($value, '/') . '/i' :
                '/^' . preg_quote($value, '/') . '$/i';
            $final_vals = array_keys(preg_grep($val_regex, $ret));
            if ($operator == DB::like()) {
                $operator = '=';
            }
        } else {
            $final_vals = array($value);
        }

        $multiselect = ($field_def['type'] == 'multiselect');
        if ($multiselect) {
            $operator = DB::like();
        }

        $sql = array();
        $vals = array();
        foreach ($final_vals as $val) {
            $sql[] = "$field $operator %s";
            if ($multiselect) {
                $val = "%\\_\\_{$val}\\_\\_%";
            }
            $vals[] = $val;
        }
        $sql_str = implode(' OR ', $sql);
        return array($sql_str, $vals);
    }


    protected function hf_multiple(Utils_RecordBrowser_CritsSingle $crit, $callback, $field_def = null)
    {
        $sql = array();
        $vals = array();

        $field_sql_id = $this->get_field_sql($crit->get_field());
        $operator = $crit->get_operator();
        $raw_sql_val = $crit->get_raw_sql_value();
        $value = $crit->get_value();
        if (is_array($value)) { // for empty array it will give empty result
            $sql[] = 'false';
        } else {
            $value = array($value);
        }
        foreach ($value as $w) {
            $vv = explode('::',$w,2);
            if(isset($vv[1]) && is_callable($vv)) continue;
            
            $args = array($field_sql_id, $operator, $w, $raw_sql_val, $field_def);
            list($sql2, $vals2) = call_user_func_array($callback, $args);
            if ($sql2) {
                $sql[] = $sql2;
                $vals = array_merge($vals, $vals2);
            }
        }
        $sql_str = implode(' OR ', $sql);
        if ($sql_str && $crit->get_negation()) {
            $sql_str = "NOT ($sql_str)";
        }
        return array($sql_str, $vals);

    }

    protected function handle_normal_field_crit($field_def, Utils_RecordBrowser_CritsSingle $crit)
    {
        $ret = array('', array());

        switch ($field_def['type']) {
            case 'autonumber':
            case 'text':
            case 'long text':
                $ret = $this->hf_multiple($crit, array($this, 'hf_text'));
                break;

            case 'integer':
                $ret = $this->hf_multiple($crit, array($this, 'hf_integer'));
                break;

            case 'float':
                $ret = $this->hf_multiple($crit, array($this, 'hf_float'));
                break;

            case 'checkbox':
                $ret = $this->hf_multiple($crit, array($this, 'hf_boolean'));
                break;

            case 'select':
            case 'multiselect':
                $ret = $this->hf_multiple($crit, array($this, 'hf_select'), $field_def);
                break;

            case 'commondata':
                $ret = $this->hf_multiple($crit, array($this, 'hf_commondata'), $field_def);
                break;

            case 'currency':
                $ret = $this->hf_multiple($crit, array($this, 'hf_currency'));
                break;

            case 'date':
                $ret = $this->hf_multiple($crit, array($this, 'hf_date'));
                break;

            case 'timestamp':
                $ret = $this->hf_multiple($crit, array($this, 'hf_timestamp'));
                break;

            case 'time':
                $ret = $this->hf_multiple($crit, array($this, 'hf_time'));
                break;

            default:
                $ret = $this->hf_multiple($crit, array($this, 'hf_text'));
        }

        return $ret;
    }
}