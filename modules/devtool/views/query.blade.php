@load('style.scss')
<div class="devtool_query_container" style="display:flex; gap:20px;">
    <!-- Left Sidebar -->
    <div class="query_sidebar" style="width:250px; flex-shrink:0; border-right:1px solid #eee; padding-right:20px;">
        <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">Queries</h3>
            <a href="{{ getUrl('query_id', 'new') }}" class="devtool_btn small">+ New</a>
        </div>
        <ul style="list-style:none; padding:0; margin:0;">
            <li style="margin-bottom:5px;">
                <a href="javascript:void(0);" onclick="loadQuery('new', '{{ getUrl('query_id', 'new') }}');"
                   style="display:block; padding:8px 10px; text-decoration:none; color:#333; border-radius:4px; {{ $query_id == 'new' ? 'background:#f0f4f8; font-weight:bold;' : 'background:#fff;' }} hover:background:#f7f7f7;">
                    + New
                </a>
            </li>
            @foreach($queries as $q)
            <li style="margin-bottom:5px;">
                <a href="javascript:void(0);" onclick="loadQuery('{{ $q }}', '{{ getUrl('query_id', $q) }}');" 
                   style="display:block; padding:8px 10px; text-decoration:none; color:#333; border-radius:4px; {{ $query_id == $q ? 'background:#f0f4f8; font-weight:bold;' : 'background:#fff;' }} hover:background:#f7f7f7; word-wrap: break-word;">
                    {!! preg_replace('/(?<!^)([A-Z])/', '<wbr>$0', htmlspecialchars($q)) !!}
                </a>
            </li>
            @endforeach
        </ul>
    </div>

    <!-- Right Editor -->
    <div class="query_editor" style="flex:1;">
        <form id="query_form" action="./" method="post" onsubmit="document.getElementById('input_action').disabled=false;">
            <input type="hidden" name="module" value="devtool">
            <input type="hidden" name="act" value="procDevtoolQueryUpdate">
            <input type="hidden" name="target_module" value="{{ $target_module }}">
            <input type="hidden" name="original_query_id" id="original_query_id" value="{{ $query_id }}">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0;">Query Editor</h1>
                <div>
                    <button type="button" id="btn_delete" onclick="deleteQuery()" class="devtool_btn danger" style="margin-right:5px; display:none;">Delete</button>
                    <button type="button" onclick="saveQuery()" class="devtool_btn">Save Changes</button>
                </div>
            </div>

            <div style="background:#f9f9f9; padding:15px; border-radius:5px; margin-bottom:20px;">
                <div style="display:flex; gap:15px; margin-bottom:10px;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Query ID (Filename)</label>
                        <input type="text" name="query_id" id="input_query_id" class="devtool_input" required>
                        <div id="query_id_warning" style="color:#e53e3e; font-size:12px; margin-top:5px; display:none;">
                            Alert: This Query ID already exists. It may overwrite an existing query.
                        </div>
                    </div>
                    <div style="width:150px;">
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Action</label>
                        <select name="action" id="input_action" class="devtool_select" onchange="onActionChange()">
                            <option value="select">select</option>
                            <option value="insert">insert</option>
                            <option value="update">update</option>
                            <option value="delete">delete</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SQL Preview -->
            <div class="section_box hide_new" id="sec_sql" style="margin-bottom:20px;@if($query_id === 'new') display: none; @endif">
                <h3>Generated SQL</h3>
                <pre id="query_sql_preview" style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow-x:auto; font-family:monospace;"></pre>
            </div>

            <!-- Tabs -->
            <div class="devtool_tabs" style="display:flex; border-bottom:1px solid #ddd; margin-bottom:20px;">
                <a href="javascript:void(0);" onclick="switchTab('config')" id="tab_btn_config" style="padding:10px 20px; text-decoration:none; color:#333; border-bottom:2px solid #247bff; font-weight:bold;">Config</a>
                <a href="javascript:void(0);" onclick="switchTab('playground')" id="tab_btn_playground" style="padding:10px 20px; text-decoration:none; color:#333; border-bottom:2px solid transparent;">Playground</a>
                <a href="javascript:void(0);" onclick="switchTab('php')" id="tab_btn_php" style="padding:10px 20px; text-decoration:none; color:#333; border-bottom:2px solid transparent;">PHP</a>
            </div>

            <div id="tab_content_config">
                @include('query_config')
            </div>
            <div id="tab_content_playground" style="display:none;">
                @include('query_playground')
            </div>
            <div id="tab_content_php" style="display:none;">
                @include('query_php')
            </div>
        </form>
    </div>
