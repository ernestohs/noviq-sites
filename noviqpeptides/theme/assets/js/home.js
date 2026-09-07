/**
 * Homepage carousels — scroll-snap track with prev/next and optional dots.
 */
( function () {
	'use strict';

	function setup( root ) {
		var track = root.querySelector( '[data-nq-carousel-track]' );
		var prev = root.querySelector( '[data-nq-carousel-prev]' );
		var next = root.querySelector( '[data-nq-carousel-next]' );
		var dots = root.querySelector( '[data-nq-carousel-dots]' );
		if ( ! track ) {
			return;
		}

		var items = Array.prototype.slice.call( track.children );
		if ( items.length === 0 ) {
			return;
		}

		function step() {
			var first = items[0];
			if ( ! first ) {
				return 280;
			}
			var style = window.getComputedStyle( track );
			var gap = parseFloat( style.columnGap || style.gap || '0' ) || 0;
			return first.getBoundingClientRect().width + gap;
		}

		function perView() {
			var s = step();
			if ( s <= 0 ) {
				return 1;
			}
			return Math.max( 1, Math.round( track.clientWidth / s ) );
		}

		function scrollByDir( dir ) {
			track.scrollBy( { left: dir * step(), behavior: 'smooth' } );
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				scrollByDir( -1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				scrollByDir( 1 );
			} );
		}

		if ( dots ) {
			var buttons = [];

			function rebuildDots() {
				var view = perView();
				var pageCount = Math.max( 1, Math.ceil( items.length / view ) );
				dots.innerHTML = '';
				buttons = [];
				for ( var i = 0; i < pageCount; i++ ) {
					( function ( index ) {
						var btn = document.createElement( 'button' );
						btn.type = 'button';
						btn.className = 'nq-carousel__dot';
						btn.setAttribute( 'aria-label', 'Go to slide ' + ( index + 1 ) );
						btn.addEventListener( 'click', function () {
							track.scrollTo( { left: index * step() * perView(), behavior: 'smooth' } );
						} );
						dots.appendChild( btn );
						buttons.push( btn );
					} )( i );
				}
				syncDots();
			}

			function syncDots() {
				var view = perView();
				var page = Math.round( track.scrollLeft / ( step() * view ) );
				buttons.forEach( function ( btn, idx ) {
					btn.classList.toggle( 'is-active', idx === page );
				} );
			}

			track.addEventListener( 'scroll', syncDots, { passive: true } );
			window.addEventListener( 'resize', rebuildDots );
			rebuildDots();
		}
	}

	document.querySelectorAll( '[data-nq-carousel]' ).forEach( setup );
}() );
