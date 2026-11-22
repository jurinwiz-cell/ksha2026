<!-- Tables -->
<div class="section_box" id="sec_tables">
    <h3>Tables</h3>
    <table class="devtool_info_table">
        <thead><tr><th>Name</th><th>Alias</th><th style="width:50px;"></th></tr></thead>
        <tbody id="tbody_tables"></tbody>
    </table>
    <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addTable()">+ Add Table</button></div>
</div>

<!-- Columns -->
<div class="section_box" id="sec_columns" style="margin-top:20px;">
    <h3>Columns</h3>
    <table class="devtool_info_table">
        <thead><tr><th>Name</th><th class="col_alias">Alias</th><th class="col_extra">Var</th><th class="col_extra">Default</th><th class="col_extra">Filter</th><th class="col_extra">Not Null</th><th style="width:50px;"></th></tr></thead>
        <tbody id="tbody_columns"></tbody>
    </table>
    <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addColumn()">+ Add Column</button></div>
</div>

<!-- Conditions -->
<div class="section_box" id="sec_conditions" style="margin-top:20px;">
    <h3>Conditions</h3>
    <table class="devtool_info_table">
        <thead><tr><th style="width:25%;">Column</th><th>Var</th><th style="width:60px; text-align:center;">Not Null</th><th>Operation</th><th>Default</th><th>Filter</th><th>Pipe</th><th style="width:50px;"></th></tr></thead>
        <tbody id="tbody_conditions"></tbody>
    </table>
    <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addCondition()">+ Add Condition</button></div>
</div>

<!-- Navigation -->
<div class="section_box" id="sec_navigation" style="margin-top:20px;">
    <h3>Navigation</h3>
    <table class="devtool_info_table">
        <thead><tr><th>Tag</th><th>Var</th><th>Default</th><th>Order</th><th style="width:50px;"></th></tr></thead>
        <tbody id="tbody_navigation"></tbody>
    </table>
    <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addNavigation()">+ Add Nav Item</button></div>
</div>

<script>
var autoIdGeneration = false;
var tableSchemas = {};
var tableSchemaDetails = {};
var availableColumnOptions = [];
var rowIdCounter = 0;

function renderRows(tbodyId, data, htmlFn) {
    var tbody = document.getElementById(tbodyId);
    tbody.innerHTML = '';
    if(!data) return;
    data.forEach(function(item) {
        var tr = document.createElement('tr');
        var idx = getNextRowId();
        tr.setAttribute('data-row-idx', idx);
        tr.innerHTML = htmlFn(item, idx);
        tbody.appendChild(tr);
    });
}

function tableRowHtml(item, idx) {
    var name = item.name || '';
    var isCustom = name && !schemaList.includes(name);
    
    var options = '<option value="">(Select Table)</option>';
    schemaList.forEach(function(s) {
        options += `<option value="${escapeHtml(s)}" ${s === name ? 'selected' : ''}>${escapeHtml(s)}</option>`;
    });
    options += `<option value="_custom_">(Custom)</option>`;

    var displaySelect = isCustom ? 'none' : 'block';
    var displayText = isCustom ? 'block' : 'none';

    return `
        <td>
            <div id="table_wrapper_${idx}">
                <select class="devtool_select" style="display:${displaySelect}; width:100%;" onchange="onTableNameSelectChange(this, ${idx})">
                    ${options}
                </select>
                <div class="custom_input_wrapper" style="display:${displayText}; width:100%; position:relative;">
                    <input type="text" id="table_input_${idx}" value="${escapeHtml(name)}" class="devtool_input" style="width:100%; padding-right:30px;" onchange="onTableInputChange(this, ${idx})">
                    <button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetTableSelect(${idx})">X</button>
                </div>
                <input type="hidden" name="tables[${idx}][name]" id="real_table_name_${idx}" value="${escapeHtml(name)}">
            </div>
        </td>
        <td><input type="text" name="tables[${idx}][alias]" value="${escapeHtml(item.alias||'')}" class="devtool_input" oninput="updateAvailableColumns()"></td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="removeTableRow(this, ${idx})">Del</button></td>
    `;
}

