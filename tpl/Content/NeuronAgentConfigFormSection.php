<?php
$agentConfigForm = is_array($runtimeAgentConfigForm ?? null)
	? $runtimeAgentConfigForm
	: [];
$values = is_array($agentConfigForm['values'] ?? null) ? $agentConfigForm['values'] : [];
$llmOptions = is_array($agentConfigForm['llm_options'] ?? null) ? $agentConfigForm['llm_options'] : [];
$contextProfileOptions = is_array($agentConfigForm['context_profile_options'] ?? null) ? $agentConfigForm['context_profile_options'] : [];
$toolProfileOptions = is_array($agentConfigForm['tool_profile_options'] ?? null) ? $agentConfigForm['tool_profile_options'] : [];
$formId = (string)($agentConfigForm['form_id'] ?? 'base3_neuron_agent_config');
$rootId = $formId . '_section';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
$selectedIn = static fn($current, $value): string => in_array((string)$value, array_map('strval', is_array($current) ? $current : []), true) ? ' selected="selected"' : '';
?>
<style>
.base3-neuron-config-root * { box-sizing:border-box; }
.base3-neuron-config-section { margin:0 0 18px; padding:16px; border:1px solid #ddd; border-radius:6px; background:#fff; }
.base3-neuron-config-section h3 { margin:0 0 14px; font-size:18px; }
.base3-neuron-config-row { display:grid; grid-template-columns:minmax(150px,220px) minmax(0,1fr); gap:8px 18px; margin:0 0 14px; }
.base3-neuron-config-row:last-child { margin-bottom:0; }
.base3-neuron-config-label { padding-top:7px; font-weight:600; }
.base3-neuron-config-root input,.base3-neuron-config-root select,.base3-neuron-config-root textarea { width:100%; max-width:760px; min-height:34px; padding:6px 8px; border:1px solid #bbb; border-radius:3px; background:#fff; font:inherit; }
.base3-neuron-config-root textarea { min-height:140px; resize:vertical; font-family:monospace; }
.base3-neuron-config-instructions { min-height:220px !important; }
.base3-neuron-config-help { max-width:800px; margin:5px 0 0; color:#666; font-size:12px; line-height:1.4; }
@media(max-width:700px){.base3-neuron-config-row{display:block}.base3-neuron-config-label{display:block;padding:0;margin:0 0 5px}}
</style>

<div id="<?php echo $e($rootId); ?>" class="base3-neuron-config-root" data-base3-agent-runtime-config-root="neuronai">
	<div class="base3-neuron-config-section">
		<h3>Model</h3>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_llm">Configured LLM</label>
			<div>
				<select id="<?php echo $e($formId); ?>_llm" name="llm" required>
					<option value="">Select configured LLM</option>
<?php foreach ($llmOptions as $option) {
	$id = (string)($option['id'] ?? '');
	if ($id === '') continue;
	$label = (string)($option['label'] ?? $id);
	$model = trim((string)($option['model'] ?? ''));
	$driver = trim((string)($option['driver'] ?? ''));
	$enabled = !array_key_exists('enabled', $option) || !empty($option['enabled']);
?>
					<option value="<?php echo $e($id); ?>"<?php echo $selected($values['llm'] ?? '', $id); ?><?php echo $enabled ? '' : ' disabled'; ?>><?php echo $e($label . ($model !== '' ? ' / ' . $model : '') . ($driver !== '' ? ' [' . $driver . ']' : '') . ($enabled ? '' : ' [disabled]')); ?></option>
<?php } ?>
				</select>
				<p class="base3-neuron-config-help">Provider, model, endpoint, parameters and credentials are resolved from the selected LLM and its referenced connection.</p>
			</div>
		</div>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_context_profile">Context profile</label>
			<div>
				<select id="<?php echo $e($formId); ?>_context_profile" name="context_profile">
					<option value=""<?php echo $selected($values['context_profile'] ?? '', ''); ?>>No context profile</option>
<?php foreach ($contextProfileOptions as $option) {
	$id = (string)($option['id'] ?? '');
	if ($id === '') continue;
	$label = (string)($option['label'] ?? $id);
	$description = trim((string)($option['description'] ?? ''));
?>
					<option value="<?php echo $e($id); ?>"<?php echo $selected($values['context_profile'] ?? '', $id); ?>><?php echo $e($label . ($description !== '' ? ' — ' . $description : '')); ?></option>
<?php } ?>
				</select>
				<p class="base3-neuron-config-help">The selected profile is resolved for every turn. Dynamic page, time and user context is not stored in the conversation history.</p>
			</div>
		</div>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_tool_profiles">Tool profiles</label>
			<div>
				<select id="<?php echo $e($formId); ?>_tool_profiles" name="tool_profiles[]" multiple size="6">
<?php foreach ($toolProfileOptions as $profile) {
	$id = (string)($profile['id'] ?? '');
	if ($id === '') continue;
	$label = (string)($profile['label'] ?? $id);
	$description = trim((string)($profile['description'] ?? ''));
	$toolCount = (int)($profile['tool_count'] ?? 0);
?>
					<option value="<?php echo $e($id); ?>"<?php echo $selectedIn($values['tool_profiles'] ?? [], $id); ?>><?php echo $e($label . ' (' . $toolCount . ')' . ($description !== '' ? ' — ' . $description : '')); ?></option>
<?php } ?>
				</select>
				<p class="base3-neuron-config-help">Selected profiles are shared with MissionBay. Explicitly read-only functions run directly. Mutations are available only when they require approval and satisfy the configured commit-guard rules.</p>
			</div>
		</div>
	</div>

	<div class="base3-neuron-config-section">
		<h3>Instructions and execution</h3>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_instructions">Instructions</label>
			<div><textarea id="<?php echo $e($formId); ?>_instructions" name="neuron_instructions" class="base3-neuron-config-instructions"><?php echo $e($values['neuron_instructions'] ?? ''); ?></textarea><p class="base3-neuron-config-help">When empty, the request system prompt is used.</p></div>
		</div>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_max_tools">Maximum tool runs</label>
			<div><input id="<?php echo $e($formId); ?>_max_tools" type="number" min="1" name="neuron_max_tool_runs" value="<?php echo $e($values['neuron_max_tool_runs'] ?? 10); ?>" /></div>
		</div>
		<div class="base3-neuron-config-row">
			<label class="base3-neuron-config-label" for="<?php echo $e($formId); ?>_mcp">MCP connector</label>
			<div><textarea id="<?php echo $e($formId); ?>_mcp" name="neuron_mcp"><?php echo $e($values['neuron_mcp_json'] ?? '{}'); ?></textarea><p class="base3-neuron-config-help">Optional JSON object with URL or command plus only and exclude lists. Secrets must not be stored in the agent configuration.</p></div>
		</div>
	</div>
</div>

<script>
(function(){
	var root=document.getElementById(<?php echo json_encode($rootId); ?>);if(!root||root.dataset.ready==='1')return;root.dataset.ready='1';
	function setValue(name,value){var field=root.querySelector('[name="'+name.replace(/"/g,'\\"')+'"]');if(field)field.value=value==null?'':String(value)}
	function setMulti(name,values){values=Array.isArray(values)?values.map(String):[];root.querySelectorAll('[name="'+name.replace(/"/g,'\\"')+'"] option').forEach(function(option){option.selected=values.indexOf(String(option.value))!==-1})}
	root.__base3AgentRuntimeConfigUpdateValues=function(values){values=values&&typeof values==='object'?values:{};setValue('llm',values.llm||'');setValue('context_profile',values.context_profile||'');setMulti('tool_profiles[]',values.tool_profiles||[]);setValue('neuron_instructions',values.neuron_instructions||'');setValue('neuron_max_tool_runs',values.neuron_max_tool_runs==null?10:values.neuron_max_tool_runs);setValue('neuron_mcp',values.neuron_mcp_json||'{}')};
	root.__base3AgentRuntimeConfigPrepareSubmit=function(){return true};
})();
</script>
