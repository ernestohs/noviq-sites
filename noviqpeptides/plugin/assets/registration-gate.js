/**
 * Registration gate — progressive enhancement.
 *
 * Both forms already work without JavaScript via ?nq_view= and full page
 * posts. This only swaps tabs without a round trip and keeps the register
 * submit button enabled only when both required checkboxes are checked.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-noviq-reg-gate]' );
	if ( ! root ) {
		return;
	}

	var tabs = root.querySelectorAll( '[data-noviq-reg-tab]' );
	var forms = {
		register: root.querySelector( '[data-noviq-reg-form="register"]' ),
		signin: root.querySelector( '[data-noviq-reg-form="signin"]' ),
	};

	function show( view ) {
		Object.keys( forms ).forEach( function ( key ) {
			var form = forms[ key ];
			if ( ! form ) {
				return;
			}
			var active = key === view;
			form.hidden = ! active;
			form.classList.toggle( 'is-active', active );
		} );

		tabs.forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-noviq-reg-tab' ) === view;
			tab.classList.toggle( 'is-active', active );
			if ( active ) {
				tab.setAttribute( 'aria-current', 'page' );
			} else {
				tab.removeAttribute( 'aria-current' );
			}
		} );

		root.setAttribute( 'data-view', view );

		var focusTarget = forms[ view ] && forms[ view ].querySelector( 'input, select, button' );
		if ( focusTarget ) {
			focusTarget.focus();
		}
	}

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function ( event ) {
			var view = tab.getAttribute( 'data-noviq-reg-tab' );
			if ( ! view || ! forms[ view ] ) {
				return;
			}
			event.preventDefault();
			show( view );
			if ( window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'nq_view', view );
				window.history.replaceState( {}, '', url.toString() );
			}
		} );
	} );

	var registerForm = forms.register;
	if ( registerForm ) {
		var checks = registerForm.querySelectorAll( '[data-noviq-reg-check]' );
		var submit = registerForm.querySelector( '[data-noviq-reg-submit]' );

		function syncSubmit() {
			if ( ! submit || ! checks.length ) {
				return;
			}
			var allChecked = true;
			checks.forEach( function ( box ) {
				if ( ! box.checked ) {
					allChecked = false;
				}
			} );
			submit.disabled = ! allChecked;
		}

		checks.forEach( function ( box ) {
			box.addEventListener( 'change', syncSubmit );
		} );
		syncSubmit();
	}
}() );