function columnRowHtml(item, idx) {
    var action = document.getElementById('input_action').value;
    var displayAlias = (action === 'select') ? '' : 'none';
    var displayExtra = (action === 'select') ? 'none' : '';
    
    var name = item.name || '';
    var isCustom = name && !availableColumnOptions.some(opt => opt.value === name);
    var displaySelect = isCustom ? 'none' : 'block';
    var displayText = isCustom ? 'block' : 'none';

    return `
        <td>
            <div id="col_name_wrapper_${idx}">
                <select class="devtool_select column_name_select" style="display:${displaySelect}; width:100%;" onchange="onColumnNameSelectChange(this, ${idx})">
                    ${generateColumnOptionsHtml(name)}
                </select>
                <div class="custom_input_wrapper" style="display:${displayText}; width:100%; position:relative;">
                    <input type="text" value="${escapeHtml(name)}" class="devtool_input" style="width:100%; padding-right:30px;" oninput="onColumnNameInputChange(this, ${idx})">
                    <button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetColumnNameSelect(${idx})">X</button>
                </div>
                <input type="hidden" name="columns[${idx}][name]" id="real_col_name_${idx}" value="${escapeHtml(name)}">
            </div>
        </td>
        <td class="col_alias" style="display:${displayAlias}"><input type="text" name="columns[${idx}][alias]" value="${escapeHtml(item.alias||'')}" class="devtool_input"></td>
        <td class="col_extra" style="display:${displayExtra}"><input type="text" name="columns[${idx}][var]" id="col_var_${idx}" value="${escapeHtml(item.var||'')}" class="devtool_input"></td>
        <td class="col_extra" style="display:${displayExtra}"><input type="text" name="columns[${idx}][default]" value="${escapeHtml(item.default||'')}" class="devtool_input"></td>
        <td class="col_extra" style="display:${displayExtra}"><input type="text" name="columns[${idx}][filter]" value="${escapeHtml(item.filter||'')}" class="devtool_input"></td>
        <td class="col_extra" style="display:${displayExtra}; text-align:center;"><input type="checkbox" name="columns[${idx}][notnull]" value="notnull" ${item.notnull=='notnull'?'checked':''}></td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
    `;
}

function conditionRowHtml(item, idx) {
    var col = item.column || '';
    var isCustom = col && !availableColumnOptions.some(opt => opt.value === col);
    var displaySelect = isCustom ? 'none' : 'block';
    var displayText = isCustom ? 'block' : 'none';
    
    var op = item.operation || 'equal';
    var ops = ['below', 'equal', 'excess', 'in', 'less', 'like', 'like_prefix', 'more', 'notequal', 'notin', 'notnull', 'null', 'search'];
    var opOptions = '';
    ops.forEach(function(o) {
        opOptions += `<option value="${o}" ${o === op ? 'selected' : ''}>${o}</option>`;
    });

    var pipe = item.pipe || 'and';
    var pipes = ['and', 'or', 'where'];
    var pipeOptions = '';
    pipes.forEach(function(p) {
        pipeOptions += `<option value="${p}" ${p === pipe ? 'selected' : ''}>${p}</option>`;
    });

    return `
        <td>
            <div id="cond_col_wrapper_${idx}">
                <select class="devtool_select condition_col_select" style="display:${displaySelect}; width:100%;" onchange="onConditionColumnSelectChange(this, ${idx})">
                    ${generateColumnOptionsHtml(col, false)}
                </select>
                <div class="custom_input_wrapper" style="display:${displayText}; width:100%; position:relative;">
                    <input type="text" value="${escapeHtml(col)}" class="devtool_input" style="width:100%; padding-right:30px;" oninput="onConditionColumnInputChange(this, ${idx})">
                    <button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetConditionColumnSelect(${idx})">X</button>
                </div>
                <input type="hidden" name="conditions[${idx}][column]" id="real_cond_col_${idx}" value="${escapeHtml(col)}">
            </div>
        </td>
        <td><input type="text" name="conditions[${idx}][var]" id="cond_var_${idx}" value="${escapeHtml(item.var||'')}" class="devtool_input"></td>
        <td style="text-align:center;"><input type="checkbox" name="conditions[${idx}][notnull]" value="Y" ${item.notnull=='notnull'?'checked':''}></td>
        <td><select name="conditions[${idx}][operation]" class="devtool_select" style="width:100%;">${opOptions}</select></td>
        <td><input type="text" name="conditions[${idx}][default]" value="${escapeHtml(item.default||'')}" class="devtool_input"></td>
        <td><input type="text" name="conditions[${idx}][filter]" value="${escapeHtml(item.filter||'')}" class="devtool_input"></td>
        <td>
            <select name="conditions[${idx}][pipe]" class="devtool_select" style="width:100%;" onchange="generateQueryId()">
                ${pipeOptions}
            </select>
        </td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove(); generateQueryId();">Del</button></td>
    `;
}

