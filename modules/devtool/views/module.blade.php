@load('style.scss')
<div class="devtool_index_container">
	<h1>{{ $module_info->title }} <span style="font-size:0.6em; color:#888">({{ $module_info->module }})</span></h1>
	<p style="margin-bottom: 20px; color: #666;">{{ $module_info->description }}</p>

	<div style="display:flex; gap:10px; margin-bottom:30px;">
		<a href="{{ getUrl('act', 'dispDevtoolModuleInfo', 'target_module', $module_info->module) }}" class="devtool_btn" style="flex:1; text-align:center; padding:12px; font-size:14px;">
			Edit Module Info (info.xml)
		</a>
		<a href="{{ getUrl('act', 'procDevtoolModuleDownload', 'target_module', $module_info->module) }}" class="devtool_btn" style="flex:1; text-align:center; padding:12px; font-size:14px;">
			Download .zip
		</a>
	</div>

	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
		<h3>Actions (conf/module.xml)</h3>
		<a href="{{ getUrl('act', 'dispDevtoolAction', 'target_module', $module_info->module, 'action_name', 'new') }}" class="devtool_btn small">+ Add Action</a>
	</div>
	<table class="devtool_module_table">
		<tbody>
			@if(isset($actions) && count($actions) > 0)
				@foreach($actions as $action)
				<tr>
					<td class="module_info">
						<a href="{{ getUrl('act', 'dispDevtoolAction', 'target_module', $module_info->module, 'action_name', $action['name']) }}" class="module_link">
							<div class="module_name">{{ $action['name'] }}</div>
							<div class="module_desc">{{ $action['type'] ?? $action['class'] ?? '' }}</div>
						</a>
					</td>
					<td class="arrow_cell">&gt;</td>
				</tr>
				@endforeach
			@else
				<tr><td colspan="2" style="padding: 20px; text-align: center; color: #888;">No actions defined.</td></tr>
			@endif
		</tbody>
	</table>

	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; margin-top:30px;">
		<h3>Schemas</h3>
		<a href="{{ getUrl('act', 'dispDevtoolSchema', 'target_module', $module_info->module, 'schema_name', 'new') }}" class="devtool_btn small">+ Add Schema</a>
	</div>
	<table class="devtool_module_table">
		<tbody>
			@if(isset($schemas) && count($schemas) > 0)
				@foreach($schemas as $schema)
				<tr>
					<td class="module_info">
						<a href="{{ getUrl('act', 'dispDevtoolSchema', 'target_module', $module_info->module, 'schema_name', $schema) }}" class="module_link">
							<div class="module_name">{{ $schema }}</div>
							<div class="module_desc">schemas/{{ $schema }}.xml</div>
						</a>
					</td>
					<td class="arrow_cell">
						&gt;
					</td>
				</tr>
				@endforeach
			@else
				<tr><td colspan="2" style="padding: 20px; text-align: center; color: #888;">스키마 파일이 없습니다.</td></tr>
			@endif
		</tbody>
	</table>

	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; margin-top:30px;">
		<h3>Queries</h3>
		<a href="{{ getUrl('act', 'dispDevtoolQuery', 'target_module', $module_info->module, 'query_id', 'new') }}" class="devtool_btn small">+ Add Query</a>
	</div>
	<table class="devtool_module_table">
		<tbody>
			@if(isset($queries) && count($queries) > 0)
				@foreach($queries as $query)
				<tr>
					<td class="module_info">
						<a href="{{ getUrl('act', 'dispDevtoolQuery', 'target_module', $module_info->module, 'query_id', $query) }}" class="module_link">
							<div class="module_name">{{ $query }}</div>
							<div class="module_desc">queries/{{ $query }}.xml</div>
						</a>
					</td>
					<td class="arrow_cell">&gt;</td>
				</tr>
				@endforeach
			@else
				<tr><td colspan="2" style="padding: 20px; text-align: center; color: #888;">쿼리 파일이 없습니다.</td></tr>
			@endif
		</tbody>
	</table>
</div>
