<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function cpt_get_owner_profile_field_schema(): array
{
	return [
		'nationality' => [
			'label' => l10n('Nationality'),
			'type' => 'controlled',
		],
		'city' => [
			'label' => l10n('City'),
			'type' => 'controlled',
		],
		'age' => [
			'label' => l10n('Age'),
			'type' => 'text',
			'max_length' => 120,
		],
		'measurements' => [
			'label' => l10n('Measures'),
			'type' => 'text',
			'max_length' => 255,
		],
		'breasts' => [
			'label' => l10n('Breasts'),
			'type' => 'controlled',
		],
		'eyes' => [
			'label' => l10n('Eyes'),
			'type' => 'text',
			'max_length' => 120,
		],
		'hair' => [
			'label' => l10n('Hair'),
			'type' => 'text',
			'max_length' => 120,
		],
		'private_parts' => [
			'label' => l10n('Private parts'),
			'type' => 'controlled',
		],
		'tattoo' => [
			'label' => l10n('Tattoo'),
			'type' => 'controlled',
		],
		'piercing' => [
			'label' => l10n('Piercing'),
			'type' => 'controlled',
		],
		'experience' => [
			'label' => l10n('Experience'),
			'type' => 'controlled',
		],
		'i_offer' => [
			'label' => l10n('I offer'),
			'type' => 'controlled_multi',
		],
		'other_girls' => [
			'label' => l10n('Other girls'),
			'type' => 'controlled',
		],
		'services_for' => [
			'label' => l10n('Services for'),
			'type' => 'controlled_multi',
		],
		'i_speak' => [
			'label' => l10n('I speak'),
			'type' => 'controlled_multi',
		],
		'contact_number' => [
			'label' => l10n('Contact number'),
			'type' => 'text',
			'max_length' => 64,
		],
		'contact_phone' => [
			'label' => l10n('Phone calls'),
			'type' => 'controlled',
		],
		'contact_sms' => [
			'label' => l10n('SMS'),
			'type' => 'controlled',
		],
		'contact_whatsapp' => [
			'label' => l10n('WhatsApp'),
			'type' => 'controlled',
		],
		'availability_monday' => [
			'label' => l10n('Monday'),
			'type' => 'availability_range',
		],
		'availability_tuesday' => [
			'label' => l10n('Tuesday'),
			'type' => 'availability_range',
		],
		'availability_wednesday' => [
			'label' => l10n('Wednesday'),
			'type' => 'availability_range',
		],
		'availability_thursday' => [
			'label' => l10n('Thursday'),
			'type' => 'availability_range',
		],
		'availability_friday' => [
			'label' => l10n('Friday'),
			'type' => 'availability_range',
		],
		'availability_saturday' => [
			'label' => l10n('Saturday'),
			'type' => 'availability_range',
		],
		'availability_sunday' => [
			'label' => l10n('Sunday'),
			'type' => 'availability_range',
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

	if ($field_key === 'city' && function_exists('cpt_get_owner_profile_city_options')) {
		return cpt_get_owner_profile_city_options();
	}

	if (!isset($conf['core_privacy_toggle_owner_profile_options'][$field_key]) || !is_array($conf['core_privacy_toggle_owner_profile_options'][$field_key])) {
		return cpt_get_owner_profile_builtin_options($field_key);
	}

	return $conf['core_privacy_toggle_owner_profile_options'][$field_key];
}

function cpt_get_owner_profile_builtin_options(string $field_key): array
{
	switch ($field_key) {
		case 'nationality':
			return [
				1 => l10n('Slovak'),
				2 => l10n('Czech'),
				3 => l10n('Ukrainian'),
				4 => l10n('Hungarian'),
				5 => l10n('Polish'),
				6 => l10n('German'),
				7 => l10n('Austrian'),
				8 => l10n('Romanian'),
			];

		case 'breasts':
			return [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7'];

		case 'private_parts':
			return [
				1 => l10n('Shaved'),
				2 => l10n('Partially shaved'),
				3 => l10n('Not shaved'),
			];

		case 'tattoo':
		case 'piercing':
		case 'contact_phone':
		case 'contact_sms':
		case 'contact_whatsapp':
			return [1 => l10n('Yes'), 2 => l10n('No')];

		case 'experience':
			return [1 => l10n('Experienced'), 2 => l10n('Not experienced')];

		case 'i_offer':
			return [1 => l10n('Private flat'), 2 => l10n('Escort')];

		case 'other_girls':
			return [1 => l10n('Alone'), 2 => l10n('Not alone')];

		case 'services_for':
			return [1 => l10n('Men'), 2 => l10n('Women'), 3 => l10n('Couples')];

		case 'i_speak':
			return [
				1 => l10n('Slovak'),
				2 => l10n('Czech'),
				3 => l10n('English'),
				4 => l10n('German'),
				5 => l10n('Hungarian'),
				6 => l10n('Polish'),
				7 => l10n('Ukrainian'),
				8 => l10n('Russian'),
			];
	}

	return [];
}

function cpt_get_owner_profile_availability_time_options(): array
{
	static $options = null;
	if ($options !== null) {
		return $options;
	}

	$options = [];
	$options['unavailable'] = l10n('Unavailable');
	for ($hour = 0; $hour < 24; $hour++) {
		$label = sprintf('%02d:00', $hour);
		$options[$label] = $label;
	}

	return $options;
}

function cpt_is_owner_profile_availability_field(string $field_key): bool
{
	return str_starts_with($field_key, 'availability_');
}