function navigationRowHtml(item, idx) {
    var tag = item._tag || 'index';
    return `
        <td>
            <select name="navigation[${idx}][_tag]" class="devtool_select">
                <option value="index" ${tag=='index'?'selected':''}>index</option>
                <option value="list_count" ${tag=='list_count'?'selected':''}>list_count</option>
                <option value="page_count" ${tag=='page_count'?'selected':''}>page_count</option>
                <option value="page" ${tag=='page'?'selected':''}>page</option>
            </select>
        </td>
        <td><input type="text" name="navigation[${idx}][var]" value="${escapeHtml(item.var||'')}" class="devtool_input"></td>
        <td><input type="text" name="navigation[${idx}][default]" value="${escapeHtml(item.default||'')}" class="devtool_input"></td>
        <td><input type="text" name="navigation[${idx}][order]" value="${escapeHtml(item.order||'')}" class="devtool_input"></td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
    `;
}

function addTable() {
    var tbody = document.getElementById('tbody_tables');
    var tr = document.createElement('tr');
    var idx = getNextRowId();
    tr.setAttribute('data-row-idx', idx);
    tr.innerHTML = tableRowHtml({}, idx);
    tbody.appendChild(tr);
}
function addColumn(data) {
    var tbody = document.getElementById('tbody_columns');
    var tr = document.createElement('tr');
    var idx = getNextRowId();
    tr.innerHTML = columnRowHtml(data || {}, idx);
    tbody.appendChild(tr);
}
function addCondition() {
    var tbody = document.getElementById('tbody_conditions');
    var tr = document.createElement('tr');
    var idx = getNextRowId();
    tr.innerHTML = conditionRowHtml({}, idx);
    tbody.appendChild(tr);
}
function addNavigation() {
    var tbody = document.getElementById('tbody_navigation');
    var tr = document.createElement('tr');
    var idx = getNextRowId();
    tr.innerHTML = navigationRowHtml({}, idx);
    tbody.appendChild(tr);
}

function removeTableRow(btn, idx) {
    btn.closest('tr').remove();
    delete tableSchemas[idx];
    updateAvailableColumns();
    generateQueryId();
}

function onTableNameSelectChange(select, idx) {
    var val = select.value;
    if (val === '_custom_') {
        select.style.display = 'none';
        var wrapper = select.nextElementSibling; // custom_input_wrapper
        wrapper.style.display = 'block';
        var input = wrapper.querySelector('input');
        input.value = '';
        input.focus();
        document.getElementById('real_table_name_' + idx).value = '';
    } else {
        document.getElementById('real_table_name_' + idx).value = val;
        fetchTableSchema(idx, val);
        generateQueryId();
    }
}

function onTableInputChange(input, idx) {
    var val = input.value;
    document.getElementById('real_table_name_' + idx).value = val;
    fetchTableSchema(idx, val);
    generateQueryId();
}

function resetTableSelect(idx) {
    var wrapper = document.getElementById('table_wrapper_' + idx);
    var select = wrapper.querySelector('select');
    var inputWrapper = wrapper.querySelector('.custom_input_wrapper');
    
    inputWrapper.style.display = 'none';
    select.style.display = 'block';
    select.value = '';
    document.getElementById('real_table_name_' + idx).value = '';
    generateQueryId();
}