</div>

<script>
var currentQueryId = '{{ $query_id }}';
var targetModule = '{{ $target_module }}';
var currentQueryData = null;
var schemaList = @json($schemas);
var queryList = @json($queries);

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function loadQuery(queryId, newUrl) {
    switchTab('config');

    if (newUrl) {
        // Update URL without reload
        window.history.replaceState({path:newUrl}, '', newUrl);
    }

    // Highlight sidebar
    document.querySelectorAll('.query_sidebar a').forEach(el => {
        el.style.fontWeight = 'normal';
        el.style.background = '#fff';
    });
    var activeLink = document.querySelector(`.query_sidebar a[onclick*="loadQuery('${queryId}'"]`);
    if(activeLink) {
        activeLink.style.fontWeight = 'bold';
        activeLink.style.background = '#f0f4f8';
    }

    if(queryId === 'new') {
        resetForm();
        return;
    }

    autoIdGeneration = false;
    tableSchemas = {};
    exec_json('devtool.getDevtoolQuery', {target_module: targetModule, query_id: queryId}, function(ret){
        var data = ret.data;
        currentQueryData = data;
        document.getElementById('input_query_id').value = data.id;
        document.getElementById('original_query_id').value = data.id;
        document.getElementById('input_action').value = data.action;
        checkQueryIdDuplicate();
        document.getElementById('input_action').disabled = true;
        document.getElementById('btn_delete').style.display = 'inline-block';
        document.getElementById('sec_sql').style.display = 'block';
        document.getElementById('query_sql_preview').innerText = window.sqlFormatter.format(data.sql);

        renderPlayground(data);
        renderRows('tbody_tables', data.tables, tableRowHtml);
        renderRows('tbody_columns', data.columns, columnRowHtml);
        renderRows('tbody_conditions', data.conditions, conditionRowHtml);
        renderRows('tbody_navigation', data.navigation, navigationRowHtml);

        updateFormVisibility();

        // Load schemas for existing tables
        document.querySelectorAll('#tbody_tables tr').forEach(tr => {
            var idx = tr.getAttribute('data-row-idx');
            var nameInput = document.getElementById('real_table_name_' + idx);
            if(nameInput && nameInput.value) {
                fetchTableSchema(idx, nameInput.value);
            }
        });
    });
}

function resetForm() {
    autoIdGeneration = true;
    currentQueryData = null;
    document.getElementById('input_query_id').value = '';
    document.getElementById('original_query_id').value = 'new';
    document.getElementById('input_action').value = 'select';
    checkQueryIdDuplicate();
    document.getElementById('input_action').disabled = false;
    document.getElementById('btn_delete').style.display = 'none';
    document.getElementById('sec_sql').style.display = 'none';
    document.getElementById('query_sql_preview').innerText = '';
    document.getElementById('tbody_tables').innerHTML = '';
    document.getElementById('tbody_columns').innerHTML = '';
    document.getElementById('tbody_conditions').innerHTML = '';
    document.getElementById('tbody_navigation').innerHTML = '';
    renderPlayground(null);
    tableSchemas = {};
    addTable();
    addColumn({name: '*'});
    updateFormVisibility();
}

