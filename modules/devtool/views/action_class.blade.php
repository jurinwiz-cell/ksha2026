<div class="section_box">
    <h3>Class Generator</h3>
    <div id="class_generator_message" style="display:none; padding:20px; text-align:center; color:#666; background:#f9f9f9; border-radius:5px;">
        <p>Unsupported: Please select a Class in the Config tab first.</p>
    </div>
    <div id="class_generator_content" style="display:none;">
        <div id="class_preview_container">
            <p style="color:#666;">Loading...</p>
        </div>
        <div style="margin-top:15px; text-align:right;">
            <button type="button" class="devtool_btn" onclick="saveClassCode()">Save / Create File</button>
        </div>
    </div>
</div>

<script>
var currentClassCode = '';

function loadClassCode() {
    var classVal = document.getElementById('real_class_input').value;
    var container = document.getElementById('class_preview_container');
    
    if (!classVal || !classVal.startsWith('Controllers\\')) {
        container.innerHTML = '<p style="color:#e53e3e;">Only classes starting with Controllers\\ are supported.</p>';
        return;
    }
    
    var actionName = document.querySelector('input[name="name"]').value;
    if (!actionName) {
         container.innerHTML = '<p style="color:#e53e3e;">Please enter an Action Name in the Config tab.</p>';
         return;
    }

    container.innerHTML = '<p style="color:#666;">Loading...</p>';
    var saveBtn = document.querySelector('#class_generator_content button');
    if(saveBtn) saveBtn.style.display = 'none';
    
    exec_json('devtool.getDevtoolActionClass', {
        target_module: document.querySelector('input[name="target_module"]').value,
        class_name: classVal,
        action_name: actionName
    }, function(ret) {
        currentClassCode = ret.code;
        
        var viewFileMsg = '';
        if (ret.view_file_exists !== null) {
            if (ret.view_file_exists) {
                viewFileMsg = `<p style="margin-top:10px; color:#48bb78;">View file <strong>${escapeHtml(ret.view_file_path)}</strong> already exists.</p>`;
                viewFileMsg += `<pre style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow:auto; font-family:monospace; margin-top:5px;">${escapeHtml(ret.view_code || 'Empty file.')}</pre>`;
            } else {
                viewFileMsg = `<p style="margin-top:10px; color:#f56565;">View file <strong>${escapeHtml(ret.view_file_path)}</strong> does not exist and will be created.</p>`;
            }
        }

        if (ret.method_exists) {
            var msg = `<p>Method <strong>${escapeHtml(actionName)}</strong> already exists in <strong>${escapeHtml(ret.file_path)}</strong>.</p>`;
            container.innerHTML = msg + `<pre style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow:auto; font-family:monospace;">${escapeHtml(ret.code)}</pre>` + viewFileMsg;
            if(saveBtn) saveBtn.style.display = 'none';
        } else {
            var msg = ret.file_exists ? 
                `<p>File <strong>${escapeHtml(ret.file_path)}</strong> exists. The following method will be appended:</p>` : 
                `<p>File <strong>${escapeHtml(ret.file_path)}</strong> does not exist. It will be created with the following content:</p>`;
            
            container.innerHTML = msg + `<pre style="background:#2d3748; color:#e2e8f0; padding:15px; border-radius:5px; overflow:auto; font-family:monospace;">${escapeHtml(ret.code)}</pre>` + viewFileMsg;
            if(saveBtn) saveBtn.style.display = 'inline-block';
        }
    });
}

function saveClassCode() {
    var classVal = document.getElementById('real_class_input').value;
    var actionName = document.querySelector('input[name="name"]').value;
    
    if (!confirm('Do you want to save/create this file?')) return;
    
    exec_json('devtool.procDevtoolActionClassSave', {
        target_module: document.querySelector('input[name="target_module"]').value,
        class_name: classVal,
        action_name: actionName
    }, function(ret) {
        alert(ret.message);
        loadClassCode();
    });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>