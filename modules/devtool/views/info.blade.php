@load('style.scss')

<div class="devtool_info_container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Module Info: {{ $target_module }}</h1>
        <button type="button" class="devtool_btn" onclick="saveInfo()">Save Changes</button>
    </div>

    <form id="info_form">
        <input type="hidden" name="target_module" value="{{ $target_module }}">
        
        <div class="section_box">
            <h3>Basic Information</h3>
            <table class="devtool_info_table">
                <tr>
                    <th style="width:150px;">Version</th>
                    <td><input type="text" name="version" value="{{ $module_info_xml['version'] }}" class="devtool_input"></td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <input type="date" name="date" value="{{ $module_info_xml['date'] }}" class="devtool_input">
                            <button type="button" class="devtool_btn small" onclick="setToday()">Today</button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td>
                        <select name="category" class="devtool_select">
                            @foreach(['accessory', 'construction', 'content', 'member', 'migration', 'service', 'statistics', 'system', 'utility'] as $cat)
                            <option value="{{ $cat }}" {{ $module_info_xml['category'] == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section_box" style="margin-top:20px;">
            <h3>Titles</h3>
            <table class="devtool_info_table" id="table_titles">
                <thead><tr><th style="width:100px;">Lang</th><th>Title</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    @foreach($module_info_xml['title'] as $item)
                    <tr>
                        <td><input type="text" name="title_lang[]" value="{{ $item['lang'] }}" class="devtool_input" placeholder="ko"></td>
                        <td><input type="text" name="title[]" value="{{ $item['value'] }}" class="devtool_input"></td>
                        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addLocalizedRow('table_titles', 'title')">+ Add Title</button></div>
        </div>

        <div class="section_box" style="margin-top:20px;">
            <h3>Descriptions</h3>
            <table class="devtool_info_table" id="table_descriptions">
                <thead><tr><th style="width:100px;">Lang</th><th>Description</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    @foreach($module_info_xml['description'] as $item)
                    <tr>
                        <td><input type="text" name="description_lang[]" value="{{ $item['lang'] }}" class="devtool_input" placeholder="ko"></td>
                        <td><input type="text" name="description[]" value="{{ $item['value'] }}" class="devtool_input" style="width:100%;"></td>
                        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addLocalizedRow('table_descriptions', 'description')">+ Add Description</button></div>
        </div>

        <div id="authors_wrapper">
            @if(count($module_info_xml['author']) > 0)
                @foreach($module_info_xml['author'] as $index => $author)
                <div class="section_box author_block" style="margin-top:20px; border-left: 5px solid #666; padding-left:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Author #{{ $index + 1 }}</h3>
                        <button type="button" class="devtool_btn small danger" onclick="this.closest('.author_block').remove()">Remove Author</button>
                    </div>
                    <table class="devtool_info_table">
                        <tr>
                            <th style="width:150px;">Email</th>
                            <td><input type="text" class="devtool_input author_email" value="{{ $author['email_address'] }}"></td>
                        </tr>
                        <tr>
                            <th>Link</th>
                            <td><input type="text" class="devtool_input author_link" value="{{ $author['link'] }}"></td>
                        </tr>
                    </table>
                    
                    <h4 style="margin-top:15px;">Author Names</h4>
                    <table class="devtool_info_table author_names_table">
                        <thead><tr><th style="width:100px;">Lang</th><th>Name</th><th style="width:60px;"></th></tr></thead>
                        <tbody>
                            @foreach($author['name'] as $name)
                            <tr>
                                <td><input type="text" name="author_name_lang[]" value="{{ $name['lang'] }}" class="devtool_input" placeholder="ko"></td>
                                <td><input type="text" name="author_name[]" value="{{ $name['value'] }}" class="devtool_input"></td>
                                <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addAuthorNameRow(this)">+ Add Name</button></div>
                </div>
                @endforeach
            @endif
        </div>
        <div style="margin-top:10px; text-align:center;">
            <button type="button" class="devtool_btn" onclick="addAuthorBlock()">+ Add New Author</button>
        </div>
        
        <div class="section_box" style="margin-top:20px;">
            <h3>License</h3>
            <table class="devtool_info_table">
                <tr>
                    <th style="width:150px;">License Name</th>
                    <td><input type="text" name="license" value="{{ $module_info_xml['license']['text'] ?? '' }}" class="devtool_input"></td>
                </tr>
                <tr>
                    <th>Link</th>
                    <td><input type="text" name="license_link" value="{{ $module_info_xml['license']['link'] ?? '' }}" class="devtool_input"></td>
                </tr>
            </table>
        </div>
    </form>
</div>

<script>
function addLocalizedRow(tableId, fieldName) {
    var tbody = document.querySelector('#' + tableId + ' tbody');
    var tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="${fieldName}_lang[]" class="devtool_input" placeholder="ko"></td>
        <td><input type="text" name="${fieldName}[]" class="devtool_input" style="width:100%;"></td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
    `;
    tbody.appendChild(tr);
}

function addAuthorBlock() {
    var wrapper = document.getElementById('authors_wrapper');
    var count = wrapper.querySelectorAll('.author_block').length + 1;
    var div = document.createElement('div');
    div.className = 'section_box author_block';
    div.style.marginTop = '20px';
    div.style.borderLeft = '5px solid #666';
    div.style.paddingLeft = '16px';
    div.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Author #${count}</h3>
            <button type="button" class="devtool_btn small danger" onclick="this.closest('.author_block').remove()">Remove Author</button>
        </div>
        <table class="devtool_info_table">
            <tr>
                <th style="width:150px;">Email</th>
                <td><input type="text" class="devtool_input author_email" value=""></td>
            </tr>
            <tr>
                <th>Link</th>
                <td><input type="text" class="devtool_input author_link" value=""></td>
            </tr>
        </table>
        
        <h4 style="margin-top:15px;">Author Names</h4>
        <table class="devtool_info_table author_names_table">
            <thead><tr><th style="width:100px;">Lang</th><th>Name</th><th style="width:60px;"></th></tr></thead>
            <tbody>
                <tr>
                    <td><input type="text" name="author_name_lang[]" class="devtool_input" placeholder="ko"></td>
                    <td><input type="text" name="author_name[]" class="devtool_input"></td>
                    <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
                </tr>
            </tbody>
        </table>
        <div style="text-align:right; margin-top:5px;"><button type="button" class="devtool_btn small" onclick="addAuthorNameRow(this)">+ Add Name</button></div>
    `;
    wrapper.appendChild(div);
}

function addAuthorNameRow(btn) {
    var table = btn.closest('.author_block').querySelector('.author_names_table tbody');
    var tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="author_name_lang[]" class="devtool_input" placeholder="ko"></td>
        <td><input type="text" name="author_name[]" class="devtool_input"></td>
        <td style="text-align:center;"><button type="button" class="devtool_btn small danger" onclick="this.closest('tr').remove()">Del</button></td>
    `;
    table.appendChild(tr);
}

function setToday() {
    var dateInput = document.querySelector('input[name="date"]');
    var today = new Date();
    var yyyy = today.getFullYear();
    var mm = String(today.getMonth() + 1).padStart(2, '0');
    var dd = String(today.getDate()).padStart(2, '0');
    dateInput.value = `${yyyy}-${mm}-${dd}`;
}

function saveInfo() {
    var form = document.getElementById('info_form');
    var finalParams = {
        target_module: form.target_module.value,
        version: form.version.value,
        date: form.date.value,
        category: form.category.value,
        license: form.license.value,
        license_link: form.license_link.value,
        
        title: [], title_lang: [],
        description: [], description_lang: []
    };
    
    function collect(name, target, targetLang) {
        var inputs = form.querySelectorAll(`input[name="${name}[]"]`);
        var langs = form.querySelectorAll(`input[name="${name}_lang[]"]`);
        inputs.forEach((input, i) => {
            finalParams[target].push(input.value);
            finalParams[targetLang].push(langs[i].value);
        });
    }
    
    collect('title', 'title', 'title_lang');
    collect('description', 'description', 'description_lang');

    var authors = [];
    document.querySelectorAll('.author_block').forEach(function(block) {
        var email = block.querySelector('.author_email').value;
        var link = block.querySelector('.author_link').value;
        var names = [];
        block.querySelectorAll('.author_names_table tbody tr').forEach(function(tr) {
            var lang = tr.querySelector('input[name="author_name_lang[]"]').value;
            var val = tr.querySelector('input[name="author_name[]"]').value;
            if(val) names.push({lang: lang, value: val});
        });
        authors.push({email_address: email, link: link, names: names});
    });
    finalParams.authors = authors;

    exec_json('devtool.procDevtoolModuleInfoSave', finalParams, function(ret){
        alert(ret.message);
        location.reload();
    });
}
</script>