function onActionChange() {
    var isNew = document.getElementById('original_query_id').value === 'new';
    if (isNew) {
        document.getElementById('tbody_tables').innerHTML = '';
        document.getElementById('tbody_columns').innerHTML = '';
        document.getElementById('tbody_conditions').innerHTML = '';
        document.getElementById('tbody_navigation').innerHTML = '';
        tableSchemas = {};
        availableColumnOptions = [];
        autoIdGeneration = true;
        addTable();
    }
    updateFormVisibility();
    if (typeof updateAvailableColumns === 'function') {
        updateAvailableColumns();
    }
}

function updateFormVisibility() {
    var action = document.getElementById('input_action').value;
    generateQueryId();
    var secCols = document.getElementById('sec_columns');
    var secConds = document.getElementById('sec_conditions');
    var secNav = document.getElementById('sec_navigation');

    secCols.style.display = (action === 'delete') ? 'none' : 'block';
    secConds.style.display = (action === 'insert') ? 'none' : 'block';
    secNav.style.display = (action === 'select') ? 'block' : 'none';

    var displayAlias = (action === 'select') ? '' : 'none';
    document.querySelectorAll('.col_alias').forEach(el => el.style.display = displayAlias);

    var displayExtra = (action === 'select') ? 'none' : '';
    document.querySelectorAll('.col_extra').forEach(el => el.style.display = displayExtra);

    var isNew = document.getElementById('original_query_id').value === 'new';
    if (isNew) {
        var tbody = document.getElementById('tbody_columns');
        if (action === 'select') {
            if (tbody.children.length === 0) {
                addColumn({name: '*'});
            }
        } else {
            Array.from(tbody.children).forEach(tr => {
                var nameInput = tr.querySelector('input[name*="[name]"]');
                if(nameInput && nameInput.value === '*') {
                    tr.remove();
                }
            });
        }
    }
}

function saveQuery() {
    var form = document.getElementById('query_form');
    var params = {
        target_module: form.target_module.value,
        original_query_id: form.original_query_id.value,
        query_id: form.query_id.value,
        action: form.action.value,
        tables: [],
        columns: [],
        conditions: [],
        navigation: []
    };

    // Tables
    document.querySelectorAll('#tbody_tables tr').forEach(function(tr) {
        var idx = tr.getAttribute('data-row-idx');
        var nameInput = document.getElementById('real_table_name_' + idx);
        var aliasInput = tr.querySelector('input[name*="[alias]"]');
        if(nameInput && nameInput.value) {
            params.tables.push({name: nameInput.value, alias: aliasInput ? aliasInput.value : ''});
        }
    });

    // Columns
    document.querySelectorAll('#tbody_columns tr').forEach(function(tr) {
        var p = {};
        var nameInput = tr.querySelector('input[name*="[name]"]');
        if(nameInput) p.name = nameInput.value;
        
        var aliasInput = tr.querySelector('input[name*="[alias]"]');
        if(aliasInput) p.alias = aliasInput.value;
        
        var varInput = tr.querySelector('input[name*="[var]"]');
        if(varInput) p.var = varInput.value;
        
        var defaultInput = tr.querySelector('input[name*="[default]"]');
        if(defaultInput) p.default = defaultInput.value;
        
        var filterInput = tr.querySelector('input[name*="[filter]"]');
        if(filterInput) p.filter = filterInput.value;
        
        var notnullInput = tr.querySelector('input[name*="[notnull]"]');
        if(notnullInput && notnullInput.checked) p.notnull = 'notnull';
        
        if(p.name) params.columns.push(p);
    });

    // Conditions
    document.querySelectorAll('#tbody_conditions tr').forEach(function(tr) {
        var p = {};
        var colInput = tr.querySelector('input[name*="[column]"]');
        if(colInput) p.column = colInput.value;
        var varInput = tr.querySelector('input[name*="[var]"]');
        if(varInput) p.var = varInput.value;
        var notnullInput = tr.querySelector('input[name*="[notnull]"]');
        if(notnullInput && notnullInput.checked) p.notnull = 'Y';
        var opInput = tr.querySelector('select[name*="[operation]"]');
        if(opInput) p.operation = opInput.value;
        var defaultInput = tr.querySelector('input[name*="[default]"]');
        if(defaultInput) p.default = defaultInput.value;
        var filterInput = tr.querySelector('input[name*="[filter]"]');
        if(filterInput) p.filter = filterInput.value;
        var pipeInput = tr.querySelector('select[name*="[pipe]"]');
        if(pipeInput) p.pipe = pipeInput.value;
        
        if(p.operation) params.conditions.push(p);
    });

    // Navigation
    document.querySelectorAll('#tbody_navigation tr').forEach(function(tr) {
        var p = {};
        var tagInput = tr.querySelector('select[name*="[_tag]"]');
        if(tagInput) p._tag = tagInput.value;
        var varInput = tr.querySelector('input[name*="[var]"]');
        if(varInput) p.var = varInput.value;
        var defaultInput = tr.querySelector('input[name*="[default]"]');
        if(defaultInput) p.default = defaultInput.value;
        var orderInput = tr.querySelector('input[name*="[order]"]');
        if(orderInput) p.order = orderInput.value;
        
        if(p._tag) params.navigation.push(p);
    });

    exec_json('devtool.procDevtoolQueryUpdate', params, function(ret) {
        if (params.original_query_id === 'new' && ret.redirect_url) {
            location.replace(ret.redirect_url);
        } else if (ret.redirect_url) {
            location.href = ret.redirect_url;
        } else {
            alert(ret.message);
            location.reload();
        }
    });
}

