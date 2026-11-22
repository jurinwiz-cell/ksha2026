<div class="section_box">
    <h3>PHP Code Generator</h3>
    
    <div id="php_config_container" style="margin-bottom:20px; display:none;">
        <div style="margin-bottom:15px; padding:15px; background:#f9f9f9; border-radius:5px;">
            <h4 style="margin-top:0;">Configuration</h4>
            
            <div style="margin-bottom:15px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Function Name:</label>
                <input type="text" id="php_func_name" class="devtool_input" style="width:100%; max-width:400px;" oninput="generatePhpPreview()">
            </div>

            <!-- Select Options -->
            <div id="php_opt_select" style="display:none;">
                <div id="php_opt_return_type" style="margin-bottom:10px;">
                    <label style="font-weight:bold; margin-right:10px;">Return Type:</label>
                    <label style="margin-right:15px;"><input type="radio" name="php_return_type" value="object" checked onchange="toggleReturnTypeUI(); generatePhpPreview()"> Single (Row)</label>
                    <label><input type="radio" name="php_return_type" value="array" onchange="toggleReturnTypeUI(); generatePhpPreview()"> List (Array)</label>
                </div>
                
                <div id="php_return_class_wrapper" style="margin-bottom:15px; margin-left:20px;">
                    <label style="display:block; font-size:12px; color:#666; margin-bottom:3px;">Item Class:</label>
                    <select id="php_return_class" class="devtool_select" style="width:100%; max-width:400px;" onchange="onReturnClassChange(); generatePhpPreview()">
                        <option value="object">object (stdClass)</option>
                        <option value="custom">Custom...</option>
                    </select>
                    <input type="text" id="php_return_class_custom" class="devtool_input" style="display:none; width:100%; max-width:400px; margin-top:5px;" placeholder="e.g. \My\Class" oninput="generatePhpPreview()">
                </div>
                
                <div style="margin-bottom:10px;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Parameters:</label>
                    <table class="devtool_info_table">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" checked disabled></th>
                                <th>Variable</th>
                                <th>Type</th>
                                <th>Param Name</th>
                                <th>Optional</th>
                            </tr>
                        </thead>
                        <tbody id="php_params_tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Insert/Update Options -->
            <div id="php_opt_default" style="display:none;">
                <p style="color:#666; margin:0;">Standard signature <code>(object|array $args)</code> will be used.</p>
            </div>
        </div>
    </div>

    <div id="php_result_container">
        <p style="color:#666;">Loading...</p>
    </div>

    <div style="margin-top:15px; text-align:right;">
        <button type="button" class="devtool_btn" onclick="savePhpCode()">Save / Create File</button>
    </div>
</div>

<script>
var cachedModelClasses = null;

function loadPhpCode() {
    var queryId = document.getElementById('original_query_id').value;
    var container = document.getElementById('php_result_container');
    var configContainer = document.getElementById('php_config_container');
    
    if (queryId === 'new' || !currentQueryData) {
        container.innerHTML = '<p style="color:#e53e3e;">Please save the query first.</p>';
        configContainer.style.display = 'none';
        return;
    }
    
    if (!cachedModelClasses) {
        exec_json('devtool.getDevtoolModelClasses', {target_module: targetModule}, function(ret){
            cachedModelClasses = ret.classes || [];
            renderReturnClassOptions();
            proceedLoadPhpCode();
        });
    } else {
        proceedLoadPhpCode();
    }
}

function proceedLoadPhpCode() {
    var configContainer = document.getElementById('php_config_container');
    configContainer.style.display = 'block';
    
    var funcNameInput = document.getElementById('php_func_name');
    funcNameInput.value = toCamelCaseLcfirst(document.getElementById('original_query_id').value);
    funcNameInput.focus();
    
    // Setup UI based on action
    document.getElementById('php_opt_select').style.display = 'none';
    document.getElementById('php_opt_default').style.display = 'none';

    if (currentQueryData.action === 'select') {
        document.getElementById('php_opt_select').style.display = 'block';
        document.getElementById('php_opt_return_type').style.display = 'block';
        renderPhpParamsTable();
        toggleReturnTypeUI();
    } else if (currentQueryData.action === 'delete') {
        document.getElementById('php_opt_select').style.display = 'block';
        document.getElementById('php_opt_return_type').style.display = 'none';
        document.getElementById('php_return_class_wrapper').style.display = 'none';
        renderPhpParamsTable();
    } else {
        document.getElementById('php_opt_default').style.display = 'block';
    }

    generatePhpPreview();
}

