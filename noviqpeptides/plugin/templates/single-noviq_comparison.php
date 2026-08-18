<?php
/**
 * Compound comparison — /compare/{slug}
 *
 * The table is generated from the linked compound records, so a comparison can
 * never disagree with the monographs it compares.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

use Noviq\Core\Content\Compound;
use Noviq\Core\Meta;

defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$compounds = array();
foreach ( Meta::comparison_compound_ids( get_the_ID() ) as $compound_id ) {
	$compound = Compound::from_id( $compound_id );
	if ( null !== $compound ) {
		$compounds[] = $compound;
	}
}
?>

<main id="main" class="noviq-page noviq-comparison__main">
	<article class="noviq-comparison__article">

		<header class="noviq-comparison__header">
			<p class="noviq-kicker"><?php esc_html_e( 'Comparison', 'noviq-core' ); ?></p>
			<h1 class="noviq-comparison__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="noviq-comparison__precis"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( array() !== $compounds ) : ?>
			<section class="noviq-comparison__table-wrap">
				<h2><?php esc_html_e( 'Side by side', 'noviq-core' ); ?></h2>

				<div class="noviq-scroll-x">
					<table class="noviq-spec noviq-compare-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Property', 'noviq-core' ); ?></th>
								<?php foreach ( $compounds as $compound ) : ?>
									<th scope="col">
										<a href="<?php echo esc_url( $compound->permalink() ); ?>"><?php echo esc_html( $compound->name() ); ?></a>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php
							$row_count = count( $compounds[0]->spec_rows() );
							for ( $i = 0; $i < $row_count; $i++ ) :
								$first = $compounds[0]->spec_rows()[ $i ];
								?>
								<tr>
									<th scope="row"><?php echo esc_html( $first['label'] ); ?></th>
									<?php
									foreach ( $compounds as $compound ) :
										$row = $compound->spec_rows()[ $i ] ?? null;
										?>
										<td class="<?php echo esc_attr( $first['mono'] ? 'noviq-num' : '' ); ?>">
											<?php echo esc_html( null !== $row ? $row['value'] : Compound::EM_DASH ); ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endfor; ?>

							<tr>
								<th scope="row"><?php esc_html_e( 'Primary sequence', 'noviq-core' ); ?></th>
								<?php foreach ( $compounds as $compound ) : ?>
									<td class="noviq-num noviq-seq"><?php echo esc_html( $compound->sequence() ?? Compound::EM_DASH ); ?></td>
								<?php endforeach; ?>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<section class="noviq-comparison__narrative">
			<?php the_content(); ?>
		</section>

	</article>
</main>

<?php
get_footer();