function deleteQuery() {
    if(!confirm('Are you sure you want to delete this query?')) return;
    var qid = document.getElementById('original_query_id').value;
    exec_json('devtool.procDevtoolQueryDelete', {target_module: targetModule, query_id: qid}, function(){
        location.href = getUrl('query_id', 'new');
    });
}

function getUrl(key, val) {
    // Simple helper to update query param in current url
    var url = new URL(window.location.href);
    url.searchParams.set(key, val);
    return url.toString();
}

function toCamelCase(str) {
    if (!str) return '';
    return str.replace(/([-_][a-zA-Z0-9])/g, ($1) => {
        return $1.toUpperCase()
            .replace('-', '')
            .replace('_', '');
    }).replace(/^[a-z]/, ($1) => $1.toUpperCase());
}

function switchTab(tab) {
    document.querySelectorAll('.devtool_tabs a').forEach(el => {
        el.style.borderBottom = '2px solid transparent';
        el.style.fontWeight = 'normal';
    });
    document.getElementById('tab_btn_' + tab).style.borderBottom = '2px solid #247bff';
    document.getElementById('tab_btn_' + tab).style.fontWeight = 'bold';
    
    document.getElementById('tab_content_config').style.display = (tab === 'config') ? 'block' : 'none';
    document.getElementById('tab_content_playground').style.display = (tab === 'playground') ? 'block' : 'none';
    document.getElementById('tab_content_php').style.display = (tab === 'php') ? 'block' : 'none';

    if (tab === 'php') {
        loadPhpCode();
    }
}

function checkQueryIdDuplicate() {
    var val = document.getElementById('input_query_id').value;
    var original = document.getElementById('original_query_id').value;
    var warning = document.getElementById('query_id_warning');
    
    if (val && queryList.includes(val) && val !== original) {
        warning.style.display = 'block';
    } else {
        warning.style.display = 'none';
    }
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('input_query_id').addEventListener('input', function() {
        autoIdGeneration = false;
        checkQueryIdDuplicate();
    });

    if(currentQueryId && currentQueryId !== 'new') {
        loadQuery(currentQueryId);
        autoIdGeneration = false;
    } else {
        resetForm();
    }
});
</script>
<script src="https://unpkg.com/sql-formatter@2.3.3/dist/sql-formatter.min.js"></script>