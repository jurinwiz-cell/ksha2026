<div class="section_box">
    <h3>Basic Info</h3>
    <table class="devtool_info_table">
        <tr>
            <th style="width:150px;">Name</th>
            <td><input type="text" name="name" value="{{ $action_info['name'] ?? '' }}" class="devtool_input" required placeholder="dispModuleIndex"></td>
        </tr>
        <tr>
            <th>Class</th>
            <td>
                @php
                    $currentClass = $action_info['class'] ?? '';
                    $isCustomClass = $currentClass && !in_array($currentClass, $controller_classes ?? []);
                @endphp
                <div id="class_wrapper" style="position:relative;">
                    <select id="class_select" class="devtool_select" style="display:{{ $isCustomClass ? 'none' : 'block' }}; width:100%;" onchange="onClassSelectChange(this)">
                        <option value="">(None / Legacy)</option>
                        @if(isset($controller_classes))
                            @foreach($controller_classes as $cls)
                            <option value="{{ $cls }}" {{ $currentClass == $cls ? 'selected' : '' }}>{{ $cls }}</option>
                            @endforeach
                        @endif
                        <option value="_custom_">(Custom)</option>
                    </select>
                    <div id="class_custom_wrapper" style="display:{{ $isCustomClass ? 'block' : 'none' }}; width:100%; position:relative;">
                        <input type="text" id="class_input" value="{{ $currentClass }}" class="devtool_input" style="width:100%; padding-right:30px;" oninput="onClassInputChange(this)" placeholder="Controllers\Index">
                        <button type="button" class="devtool_btn small" style="position:absolute; right:0; top:0; height:100%; border-left:1px solid #ddd;" onclick="resetClassSelect()">X</button>
                    </div>
                    <input type="hidden" name="class" id="real_class_input" value="{{ $currentClass }}">
                </div>
            </td>
        </tr>
        <tr>
            <th>Type</th>
            <td>
                <select name="type" class="devtool_select">
                    <option value="" {{ empty($action_info['type']) ? 'selected' : '' }}>(None / Use Class)</option>
                    <option value="view" {{ ($action_info['type'] ?? '') == 'view' ? 'selected' : '' }}>view</option>
                    <option value="controller" {{ ($action_info['type'] ?? '') == 'controller' ? 'selected' : '' }}>controller</option>
                    <option value="model" {{ ($action_info['type'] ?? '') == 'model' ? 'selected' : '' }}>model</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>Method</th>
            <td><input type="text" name="method" value="{{ $action_info['method'] ?? '' }}" class="devtool_input" placeholder="(Optional)"></td>
        </tr>
    </table>
</div>

<div class="section_box" style="margin-top:20px;">
    <h3>Routing & Permissions</h3>
    <table class="devtool_info_table">
        <tr>
            <th style="width:150px; vertical-align:top; padding-top:12px;">Routes</th>
            <td>
                <table style="width:100%; border-collapse:collapse;" id="routes_table">
                    @if(!empty($action_info['routes']))
                        @foreach($action_info['routes'] as $idx => $route)
                        <tr>
                            <td style="padding-bottom:5px;">
                                <div style="display:flex; gap:5px;">
                                    <input type="text" name="routes[{{ $idx }}][route]" value="{{ $route['route'] }}" class="devtool_input" placeholder="route path (e.g. my/path/$var)" style="flex:1;">
                                    <input type="text" name="routes[{{ $idx }}][priority]" value="{{ $route['priority'] ?? '' }}" class="devtool_input" placeholder="priority" style="width:80px;">
                                    <button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </table>
                <button type="button" class="devtool_btn small" onclick="addRoute()">+ Add Route</button>
            </td>
        </tr>
        <tr>
            <th>Permission</th>
            <td><input type="text" name="permission" value="{{ $action_info['permission'] ?? '' }}" class="devtool_input" placeholder="root, manager, or empty"></td>
        </tr>
        @if(!empty($action_info['ruleset']))
        <tr>
            <th>Ruleset</th>
            <td><input type="text" name="ruleset" value="{{ $action_info['ruleset'] ?? '' }}" class="devtool_input" placeholder="insertConfig"></td>
        </tr>
        @endif
        <tr>
            <th>Check CSRF</th>
            <td>
                <select name="check_csrf" class="devtool_select">
                    <option value="" {{ !isset($action_info['check_csrf']) ? 'selected' : '' }}>(Default: True)</option>
                    <option value="true" {{ ($action_info['check_csrf'] ?? '') == 'true' ? 'selected' : '' }}>True</option>
                    <option value="false" {{ ($action_info['check_csrf'] ?? '') == 'false' ? 'selected' : '' }}>False</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>Standalone</th>
            <td>
                <select name="standalone" class="devtool_select">
                    <option value="" {{ !isset($action_info['standalone']) ? 'selected' : '' }}>(Default: False)</option>
                    <option value="true" {{ ($action_info['standalone'] ?? '') == 'true' ? 'selected' : '' }}>True</option>
                    <option value="false" {{ ($action_info['standalone'] ?? '') == 'false' ? 'selected' : '' }}>False</option>
                </select>
            </td>
        </tr>
    </table>
