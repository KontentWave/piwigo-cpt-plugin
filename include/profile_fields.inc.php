<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function cpt_get_owner_profile_field_schema(): array
{
	return [
		'nationality' => [
			'label' => l10n('Nationality'),
			'type' => 'controlled',
		],
		'age' => [
			'label' => l10n('Age'),
			'type' => 'text',
			'max_length' => 120,
		],
		'measurements' => [
			'label' => l10n('Measurements'),
			'type' => 'text',
			'max_length' => 255,
		],
		'eyes' => [
			'label' => l10n('Eyes'),
			'type' => 'controlled',
		],
		'hair' => [
			'label' => l10n('Hair'),
			'type' => 'controlled',
		],
	];
}

function cpt_get_owner_profile_field_definition(string $field_key): ?array
{
	$schema = cpt_get_owner_profile_field_schema();
	return $schema[$field_key] ?? null;
}

function cpt_get_owner_profile_controlled_options(string $field_key): array
{
	global $conf;

	if (!isset($conf['core_privacy_toggle_owner_profile_options'][$field_key]) || !is_array($conf['core_privacy_toggle_owner_profile_options'][$field_key])) {
		return [];
	}

	return $conf['core_privacy_toggle_owner_profile_options'][$field_key];
}