function fetchTableSchema(idx, tableName) {
    if (!tableName) return;
    exec_json('devtool.getDevtoolSchemaColumns', {target_module: targetModule, table_name: tableName}, function(ret){
        if(ret.columns) {
            tableSchemas[idx] = ret.columns;
            tableSchemaDetails[idx] = ret.schema_details;
            updateAvailableColumns();
            
            // Auto-populate columns tab if empty and action is insert/update
            var action = document.getElementById('input_action').value;
            var tbody = document.getElementById('tbody_columns');
            if ((action === 'insert' || action === 'update') && tbody.children.length === 0 && ret.columns.length > 0) {
                var rows = ret.columns.map(function(c) {
                    var item = {name: c, var: c};
                    if (action === 'insert' && ret.schema_details && ret.schema_details[c]) {
                        var details = ret.schema_details[c];
                        if (details.notnull === 'notnull') item.notnull = 'notnull';
                        if (details.default) item.default = details.default;
                        
                        if (['int', 'bigint', 'tinyint', 'smallint', 'mediumint', 'float', 'double', 'decimal'].includes(details.type)) {
                            item.filter = 'number';
                        } else if (details.type === 'email_address') {
                            item.filter = 'email';
                        }
                    }
                    return item;
                });
                renderRows('tbody_columns', rows, columnRowHtml);
            }
        }
    });
}

function updateAvailableColumns() {
    availableColumnOptions = [];
    var seenWildcards = new Set();
    var action = document.getElementById('input_action').value;

    document.querySelectorAll('#tbody_tables tr').forEach(tr => {
        var idx = tr.getAttribute('data-row-idx');
        var aliasInput = tr.querySelector('input[name*="[alias]"]');
        var alias = aliasInput ? aliasInput.value : '';
        var columns = tableSchemas[idx] || [];
        
        if (action !== 'insert' && action !== 'update') {
            var wildcard = alias ? alias + '.*' : '*';
            if (!seenWildcards.has(wildcard)) {
                availableColumnOptions.push({value: wildcard, label: wildcard});
                seenWildcards.add(wildcard);
            }
        }
        
        columns.forEach(col => {
            var val = alias ? alias + '.' + col : col;
            availableColumnOptions.push({value: val, label: val});
        });
    });
    refreshConditionColumnSelects();
    refreshColumnNameSelects();
}

function generateColumnOptionsHtml(selectedValue, includeWildcard = true) {
    var options = '<option value="">(Select Column)</option>';
    availableColumnOptions.forEach(opt => {
        if (!includeWildcard && (opt.value === '*' || opt.value.endsWith('.*'))) return;
        options += `<option value="${escapeHtml(opt.value)}" ${opt.value === selectedValue ? 'selected' : ''}>${escapeHtml(opt.label)}</option>`;
    });
    options += `<option value="_custom_">(Custom)</option>`;
    return options;
}

function refreshColumnNameSelects() {
    document.querySelectorAll('#tbody_columns tr').forEach(tr => {
        var select = tr.querySelector('.column_name_select');
        if(!select) return;
        
        var wrapper = select.closest('div');
        var hiddenInput = wrapper.querySelector('input[type="hidden"]');
        var currentVal = hiddenInput.value;
        
        select.innerHTML = generateColumnOptionsHtml(currentVal, true);
        
        var isCustom = currentVal && !availableColumnOptions.some(opt => opt.value === currentVal);
        var inputWrapper = wrapper.querySelector('.custom_input_wrapper');
        
        if (isCustom) {
            select.style.display = 'none';
            inputWrapper.style.display = 'block';
            inputWrapper.querySelector('input').value = currentVal;
        } else {
            select.style.display = 'block';
            inputWrapper.style.display = 'none';
            select.value = currentVal;
        }
    });
}

function onColumnNameSelectChange(select, idx) {
    var val = select.value;
    if (val === '_custom_') {
        select.style.display = 'none';
        var wrapper = select.nextElementSibling;
        wrapper.style.display = 'block';
        var input = wrapper.querySelector('input');
        input.value = '';
        input.focus();
        document.getElementById('real_col_name_' + idx).value = '';
    } else {
        document.getElementById('real_col_name_' + idx).value = val;
        var varInput = document.getElementById('col_var_' + idx);
        if (varInput) {
            varInput.value = val.split('.').pop();
        }
    }
}

function onColumnNameInputChange(input, idx) {
    var val = input.value;
    document.getElementById('real_col_name_' + idx).value = val;
    var varInput = document.getElementById('col_var_' + idx);
    if (varInput) {
        varInput.value = val.split('.').pop();
    }
}

function resetColumnNameSelect(idx) {
    var wrapper = document.getElementById('col_name_wrapper_' + idx);
    var select = wrapper.querySelector('select');
    var inputWrapper = wrapper.querySelector('.custom_input_wrapper');
    
    inputWrapper.style.display = 'none';
    select.style.display = 'block';
    select.value = '';
    document.getElementById('real_col_name_' + idx).value = '';
    var varInput = document.getElementById('col_var_' + idx);
    if (varInput) varInput.value = '';
}