function renderReturnClassOptions() {
    var select = document.getElementById('php_return_class');
    select.innerHTML = '<option value="object">object (stdClass)</option>';
    cachedModelClasses.forEach(function(cls) {
        var opt = document.createElement('option');
        opt.value = cls;
        opt.text = cls;
        select.appendChild(opt);
    });
    var customOpt = document.createElement('option');
    customOpt.value = 'custom';
    customOpt.text = 'Custom...';
    select.appendChild(customOpt);
}

function toggleReturnTypeUI() {
    // Always show item class wrapper
    if (currentQueryData.action === 'select') {
        document.getElementById('php_return_class_wrapper').style.display = 'block';
    }
}

function onReturnClassChange() {
    var val = document.getElementById('php_return_class').value;
    document.getElementById('php_return_class_custom').style.display = (val === 'custom') ? 'block' : 'none';
}

function renderPhpParamsTable() {
    var tbody = document.getElementById('php_params_tbody');
    tbody.innerHTML = '';
    
    var aliasMap = {};
    if (typeof tableSchemaDetails !== 'undefined') {
        document.querySelectorAll('#tbody_tables tr').forEach(function(tr) {
            var idx = tr.getAttribute('data-row-idx');
            var aliasInput = tr.querySelector('input[name*="[alias]"]');
            var alias = aliasInput ? aliasInput.value : '';
            
            if (tableSchemaDetails[idx]) {
                if (alias) {
                    aliasMap[alias] = tableSchemaDetails[idx];
                }
                aliasMap['__idx_' + idx] = tableSchemaDetails[idx];
            }
        });
    }

    var vars = [];
    if (currentQueryData.conditions) vars = vars.concat(currentQueryData.conditions);
    if (currentQueryData.navigation) vars = vars.concat(currentQueryData.navigation);
    
    var seen = {};
    vars.forEach(function(v) {
        if (!v.var || seen[v.var]) return;
        seen[v.var] = true;
        
        var tr = document.createElement('tr');
        var isRequired = (v.notnull === 'notnull' || v.notnull === 'Y');
        var defaultType = 'string';

        if (v.operation === 'in' || v.operation === 'notin') {
            defaultType = 'array';
        } else if (v.column) {
            var parts = v.column.split('.');
            var colName = parts.pop();
            var tblAlias = parts.join('.');
            var schema = null;

            if (tblAlias && aliasMap[tblAlias]) {
                schema = aliasMap[tblAlias];
            } else {
                // Try to find in any table
                for (var key in aliasMap) {
                    if (aliasMap[key][colName]) {
                        schema = aliasMap[key];
                        break;
                    }
                }
            }

            if (schema && schema[colName]) {
                var colType = schema[colName].type;
                if (['int', 'bigint', 'tinyint', 'smallint', 'mediumint', 'year'].includes(colType)) {
                    defaultType = 'int';
                } else if (['float', 'double', 'decimal'].includes(colType)) {
                    defaultType = 'float';
                }
            }
        }
        
        tr.innerHTML = `
            <td style="text-align:center;"><input type="checkbox" class="php_param_check" checked onchange="generatePhpPreview()"></td>
            <td>${escapeHtml(v.var)}</td>
            <td>
                <select class="devtool_select php_param_type" onchange="generatePhpPreview()" style="padding:2px;">
                    <option value="int" ${defaultType === 'int' ? 'selected' : ''}>int</option>
                    <option value="string" ${defaultType === 'string' ? 'selected' : ''}>string</option>
                    <option value="array" ${defaultType === 'array' ? 'selected' : ''}>array</option>
                    <option value="float" ${defaultType === 'float' ? 'selected' : ''}>float</option>
                    <option value="bool" ${defaultType === 'bool' ? 'selected' : ''}>bool</option>
                </select>
            </td>
            <td><input type="text" class="devtool_input php_param_name" value="${escapeHtml(v.var)}" oninput="generatePhpPreview()" style="width:100%;"></td>
            <td style="text-align:center;"><input type="checkbox" class="php_param_optional" ${!isRequired ? 'checked' : ''} onchange="generatePhpPreview()"></td>
        `;
        tbody.appendChild(tr);
    });
}

