@load('style.scss')
@php
$db_types = [
    'bigint', 'int', 'mediumint', 'smallint', 'tinyint',
    'varchar', 'char', 'text', 'mediumtext', 'longtext',
    '\date', 'datetime', 'time', 'timestamp', 'year',
    'decimal', 'float', 'double',
    'blob', 'mediumblob', 'longblob',
    'json', 'enum', 'set', 'binary', 'varbinary'
];
sort($db_types);
@endphp
<div class="devtool_schema_container">
    <form id="schema_form" action="./" method="post">
    <input type="hidden" name="original_schema_name" value="{{ $schema_name }}">
    @if(isset($is_writable) && !$is_writable)
    <div style="padding:10px; margin-bottom:15px; background:#fff5f5; border:1px solid #fc8181; color:#c53030; border-radius:4px;">※ {{ $schema_name == 'new' ? '폴더' : '파일' }}에 쓰기 권한이 없습니다. 변경사항이 저장되지 않을 수 있습니다.</div>
    @endif

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Schema: <input type="text" name="schema_name" value="{{ $schema_info['name'] }}" class="devtool_input" style="width:auto; display:inline-block; font-size:24px; font-weight:bold; color:#333;" placeholder="table_name"> .xml</h1>
        <div>
            @if($schema_name !== 'new')
            <button type="button" onclick="applyDtoClass()" class="devtool_btn" style="margin-right:5px;">Apply DTO Class</button>
            <button type="button" onclick="if(confirm('정말 삭제하시겠습니까?')) { this.disabled=true; exec_json('devtool.procDevtoolSchemaDelete', {target_module:'{{ $target_module }}', schema_name:'{{ $schema_name }}'}); }" class="devtool_btn danger" style="margin-right:5px;">Delete</button>
            @endif
            <button type="button" onclick="saveSchema()" class="devtool_btn">Save Changes</button>
        </div>
    </div>

    @if($schema_name !== 'new' && isset($is_table_exists) && !$is_table_exists)
    <div style="padding:10px; margin-bottom:15px; background:#fffff0; border:1px solid #faf089; color:#975a16; border-radius:4px; display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
        <span>※ 실제 데이터베이스에 테이블({{ $schema_name }})이 존재하지 않습니다.</span>
        <button type="button" class="devtool_btn small" onclick="createTable()">Create Table</button>
    </div>
    <script>
    function createTable() {
        if(!confirm('테이블을 생성하시겠습니까?')) return;
        exec_json('devtool.procDevtoolSchemaTableCreate', {target_module:'{{ $target_module }}', schema_name:'{{ $schema_name }}'}, function(ret){
            alert(ret.message);
            location.reload();
        });
    }
    </script>
    @endif

    <h3 style="margin-top:30px;">XML Content</h3>
    <pre class="devtool_code_block"><code>{{ $schema_xml }}</code></pre>

    <h3 style="margin-top:30px;">Columns</h3>
    
        <input type="hidden" name="module" value="devtool">
        <input type="hidden" name="act" value="procDevtoolSchemaUpdate">
        <input type="hidden" name="target_module" value="{{ $target_module }}">

        <table class="devtool_info_table" id="schema_table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:120px;">Type</th>
                    <th style="width:80px;">Size</th>
                    <th>Default</th>
                    <th style="text-align:center;">Not Null</th>
                    <th style="text-align:center;">PK</th>
                    <th style="text-align:center;">AI</th>
                    <th>Index (Inline)</th>
                    <th style="width:60px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="schema_tbody">
                @php $col_idx = 0; @endphp
                @foreach($schema_info->column as $column)
                @php
                    $currentType = (string)$column['type'];
                    $isCustom = $currentType && !in_array($currentType, $db_types);
                @endphp
                <tr>
                    <td>
                        <input type="text" name="columns[{{ $col_idx }}][name]" value="{{ $column['name'] }}" class="devtool_input" style="font-weight:bold;" oninput="onColumnNameChange(this)">
                    </td>
                    <td>
                        <div class="type_wrapper" style="position:relative;">
                            <select class="devtool_select type_select" style="display:{{ $isCustom ? 'none' : 'block' }}; width:100%;" onchange="onTypeSelectChange(this)">
                                <option value="">(Select Type)</option>
                                @foreach($db_types as $t)
                                <option value="{{ $t }}" {{ $t === $currentType ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                                <option value="_custom_">(Custom)</option>
                            </select>
                            <div class="custom_input_wrapper" style="display:{{ $isCustom ? 'block' : 'none' }}; width:100%; position:relative;">
                                <input type="text" value="{{ $currentType }}" class="devtool_input type_input" style="width:100%; padding-right:30px;" oninput="onTypeInputChange(this)">
                                <button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetTypeSelect(this)">X</button>
                            </div>
                            <input type="hidden" name="columns[{{ $col_idx }}][type]" value="{{ $currentType }}" class="real_type_input">
                        </div>
                    </td>
                    <td>
                        <input type="text" name="columns[{{ $col_idx }}][size]" value="{{ $column['size'] }}" class="devtool_input">
                    </td>
                    <td>
                        <input type="text" name="columns[{{ $col_idx }}][default]" value="{{ $column['default'] }}" class="devtool_input" placeholder="NULL">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="columns[{{ $col_idx }}][notnull]" value="Y" {{ isset($column['notnull']) ? 'checked' : '' }}>
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="columns[{{ $col_idx }}][primary_key]" value="Y" {{ isset($column['primary_key']) ? 'checked' : '' }}>
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="columns[{{ $col_idx }}][auto_increment]" value="Y" {{ isset($column['auto_increment']) ? 'checked' : '' }}>
                    </td>
                    <td>
                        <input type="text" name="columns[{{ $col_idx }}][index]" value="{{ $column['index'] }}" class="devtool_input" placeholder="">
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button>
                    </td>
                </tr>
                @php $col_idx++; @endphp
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:10px; text-align:right;">
            <button type="button" class="devtool_btn" onclick="addColumn()">+ Add Column</button>
        </div>
        
        <h3 style="margin-top:30px;">Indexes</h3>
        <table class="devtool_info_table" id="index_table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Columns (comma separated)</th>
                    <th>Type</th>
                    <th style="width:60px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="index_tbody">
                @php $idx_idx = 0; @endphp
                @foreach($schema_info->index as $index)
                <tr>
                    <td><input type="text" name="indexes[{{ $idx_idx }}][name]" value="{{ $index['name'] }}" class="devtool_input"></td>
                    <td><input type="text" name="indexes[{{ $idx_idx }}][columns]" value="{{ $index['columns'] }}" class="devtool_input"></td>
                    <td>
                        <select name="indexes[{{ $idx_idx }}][type]" class="devtool_select">
                            <option value="">(Normal)</option>
                            <option value="unique" {{ (string)$index['type'] === 'unique' ? 'selected' : '' }}>Unique</option>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button>
                    </td>
                </tr>
                @php $idx_idx++; @endphp
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:10px; text-align:right;">
            <button type="button" class="devtool_btn" onclick="addIndex()">+ Add Index</button>
        </div>

        <h3 style="margin-top:30px;">Constraints (Foreign Keys)</h3>
        <table class="devtool_info_table" id="constraint_table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Column</th>
                    <th>References (table.column)</th>
                    <th>On Update</th>
                    <th>On Delete</th>
                    <th style="width:60px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="constraint_tbody">
                @php $const_idx = 0; @endphp
                @foreach($schema_info->constraint as $constraint)
                <tr>
                    <td>
                        <input type="text" name="constraints[{{ $const_idx }}][type]" value="{{ $constraint['type'] }}" class="devtool_input" placeholder="foreign key">
                    </td>
                    <td><input type="text" name="constraints[{{ $const_idx }}][column]" value="{{ $constraint['column'] }}" class="devtool_input"></td>
                    <td><input type="text" name="constraints[{{ $const_idx }}][references]" value="{{ $constraint['references'] }}" class="devtool_input"></td>
                    <td>
                        <select name="constraints[{{ $const_idx }}][onupdate]" class="devtool_select">
                            <option value="">(None)</option>
                            @foreach(['cascade', 'restrict', 'set null', 'no action'] as $opt)
                            <option value="{{ $opt }}" {{ (string)$constraint['onupdate'] === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="constraints[{{ $const_idx }}][ondelete]" class="devtool_select">
                            <option value="">(None)</option>
                            @foreach(['cascade', 'restrict', 'set null', 'no action'] as $opt)
                            <option value="{{ $opt }}" {{ (string)$constraint['ondelete'] === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button>
                    </td>
                </tr>
                @php $const_idx++; @endphp
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:10px; text-align:right;">
            <button type="button" class="devtool_btn" onclick="addConstraint()">+ Add Constraint</button>
        </div>
    </form>

    <h3 style="margin-top:30px;">DDL Query</h3>
    <pre class="devtool_code_block"><code>{{ $query_string }}</code></pre>

    <script>
    var dbTypes = @json($db_types);
    var schemaName = '{{ $schema_name }}';

    function addColumn() {
        var tbody = document.getElementById('schema_tbody');
        var idx = Date.now(); // Unique index based on timestamp
        var tr = document.createElement('tr');
        
        var typeOptions = '<option value="">(Select Type)</option>';
        dbTypes.forEach(function(t) {
            typeOptions += '<option value="' + t + '">' + t + '</option>';
        });
        typeOptions += '<option value="_custom_">(Custom)</option>';

        tr.innerHTML = 
            '<td><input type="text" name="columns[' + idx + '][name]" class="devtool_input" style="font-weight:bold;" oninput="onColumnNameChange(this)"></td>' +
            '<td>' +
                '<div class="type_wrapper" style="position:relative;">' +
                    '<select class="devtool_select type_select" style="width:100%;" onchange="onTypeSelectChange(this)">' +
                        typeOptions +
                    '</select>' +
                    '<div class="custom_input_wrapper" style="display:none; width:100%; position:relative;">' +
                        '<input type="text" class="devtool_input type_input" style="width:100%; padding-right:30px;" oninput="onTypeInputChange(this)">' +
                        '<button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetTypeSelect(this)">X</button>' +
                    '</div>' +
                    '<input type="hidden" name="columns[' + idx + '][type]" class="real_type_input">' +
                '</div>' +
            '</td>' +
            '<td><input type="text" name="columns[' + idx + '][size]" class="devtool_input"></td>' +
            '<td><input type="text" name="columns[' + idx + '][default]" class="devtool_input" placeholder="NULL"></td>' +
            '<td style="text-align:center;"><input type="checkbox" name="columns[' + idx + '][notnull]" value="Y"></td>' +
            '<td style="text-align:center;"><input type="checkbox" name="columns[' + idx + '][primary_key]" value="Y"></td>' +
            '<td style="text-align:center;"><input type="checkbox" name="columns[' + idx + '][auto_increment]" value="Y"></td>' +
            '<td><input type="text" name="columns[' + idx + '][index]" class="devtool_input"></td>' +
            '<td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest(\'tr\').remove()">Del</button></td>';
        tbody.appendChild(tr);
    }

    function addIndex() {
        var tbody = document.getElementById('index_tbody');
        var idx = Date.now();
        var tr = document.createElement('tr');
        tr.innerHTML = 
            '<td><input type="text" name="indexes[' + idx + '][name]" class="devtool_input"></td>' +
            '<td><input type="text" name="indexes[' + idx + '][columns]" class="devtool_input"></td>' +
            '<td><select name="indexes[' + idx + '][type]" class="devtool_select"><option value="">(Normal)</option><option value="unique">Unique</option></select></td>' +
            '<td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest(\'tr\').remove()">Del</button></td>';
        tbody.appendChild(tr);
    }

    function addConstraint() {
        var tbody = document.getElementById('constraint_tbody');
        var idx = Date.now();
        var tr = document.createElement('tr');
        tr.innerHTML = 
            '<td><input type="text" name="constraints[' + idx + '][type]" value="foreign key" class="devtool_input"></td>' +
            '<td><input type="text" name="constraints[' + idx + '][column]" class="devtool_input"></td>' +
            '<td><input type="text" name="constraints[' + idx + '][references]" class="devtool_input"></td>' +
            '<td><select name="constraints[' + idx + '][onupdate]" class="devtool_select"><option value="">(None)</option><option value="cascade">cascade</option><option value="restrict">restrict</option><option value="set null">set null</option><option value="no action">no action</option></select></td>' +
            '<td><select name="constraints[' + idx + '][ondelete]" class="devtool_select"><option value="">(None)</option><option value="cascade">cascade</option><option value="restrict">restrict</option><option value="set null">set null</option><option value="no action">no action</option></select></td>' +
            '<td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest(\'tr\').remove()">Del</button></td>';
        tbody.appendChild(tr);
    }

    function applyDtoClass() {
        var target_module = document.querySelector('input[name="target_module"]').value;
        var schema_name = document.querySelector('input[name="original_schema_name"]').value;
        
        if (!schema_name || schema_name === 'new') {
            alert('Please save the schema first.');
            return;
        }
        exec_json('devtool.procDevtoolSchemaApplyDto', {target_module: target_module, schema_name: schema_name}, function(ret){
            alert(ret.message);
        });
    }

    function saveSchema() {
        var form = document.getElementById('schema_form');
        var params = {
            target_module: form.target_module.value,
            original_schema_name: form.original_schema_name.value,
            schema_name: form.schema_name.value,
            columns: [],
            indexes: [],
            constraints: []
        };

        // Columns
        document.querySelectorAll('#schema_tbody tr').forEach(function(tr) {
            var p = {};
            p.name = tr.querySelector('input[name*="[name]"]').value;
            p.type = tr.querySelector('input[name*="[type]"]').value;
            p.size = tr.querySelector('input[name*="[size]"]').value;
            p.default = tr.querySelector('input[name*="[default]"]').value;
            if(tr.querySelector('input[name*="[notnull]"]').checked) p.notnull = 'Y';
            if(tr.querySelector('input[name*="[primary_key]"]').checked) p.primary_key = 'Y';
            if(tr.querySelector('input[name*="[auto_increment]"]').checked) p.auto_increment = 'Y';
            p.index = tr.querySelector('input[name*="[index]"]').value;
            if(p.name) params.columns.push(p);
        });

        // Indexes
        document.querySelectorAll('#index_tbody tr').forEach(function(tr) {
            var p = {};
            p.name = tr.querySelector('input[name*="[name]"]').value;
            p.columns = tr.querySelector('input[name*="[columns]"]').value;
            p.type = tr.querySelector('select[name*="[type]"]').value;
            if(p.name) params.indexes.push(p);
        });

        // Constraints
        document.querySelectorAll('#constraint_tbody tr').forEach(function(tr) {
            var p = {};
            p.type = tr.querySelector('input[name*="[type]"]').value;
            p.column = tr.querySelector('input[name*="[column]"]').value;
            p.references = tr.querySelector('input[name*="[references]"]').value;
            p.onupdate = tr.querySelector('select[name*="[onupdate]"]').value;
            p.ondelete = tr.querySelector('select[name*="[ondelete]"]').value;
            if(p.column) params.constraints.push(p);
        });

        exec_json('devtool.procDevtoolSchemaUpdate', params, function(ret) {
            if (params.original_schema_name === 'new' && ret.redirect_url) {
                location.replace(ret.redirect_url);
            } else if (ret.redirect_url) {
                location.href = ret.redirect_url;
            } else {
                alert(ret.message);
                location.reload();
            }
        });
    }

    function onTypeSelectChange(select) {
        var val = select.value;
        var wrapper = select.closest('.type_wrapper');
        var hiddenInput = wrapper.querySelector('.real_type_input');
        var customWrapper = wrapper.querySelector('.custom_input_wrapper');
        var customInput = customWrapper.querySelector('input');
        wrapper.removeAttribute('data-auto-set');

        if (val === '_custom_') {
            select.style.display = 'none';
            customWrapper.style.display = 'block';
            customInput.value = '';
            customInput.focus();
            hiddenInput.value = '';
        } else {
            hiddenInput.value = val;
            customInput.value = val;
        }
    }

    function onTypeInputChange(input) {
        var val = input.value;
        var wrapper = input.closest('.type_wrapper');
        var hiddenInput = wrapper.querySelector('.real_type_input');
        wrapper.removeAttribute('data-auto-set');
        hiddenInput.value = val;
    }

    function resetTypeSelect(btn) {
        var customWrapper = btn.closest('.custom_input_wrapper');
        var wrapper = customWrapper.closest('.type_wrapper');
        var select = wrapper.querySelector('select');
        var hiddenInput = wrapper.querySelector('.real_type_input');
        wrapper.removeAttribute('data-auto-set');
        
        customWrapper.style.display = 'none';
        select.style.display = 'block';
        select.value = '';
        hiddenInput.value = '';
    }

    function onColumnNameChange(input) {
        var val = input.value;
        var tr = input.closest('tr');
        var typeInput = tr.querySelector('.real_type_input');
        var wrapper = tr.querySelector('.type_wrapper');
        var isAutoSet = wrapper.getAttribute('data-auto-set') === 'true';
        
        if (typeInput.value && !isAutoSet) return;

        var newType = '';
        if (/_srl$/.test(val) || /_idx$/.test(val)) {
            newType = 'bigint';
        } else if (/_at$/.test(val)) {
            newType = 'datetime';
        } else if (/_count$/.test(val)) {
            newType = 'int';
        } else if (/^is_/.test(val) || /^has_/.test(val)) {
            newType = 'tinyint';
        } else if (/_price$/.test(val) || /_amount$/.test(val) || /_total$/.test(val)) {
            newType = 'decimal';
        } else if (/_date$/.test(val)) {
            newType = '\date';
        }

        if (newType) {
            var select = wrapper.querySelector('select');
            var customWrapper = wrapper.querySelector('.custom_input_wrapper');
            var customInput = customWrapper.querySelector('input');
            var hiddenInput = wrapper.querySelector('.real_type_input');

            select.value = newType;
            hiddenInput.value = newType;
            customInput.value = newType;
            
            select.style.display = 'block';
            customWrapper.style.display = 'none';
            wrapper.setAttribute('data-auto-set', 'true');
        } else if (isAutoSet) {
            var select = wrapper.querySelector('select');
            var customWrapper = wrapper.querySelector('.custom_input_wrapper');
            var customInput = customWrapper.querySelector('input');
            var hiddenInput = wrapper.querySelector('.real_type_input');

            select.value = '';
            hiddenInput.value = '';
            customInput.value = '';
            wrapper.removeAttribute('data-auto-set');
        }
    }

    $(document).ready(function(){
        if (schemaName === 'new') {
            addColumn();
            addIndex();
            addConstraint();
        }
    });
    </script>
</div>