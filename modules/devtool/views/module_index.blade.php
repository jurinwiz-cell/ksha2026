@load('style.scss')
<div class="devtool_index_container">
	<div style="display:flex; justify-content:space-between; align-items:center;">
		<h1>DevTool Modules</h1>
		<a href="https://www.poesis.dev/tools/rxmodulegen" target="_blank" class="devtool_btn">+ Add Module</a>
	</div>
	<p style="margin-bottom: 20px; color: #666;">List of modules available for development tools.</p>

	<table class="devtool_module_table">
		<tbody>
            @foreach ($module_list as $key => $val)
			<tr>
				<td class="module_info">
					<!-- 모듈 선택 시 이동할 링크 (예시: dispDevtoolModule) -->
					<a href="{{ getUrl('act', 'dispDevtoolModule', 'target_module', $val->module) }}" class="module_link">
						<div class="module_name">{{ $val->title }}</div>
						<div class="module_desc">{{ $val->module }} / {{ $val->description ?: '-' }}</div>
					</a>
				</td>
				<td class="arrow_cell">
					&gt;
				</td>
			</tr>
            @endforeach
		</tbody>
	</table>
</div>