function refreshConditionColumnSelects() {
    document.querySelectorAll('#tbody_conditions tr').forEach(tr => {
        var select = tr.querySelector('.condition_col_select');
        if(!select) return;
        
        var wrapper = select.closest('div'); // cond_col_wrapper_idx
        var hiddenInput = wrapper.querySelector('input[type="hidden"]');
        var currentVal = hiddenInput.value;
        
        // Rebuild options
        select.innerHTML = generateColumnOptionsHtml(currentVal, false);
        
        // Determine display state
        var isCustom = currentVal && !availableColumnOptions.some(opt => opt.value === currentVal);
        var inputWrapper = wrapper.querySelector('.custom_input_wrapper');
        
        if (isCustom) {
            select.style.display = 'none';
            inputWrapper.style.display = 'block';
            inputWrapper.querySelector('input').value = currentVal;
        } else {
            select.style.display = 'block';
            inputWrapper.style.display = 'none';
            select.value = currentVal;
        }
    });
}

function onConditionColumnSelectChange(select, idx) {
    var val = select.value;
    if (val === '_custom_') {
        select.style.display = 'none';
        var wrapper = select.nextElementSibling;
        wrapper.style.display = 'block';
        var input = wrapper.querySelector('input');
        input.value = '';
        input.focus();
        document.getElementById('real_cond_col_' + idx).value = '';
    } else {
        document.getElementById('real_cond_col_' + idx).value = val;
        var varInput = document.getElementById('cond_var_' + idx);
        if (varInput) {
            varInput.value = val.split('.').pop();
        }
        generateQueryId();
    }
}

function onConditionColumnInputChange(input, idx) {
    var val = input.value;
    document.getElementById('real_cond_col_' + idx).value = val;
    var varInput = document.getElementById('cond_var_' + idx);
    if (varInput) {
        varInput.value = val.split('.').pop();
    }
    generateQueryId();
}

function resetConditionColumnSelect(idx) {
    var wrapper = document.getElementById('cond_col_wrapper_' + idx);
    var select = wrapper.querySelector('select');
    var inputWrapper = wrapper.querySelector('.custom_input_wrapper');
    
    inputWrapper.style.display = 'none';
    select.style.display = 'block';
    select.value = '';
    document.getElementById('real_cond_col_' + idx).value = '';
    var varInput = document.getElementById('cond_var_' + idx);
    if (varInput) varInput.value = '';
    generateQueryId();
}

function getNextRowId() {
    return ++rowIdCounter;
}

function generateQueryId() {
    if (!autoIdGeneration) return;
    
    var action = document.getElementById('input_action').value;
    var tableName = '';
    
    var tableRows = document.querySelectorAll('#tbody_tables tr');
    if (tableRows.length > 0) {
        var firstRow = tableRows[0];
        var realNameInput = firstRow.querySelector('input[id^="real_table_name_"]');
        if (realNameInput) {
            tableName = realNameInput.value;
        }
    }
    
    if (!tableName) {
        document.getElementById('input_query_id').value = action;
        return;
    }
    
    var id = action + toCamelCase(tableName);
    
    var conditionRows = document.querySelectorAll('#tbody_conditions tr');
    if (conditionRows.length > 0) {
        var hasCondition = false;
        var condStr = '';
        
        conditionRows.forEach((tr, index) => {
            var colInput = tr.querySelector('input[id^="real_cond_col_"]');
            var pipeInput = tr.querySelector('[name*="[pipe]"]');
            
            var col = colInput ? colInput.value : '';
            var pipe = pipeInput ? pipeInput.value : 'and';
            if (!pipe) pipe = 'and';
            
            if (col) {
                if (hasCondition) {
                    condStr += toCamelCase(pipe);
                }
                var colName = col.split('.').pop();
                condStr += toCamelCase(colName);
                hasCondition = true;
            }
        });
        
        if (hasCondition) {
            id += 'By' + condStr;
        }
    }
    
    document.getElementById('input_query_id').value = id;
    if (typeof checkQueryIdDuplicate === 'function') {
        checkQueryIdDuplicate();
    }
}
</script>