function getPhpOptions() {
    var options = {
        func_name: document.getElementById('php_func_name').value,
        return_type: document.querySelector('input[name="php_return_type"]:checked').value,
        return_class: document.getElementById('php_return_class').value,
        return_class_custom: document.getElementById('php_return_class_custom').value,
        params: [],
        action: currentQueryData.action,
    };

    if (currentQueryData.action === 'select' || currentQueryData.action === 'delete') {
        document.querySelectorAll('#php_params_tbody tr').forEach(function(tr) {
            if (!tr.querySelector('.php_param_check').checked) return;
            options.params.push({
                var: tr.cells[1].innerText,
                type: tr.querySelector('.php_param_type').value,
                name: tr.querySelector('.php_param_name').value,
                optional: tr.querySelector('.php_param_optional').checked
            });
        });
    } else if (currentQueryData.action === 'update' || currentQueryData.action === 'insert') {
        options.params = null;
    }
    return options;
}

function generatePhpPreview() {
    var queryId = document.getElementById('original_query_id').value;
    var container = document.getElementById('php_result_container');
    
    if (container.offsetHeight > 0) {
        container.style.minHeight = container.offsetHeight + 'px';
    }
    container.innerHTML = '<p style="color:#666;">Generating code...</p>';

    exec_json('devtool.getDevtoolQueryPhp', {
        target_module: targetModule,
        query_id: queryId,
        options: getPhpOptions()
    }, function(ret) {
        container.style.minHeight = '';
        if (ret.exists) {
            var msg = `<div style="padding:15px; background:#fff5f5; border:1px solid #fc8181; color:#c53030; border-radius:4px;"><strong>Cannot generate code:</strong> ${escapeHtml(ret.message)}</div>`;
            if (ret.existing_code) {
                msg += `<div style="margin-top:15px;"><p><strong>Existing Code:</strong></p><pre style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow:auto; font-family:monospace;">${escapeHtml(ret.existing_code)}</pre></div>`;
            }
            container.innerHTML = msg;
        } else {
            var msg = ret.file_exists ? `<p>File <strong>${escapeHtml(ret.target_file)}</strong> exists. Add the following method to the class:</p>` : `<p>File <strong>${escapeHtml(ret.target_file)}</strong> does not exist. Create a new file with the following content:</p>`;
            container.innerHTML = msg + `<pre style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow:auto; font-family:monospace;">${escapeHtml(ret.code)}</pre>`;
        }
    });
}

function savePhpCode() {
    if (!confirm('Do you want to save/create this file?')) return;
    
    var queryId = document.getElementById('original_query_id').value;
    exec_json('devtool.procDevtoolQueryPhpSave', {
        target_module: targetModule,
        query_id: queryId,
        options: getPhpOptions()
    }, function(ret) {
        alert(ret.message);
        loadPhpCode();
    });
}

function toCamelCaseLcfirst(str) {
    if (!str) return '';
    return lcfirst(str.replace(/([-_][a-zA-Z0-9])/g, ($1) => {
        return $1.toUpperCase()
            .replace('-', '')
            .replace('_', '');
    }).replace(/^[a-z]/, ($1) => $1.toUpperCase()));
}

function lcfirst(string) {
    return string.charAt(0).toLowerCase() + string.slice(1);
}
</script>