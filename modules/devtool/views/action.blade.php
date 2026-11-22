@load('style.scss')

<div class="devtool_action_container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Action: {{ $action_name === 'new' ? 'New Action' : $action_name }}</h1>
        <div>
            @if($action_name !== 'new')
            <button type="button" onclick="if(confirm('Are you sure?')) { exec_json('devtool.procDevtoolActionDelete', {target_module:'{{ $target_module }}', action_name:'{{ $action_name }}'}); }" class="devtool_btn danger" style="margin-right:5px;">Delete</button>
            @endif
            <button type="button" onclick="saveAction()" class="devtool_btn">Save Changes</button>
        </div>
    </div>

    <form id="action_form" action="./" method="post">
        <input type="hidden" name="module" value="devtool">
        <input type="hidden" name="act" value="procDevtoolActionUpdate">
        <input type="hidden" name="target_module" value="{{ $target_module }}">
        <input type="hidden" name="original_action_name" value="{{ $action_name }}">

        <!-- Tabs -->
        <div class="devtool_tabs" style="display:flex; border-bottom:1px solid #ddd; margin-bottom:20px;">
            <a href="javascript:void(0);" onclick="switchTab('config')" id="tab_btn_config" style="padding:10px 20px; text-decoration:none; color:#333; border-bottom:2px solid #247bff; font-weight:bold;">Config</a>
            <a href="javascript:void(0);" onclick="switchTab('class')" id="tab_btn_class" style="padding:10px 20px; text-decoration:none; color:#333; border-bottom:2px solid transparent;">Class</a>
        </div>

        <div id="tab_content_config">
            @include('action_config')
        </div>
        <div id="tab_content_class" style="display:none;">
            @include('action_class')
        </div>
    </form>
    <script>
    function switchTab(tab) {
        document.querySelectorAll('.devtool_tabs a').forEach(el => {
            el.style.borderBottom = '2px solid transparent';
            el.style.fontWeight = 'normal';
        });
        document.getElementById('tab_btn_' + tab).style.borderBottom = '2px solid #247bff';
        document.getElementById('tab_btn_' + tab).style.fontWeight = 'bold';
        
        document.getElementById('tab_content_config').style.display = (tab === 'config') ? 'block' : 'none';
        document.getElementById('tab_content_class').style.display = (tab === 'class') ? 'block' : 'none';

        if (tab === 'class') {
            var classVal = document.getElementById('real_class_input').value;
            if (!classVal) {
                document.getElementById('class_generator_content').style.display = 'none';
                document.getElementById('class_generator_message').style.display = 'block';
            } else {
                document.getElementById('class_generator_content').style.display = 'block';
                document.getElementById('class_generator_message').style.display = 'none';
                if (typeof loadClassCode === 'function') {
                    loadClassCode();
                }
            }
        }
    }

    function saveAction() {
        var form = document.getElementById('action_form');
        var params = {
            target_module: form.target_module.value,
            original_action_name: form.original_action_name.value,
            name: form.name.value,
            class: document.getElementById('real_class_input').value,
            type: form.type.value,
            method: form.method.value,
            permission: form.permission.value,
            menu_name: form.menu_name.value,
            routes: []
        };
        
        if(form.ruleset) params.ruleset = form.ruleset.value;
        if(form.check_csrf) params.check_csrf = form.check_csrf.value;
        if(form.standalone) params.standalone = form.standalone.value;
        
        ['index', 'admin_index', 'menu_index', 'meta_noindex'].forEach(function(key) {
            if (form[key] && form[key].checked) params[key] = form[key].value;
        });

        document.querySelectorAll('#routes_table tr').forEach(function(tr) {
            var routeInput = tr.querySelector('input[name*="[route]"]');
            var priorityInput = tr.querySelector('input[name*="[priority]"]');
            if (routeInput && routeInput.value) {
                params.routes.push({
                    route: routeInput.value,
                    priority: priorityInput ? priorityInput.value : ''
                });
            }
        });
        
        exec_json('devtool.procDevtoolActionUpdate', params, function(ret) {
            if (params.original_action_name === 'new' && ret.redirect_url) {
                location.replace(ret.redirect_url);
            } else if (ret.redirect_url) {
                location.href = ret.redirect_url;
            } else {
                alert(ret.message);
                location.reload();
            }
        });
    }
    </script>
</div>