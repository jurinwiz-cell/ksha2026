@load('style.scss')
<div class="devtool_index_container">
	<h1>DevTool</h1>
	<p style="margin-bottom: 20px; color: #666;">Welcome to the DevTool module.</p>

	<table class="devtool_module_table">
		<tbody>
			<tr>
				<td class="module_info">
					<a href="{{ getUrl('act', 'dispDevtoolModuleIndex') }}" class="module_link">
						<div class="module_name">Modules</div>
						<div class="module_desc">List of modules available for development tools.</div>
					</a>
				</td>
				<td class="arrow_cell">
					&gt;
				</td>
			</tr>
		</tbody>
	</table>
</div>