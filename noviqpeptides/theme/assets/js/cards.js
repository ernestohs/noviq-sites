/**
 * Product card interactions: favorites (localStorage), dose chips,
 * ATC → View cart transform after a successful Woo AJAX add, and
 * fly-to-cart dots on adding_to_cart.
 */
( function () {
	'use strict';

	var FAV_KEY = 'nq_favorites';
	var FLY_DOTS = 6;
	var FLY_MS = 620;
	var cfg = window.nqCards || {};

	function readFavs() {
		try {
			var raw = window.localStorage.getItem( FAV_KEY );
			var list = raw ? JSON.parse( raw ) : [];
			return Array.isArray( list ) ? list.map( String ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function writeFavs( list ) {
		try {
			window.localStorage.setItem( FAV_KEY, JSON.stringify( list ) );
		} catch ( e ) {
			/* ignore quota / private mode */
		}
	}

	function formatMoney( amount ) {
		var n = Number( amount );
		if ( ! isFinite( n ) ) {
			return '';
		}
		try {
			return new Intl.NumberFormat( undefined, {
				style: 'currency',
				currency: 'USD',
			} ).format( n );
		} catch ( e ) {
			return '$' + n.toFixed( 2 );
		}
	}

	function syncFavButton( btn, on ) {
		btn.classList.toggle( 'is-active', on );
		btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		btn.setAttribute(
			'aria-label',
			on ? 'Remove from favorites' : 'Add to favorites'
		);
	}

	function setupFavorites() {
		var favs = readFavs();
		document.querySelectorAll( '[data-nq-fav]' ).forEach( function ( btn ) {
			var id = String( btn.getAttribute( 'data-product-id' ) || '' );
			if ( ! id ) {
				return;
			}
			syncFavButton( btn, favs.indexOf( id ) !== -1 );
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				var current = readFavs();
				var idx = current.indexOf( id );
				if ( idx === -1 ) {
					current.push( id );
					syncFavButton( btn, true );
				} else {
					current.splice( idx, 1 );
					syncFavButton( btn, false );
				}
				writeFavs( current );
			} );
		} );
	}

	function cartUrl() {
		if ( cfg.cartUrl ) {
			return cfg.cartUrl;
		}
		if ( window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url ) {
			return window.wc_add_to_cart_params.cart_url;
		}
		return '/cart/';
	}

	function setAtcIcons( atc, mode ) {
		var plus = atc.querySelector( '[data-nq-atc-icon="plus"]' );
		var cart = atc.querySelector( '[data-nq-atc-icon="cart"]' );
		if ( plus ) {
			plus.hidden = mode !== 'plus';
		}
		if ( cart ) {
			cart.hidden = mode !== 'cart';
		}
	}

	function showViewCart( atc ) {
		var label = atc.querySelector( '[data-nq-atc-label]' );
		atc.classList.add( 'is-view-cart', 'added' );
		atc.setAttribute( 'href', cartUrl() );
		if ( label ) {
			label.textContent = cfg.viewCart || 'View cart';
		}
		setAtcIcons( atc, 'cart' );

		var card = atc.closest( '.nq-card' );
		if ( card ) {
			card.querySelectorAll( 'a.added_to_cart' ).forEach( function ( el ) {
				el.remove();
			} );
		}
	}

	/**
	 * Woo AJAX add_to_cart expects the variation post ID as product_id
	 * (it resolves parent + attributes). GET fallback still uses parent + attrs.
	 */
	function buildAtcUrl( parentId, variationId, attrKey, attrValue ) {
		var params = new URLSearchParams();
		params.set( 'add-to-cart', parentId );
		params.set( 'variation_id', variationId );
		params.set( 'quantity', '1' );
		if ( attrKey && attrValue ) {
			params.set( attrKey, attrValue );
		}
		return window.location.origin + '/?' + params.toString();
	}

	function updatePrice( card, btn ) {
		var priceEl = card.querySelector( '[data-nq-price]' );
		if ( ! priceEl ) {
			return;
		}
		var price = parseFloat( btn.getAttribute( 'data-price' ) || '0' );
		var regular = parseFloat( btn.getAttribute( 'data-regular' ) || '0' );
		var onSale = btn.getAttribute( 'data-on-sale' ) === '1' && regular > price && price > 0;

		if ( onSale ) {
			priceEl.innerHTML =
				'<del>' + formatMoney( regular ) + '</del><ins>' + formatMoney( price ) + '</ins>';
		} else if ( price > 0 ) {
			priceEl.textContent = formatMoney( price );
		}
	}

	function updateAtc( card, btn ) {
		var atc = card.querySelector( '[data-nq-atc]' );
		if ( ! atc ) {
			return;
		}
		var variationId = btn.getAttribute( 'data-variation-id' ) || '0';
		var parentId = atc.getAttribute( 'data-parent_id' ) || '';
		var attrKey = btn.getAttribute( 'data-attr-key' ) || '';
		var attrValue = btn.getAttribute( 'data-attr-value' ) || '';
		var label = atc.querySelector( '[data-nq-atc-label]' );

		atc.setAttribute( 'data-variation_id', variationId );
		atc.setAttribute( 'data-attr-key', attrKey );
		atc.setAttribute( 'data-attr-value', attrValue );
		atc.classList.remove( 'is-view-cart', 'added' );

		if ( variationId !== '0' && parentId ) {
			atc.setAttribute( 'data-product_id', variationId );
			atc.setAttribute( 'href', buildAtcUrl( parentId, variationId, attrKey, attrValue ) );
			atc.classList.add( 'ajax_add_to_cart', 'add_to_cart_button' );
			if ( label ) {
				label.textContent = cfg.addToCart || 'Add to cart';
			}
			setAtcIcons( atc, 'plus' );
		}
	}

	function setupDoses() {
		document.querySelectorAll( '.nq-card' ).forEach( function ( card ) {
			var buttons = card.querySelectorAll( '[data-nq-dose]' );
			if ( buttons.length < 2 ) {
				return;
			}
			buttons.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					buttons.forEach( function ( other ) {
						other.classList.toggle( 'is-selected', other === btn );
						other.setAttribute( 'aria-pressed', other === btn ? 'true' : 'false' );
					} );
					updatePrice( card, btn );
					updateAtc( card, btn );
				} );
			} );
		} );
	}

	function setupViewCartTransform() {
		if ( ! window.jQuery ) {
			return;
		}
		window.jQuery( document.body ).on( 'added_to_cart', function ( _event, _fragments, _hash, $button ) {
			var el = $button && $button.length ? $button[ 0 ] : null;
			if ( ! el || ! el.hasAttribute( 'data-nq-atc' ) ) {
				return;
			}
			showViewCart( el );
		} );
	}

	function reducedMotion() {
		return !!(
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		);
	}

	function flyLayer() {
		var layer = document.querySelector( '.nq-fly' );
		if ( ! layer ) {
			layer = document.createElement( 'div' );
			layer.className = 'nq-fly';
			layer.setAttribute( 'aria-hidden', 'true' );
			document.body.appendChild( layer );
		}
		return layer;
	}

	/* The pill is re-rendered by the add_to_cart_fragments filter, so never hold a
	   reference across the flight; re-query when the dots land. */
	function bumpCart() {
		var cart = document.querySelector( '.nq-cart' );
		if ( ! cart ) {
			return;
		}
		cart.classList.remove( 'is-bumped' );
		void cart.offsetWidth;
		cart.classList.add( 'is-bumped' );
		window.setTimeout( function () {
			cart.classList.remove( 'is-bumped' );
		}, 420 );
	}

	/* Ease-out cubic; keeps the arc feeling brisk without WAAPI. */
	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function pointOnArc( from, to, lift, spread, t ) {
		var cx = ( from.x + to.x ) / 2 + spread;
		var cy = Math.min( from.y, to.y ) - lift;
		var m = 1 - t;
		return {
			x: m * m * from.x + 2 * m * t * cx + t * t * to.x,
			y: m * m * from.y + 2 * m * t * cy + t * t * to.y,
		};
	}

	function animateDot( node, from, to, lift, spread, delay, onDone ) {
		var startAt = null;

		function frame( now ) {
			if ( startAt === null ) {
				startAt = now + delay;
			}
			if ( now < startAt ) {
				window.requestAnimationFrame( frame );
				return;
			}
			var raw = Math.min( 1, ( now - startAt ) / FLY_MS );
			var t = easeOutCubic( raw );
			var p = pointOnArc( from, to, lift, spread, t );
			var opacity = t < 0.72 ? 1 : 1 - ( t - 0.72 ) / 0.28;
			node.style.transform =
				'translate3d(' + ( p.x - from.x ) + 'px,' + ( p.y - from.y ) + 'px,0) scale(' + ( 1 - 0.55 * t ) + ')';
			node.style.opacity = String( opacity );
			if ( raw < 1 ) {
				window.requestAnimationFrame( frame );
			} else {
				node.remove();
				if ( onDone ) {
					onDone();
				}
			}
		}

		window.requestAnimationFrame( frame );
	}

	function flyToCart( sourceEl ) {
		var cart = document.querySelector( '.nq-cart' );
		if ( ! cart || reducedMotion() || ! window.requestAnimationFrame ) {
			bumpCart();
			return;
		}

		var a = sourceEl.getBoundingClientRect();
		var b = cart.getBoundingClientRect();
		var from = { x: a.left + a.width / 2, y: a.top + a.height / 2 };
		var to = { x: b.left + b.width / 2, y: b.top + b.height / 2 };

		/* Sticky masthead keeps the pill on-screen; if it is somehow off-screen
		   aim at the top-right corner instead of animating off-viewport. */
		if ( b.bottom < 8 ) {
			to = { x: window.innerWidth - 56, y: 28 };
		}

		var layer = flyLayer();
		var remaining = FLY_DOTS;

		for ( var i = 0; i < FLY_DOTS; i++ ) {
			var dot = document.createElement( 'span' );
			dot.className = 'nq-fly__dot nq-fly__dot--' + ( ( i % FLY_DOTS ) + 1 );
			dot.style.left = from.x + 'px';
			dot.style.top = from.y + 'px';
			layer.appendChild( dot );

			animateDot(
				dot,
				from,
				to,
				90 + i * 24,
				( i - ( FLY_DOTS - 1 ) / 2 ) * 32,
				i * 55,
				function () {
					remaining -= 1;
					if ( remaining === 0 ) {
						bumpCart();
					}
				}
			);
		}
	}

	function setupFlyToCart() {
		if ( ! window.jQuery ) {
			return;
		}
		window.jQuery( document.body ).on( 'adding_to_cart', function ( _event, $button ) {
			var el = $button && $button.length ? $button[ 0 ] : null;
			if ( el && el.hasAttribute( 'data-nq-atc' ) ) {
				flyToCart( el );
			}
		} );
	}

	setupFavorites();
	setupDoses();
	setupViewCartTransform();
	setupFlyToCart();
}() );
