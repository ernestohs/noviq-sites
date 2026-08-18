<?php
/**
 * Noviq Peptides storefront profile.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'id'    => 'noviq',
	'theme' => 'noviq-peptides',

	'features' => array(
		'meta_boxes'     => true,
		'volume_breaks'  => true,
		'subscriptions'  => true,
		'age_gate'       => true,
		'ruo'            => true,
		'attestation'    => true,
		'templates'      => true,
		'shortcodes'     => true,
		'product_panels' => true,
		'seo'            => true,
		'compounds'      => true,
		'comparisons'    => true,
		'lots'           => true,
		'research_areas' => true,
		'articles'       => true,
	),

	'site' => array(
		'name'            => 'Noviq Peptides',
		'short_name'      => 'Noviq',
		'domain'          => 'noviq.demo-purposes-only.com',
		'tagline'         => 'Analytical documentation on every lot.',
		'description'     => 'Research-grade peptides supplied with lot-matched analytical documentation. For laboratory research use only — not for human or veterinary use.',
		'legal_entity'    => 'Noviq Labs, Inc.',
		'support_email'   => 'support@noviqpeptides.com',
		'wholesale_email' => 'wholesale@noviqpeptides.com',
		'partner_email'   => 'partners@noviqpeptides.com',
		'phone'           => null,
		'address'         => null,
		'instagram'       => null,
		'youtube'         => null,
		'x'               => null,
	),

	'claims' => array(
		'purity_spec'               => 99,
		'average_purity'            => null,
		'endotoxin_spec'            => 0.05,
		'sterility_incubation_days' => 14,
		'researchers_served'        => null,
		'review_score'              => null,
		'review_count'              => 0,
		'free_shipping_threshold'   => 250,
		'dispatch_cutoff'           => '2:00 PM CT',
		'claim_window_hours'        => 72,
	),

	'ruo_short' => 'For laboratory and research use only. Not for human or veterinary use.',

	'ruo_full' => 'All products supplied by Noviq Peptides are sold strictly as reference materials and reagents for in-vitro laboratory research. They are not drugs, foods, cosmetics, or dietary supplements; they are not approved by the U.S. Food and Drug Administration; and they are not intended to diagnose, treat, cure, or prevent any disease, or for human or veterinary consumption. By placing an order you certify that you are 21 years of age or older, that you are a qualified researcher or purchasing on behalf of a research institution, and that you will handle, store, and dispose of all materials in accordance with applicable law and good laboratory practice.',

	'age_gate' => array(
		'copy_version' => '1',
		'question'     => 'Are you 21 years of age or older?',
	),

	'attestation_text' => 'I certify that I am 21 years of age or older, and that I am a qualified researcher or am purchasing on behalf of a research institution. I understand these materials are supplied for in-vitro laboratory research only and are not for human or veterinary use.',

	'ticker_items' => null,

	'home_content' => null,
);