</div>

<div class="section_box" style="margin-top:20px;">
    <h3>Menu & Display</h3>
    <table class="devtool_info_table">
        <tr>
            <th style="width:150px;">Menu Name</th>
            <td><input type="text" name="menu_name" value="{{ $action_info['menu_name'] ?? '' }}" class="devtool_input"></td>
        </tr>
        <tr>
            <th>Options</th>
            <td>
                <label style="margin-right:15px;">
                    <input type="checkbox" name="index" value="true" {{ ($action_info['index'] ?? '') == 'true' ? 'checked' : '' }}> Index
                </label>
                <label style="margin-right:15px;">
                    <input type="checkbox" name="admin_index" value="true" {{ ($action_info['admin_index'] ?? '') == 'true' ? 'checked' : '' }}> Admin Index
                </label>
                <label style="margin-right:15px;">
                    <input type="checkbox" name="menu_index" value="true" {{ ($action_info['menu_index'] ?? '') == 'true' ? 'checked' : '' }}> Menu Index
                </label>
                <label>
                    <input type="checkbox" name="meta_noindex" value="true" {{ ($action_info['meta_noindex'] ?? '') == 'true' ? 'checked' : '' }}> Meta Noindex
                </label>
            </td>
        </tr>
    </table>
</div>

<script>
function addRoute() {
    var table = document.getElementById('routes_table');
    var idx = Date.now();
    var tr = document.createElement('tr');
    tr.innerHTML = '<td style="padding-bottom:5px;">' +
        '<div style="display:flex; gap:5px;">' +
        '<input type="text" name="routes[' + idx + '][route]" class="devtool_input" placeholder="route path (e.g. my/path/$var)" style="flex:1;">' +
        '<input type="text" name="routes[' + idx + '][priority]" class="devtool_input" placeholder="priority" style="width:80px;">' +
        '<button type="button" class="devtool_btn small danger" onclick="this.closest(\'tr\').remove()">Del</button>' +
        '</div></td>';
    table.appendChild(tr);
}

function onClassSelectChange(select) {
    var val = select.value;
    var realInput = document.getElementById('real_class_input');
    var customWrapper = document.getElementById('class_custom_wrapper');
    var customInput = document.getElementById('class_input');

    if (val === '_custom_') {
        select.style.display = 'none';
        customWrapper.style.display = 'block';
        customInput.value = '';
        customInput.focus();
        realInput.value = '';
    } else {
        realInput.value = val;
        customInput.value = val;
    }
}

function onClassInputChange(input) {
    document.getElementById('real_class_input').value = input.value;
}

function resetClassSelect() {
    var select = document.getElementById('class_select');
    var customWrapper = document.getElementById('class_custom_wrapper');
    var realInput = document.getElementById('real_class_input');
    
    customWrapper.style.display = 'none';
    select.style.display = 'block';
    select.value = '';
    realInput.value = '';
}
</script>