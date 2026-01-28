/**
 * Woo Fast Filter - Frontend JavaScript.
 *
 * Vanilla JS for minimal footprint.
 * No framework dependencies - runs on any browser supporting ES6+.
 *
 * Free vs Pro features in this file:
 *   Free:
 *   - AbortController (correctness: prevents stale responses).
 *   - DocumentFragment (standard rendering, not a scaling optimization).
 *   - Manual "Apply filters" button workflow.
 *
 *   Pro (present but inactive in Free):
 *   - Debounce utility — only invoked when autoApply=true (Pro).
 *     In Free, autoApply is always false (enforced server-side),
 *     so the debounce function is never called.
 *
 * @package WooFastFilter
 */

( function () {
	'use strict';

	/**
	 * Debounce utility.
	 *
	 * Pro feature — only invoked when autoApply is true.
	 * In Free, autoApply is forced to false by PHP, so this function
	 * is never called. It remains in the codebase so Pro can activate
	 * it without shipping a separate JS bundle.
	 */
	function debounce( fn, delay ) {
		var timer;
		return function () {
			var context = this;
			var args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( context, args );
			}, delay );
		};
	}

	/**
	 * Main filter controller.
	 * One instance per .wff-wrapper on the page.
	 */
	function WFFController( wrapper ) {
		this.wrapper = wrapper;
		this.config = window.wffConfig || {};
		this.abortController = null;
		this.currentPage = 1;

		// Read block attributes from data attributes.
		this.autoApply = wrapper.dataset.autoApply === 'true';
		this.showActive = wrapper.dataset.showActive === 'true';
		this.layout = wrapper.dataset.layout || 'sidebar';

		// Cache DOM references for performance - avoids repeated querySelector calls.
		this.dom = {
			mobileToggle: wrapper.querySelector( '.wff-mobile-toggle' ),
			panel: wrapper.querySelector( '.wff-panel' ),
			panelClose: wrapper.querySelector( '.wff-panel-close' ),
			overlay: wrapper.querySelector( '.wff-overlay' ),
			form: wrapper.querySelector( '.wff-form' ),
			activeFilters: wrapper.querySelector( '.wff-active-filters' ),
			activeTags: wrapper.querySelector( '.wff-active-tags' ),
			clearAll: wrapper.querySelector( '.wff-clear-all' ),
			grid: wrapper.querySelector( '.wff-products-grid' ),
			loading: wrapper.querySelector( '.wff-loading' ),
			noResults: wrapper.querySelector( '.wff-no-results' ),
			pagination: wrapper.querySelector( '.wff-pagination' ),
			resultsCount: wrapper.querySelector( '.wff-results-count' ),
			sortSelect: wrapper.querySelector( '.wff-sort-select' ),
			priceMin: wrapper.querySelector( '.wff-price-input[name="min_price"]' ),
			priceMax: wrapper.querySelector( '.wff-price-input[name="max_price"]' ),
			rangeMin: wrapper.querySelector( '.wff-range-min' ),
			rangeMax: wrapper.querySelector( '.wff-range-max' ),
		};

		this.init();
	}

	WFFController.prototype = {
		/**
		 * Initialize event listeners and load initial products.
		 */
		init: function () {
			this.bindEvents();
			this.fetchProducts();
		},

		/**
		 * Bind all event listeners.
		 * Uses event delegation on the form to minimize listeners.
		 */
		bindEvents: function () {
			var self = this;

			// Mobile toggle.
			if ( this.dom.mobileToggle ) {
				this.dom.mobileToggle.addEventListener( 'click', function () {
					self.openPanel();
				} );
			}

			// Close panel.
			if ( this.dom.panelClose ) {
				this.dom.panelClose.addEventListener( 'click', function () {
					self.closePanel();
				} );
			}

			// Overlay click.
			if ( this.dom.overlay ) {
				this.dom.overlay.addEventListener( 'click', function () {
					self.closePanel();
				} );
			}

			// Form changes (event delegation).
			if ( this.dom.form ) {
				// Checkbox changes.
				this.dom.form.addEventListener( 'change', function ( e ) {
					if ( e.target.classList.contains( 'wff-checkbox' ) ) {
						self.currentPage = 1;
						if ( self.autoApply ) {
							self.debouncedFetch();
						}
						self.updateActiveFilters();
					}
				} );

				// Form submit.
				this.dom.form.addEventListener( 'submit', function ( e ) {
					e.preventDefault();
					self.currentPage = 1;
					self.fetchProducts();
					self.closePanel();
				} );
			}

			// Price inputs.
			if ( this.dom.priceMin ) {
				this.dom.priceMin.addEventListener( 'input', debounce( function () {
					self.syncRangeFromInput( 'min' );
					if ( self.autoApply ) {
						self.currentPage = 1;
						self.debouncedFetch();
					}
					self.updateActiveFilters();
				}, 500 ) );
			}

			if ( this.dom.priceMax ) {
				this.dom.priceMax.addEventListener( 'input', debounce( function () {
					self.syncRangeFromInput( 'max' );
					if ( self.autoApply ) {
						self.currentPage = 1;
						self.debouncedFetch();
					}
					self.updateActiveFilters();
				}, 500 ) );
			}

			// Range sliders.
			if ( this.dom.rangeMin ) {
				this.dom.rangeMin.addEventListener( 'input', function () {
					self.syncInputFromRange( 'min' );
					if ( self.autoApply ) {
						self.currentPage = 1;
						self.debouncedFetch();
					}
					self.updateActiveFilters();
				} );
			}

			if ( this.dom.rangeMax ) {
				this.dom.rangeMax.addEventListener( 'input', function () {
					self.syncInputFromRange( 'max' );
					if ( self.autoApply ) {
						self.currentPage = 1;
						self.debouncedFetch();
					}
					self.updateActiveFilters();
				} );
			}

			// Clear all.
			if ( this.dom.clearAll ) {
				this.dom.clearAll.addEventListener( 'click', function () {
					self.clearFilters();
				} );
			}

			// Sort.
			if ( this.dom.sortSelect ) {
				this.dom.sortSelect.addEventListener( 'change', function () {
					self.currentPage = 1;
					self.fetchProducts();
				} );
			}

			// Collapsible groups.
			var legends = this.wrapper.querySelectorAll( '.wff-group-title' );
			for ( var i = 0; i < legends.length; i++ ) {
				legends[ i ].addEventListener( 'click', function () {
					var expanded = this.getAttribute( 'aria-expanded' ) !== 'false';
					this.setAttribute( 'aria-expanded', ! expanded );
					var content = this.nextElementSibling;
					if ( content ) {
						content.hidden = expanded;
					}
				} );
			}

			// Escape key closes panel.
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					self.closePanel();
				}
			} );
		},

		/**
		 * Debounced fetch for auto-apply mode.
		 * 300ms delay prevents excessive requests during rapid checkbox toggling.
		 *
		 * Pro feature — only called when this.autoApply is true.
		 * In Free, autoApply is always false (server-enforced), so this
		 * method is never invoked.
		 */
		debouncedFetch: debounce( function () {
			this.fetchProducts();
		}, 300 ),

		/**
		 * Open the filter panel (mobile/modal).
		 */
		openPanel: function () {
			if ( this.dom.panel ) {
				this.dom.panel.classList.add( 'is-open' );
			}
			if ( this.dom.overlay ) {
				this.dom.overlay.classList.add( 'is-visible' );
			}
			if ( this.dom.mobileToggle ) {
				this.dom.mobileToggle.setAttribute( 'aria-expanded', 'true' );
			}
			document.body.style.overflow = 'hidden';
		},

		/**
		 * Close the filter panel.
		 */
		closePanel: function () {
			if ( this.dom.panel ) {
				this.dom.panel.classList.remove( 'is-open' );
			}
			if ( this.dom.overlay ) {
				this.dom.overlay.classList.remove( 'is-visible' );
			}
			if ( this.dom.mobileToggle ) {
				this.dom.mobileToggle.setAttribute( 'aria-expanded', 'false' );
			}
			document.body.style.overflow = '';
		},

		/**
		 * Sync price range slider from number input.
		 */
		syncRangeFromInput: function ( type ) {
			if ( type === 'min' && this.dom.rangeMin && this.dom.priceMin ) {
				this.dom.rangeMin.value = this.dom.priceMin.value;
			} else if ( type === 'max' && this.dom.rangeMax && this.dom.priceMax ) {
				this.dom.rangeMax.value = this.dom.priceMax.value;
			}
		},

		/**
		 * Sync number input from price range slider.
		 */
		syncInputFromRange: function ( type ) {
			if ( type === 'min' && this.dom.priceMin && this.dom.rangeMin ) {
				var val = parseInt( this.dom.rangeMin.value, 10 );
				var maxVal = parseInt( this.dom.rangeMax.value, 10 );
				if ( val > maxVal ) {
					this.dom.rangeMin.value = maxVal;
					val = maxVal;
				}
				this.dom.priceMin.value = val;
			} else if ( type === 'max' && this.dom.priceMax && this.dom.rangeMax ) {
				var val2 = parseInt( this.dom.rangeMax.value, 10 );
				var minVal = parseInt( this.dom.rangeMin.value, 10 );
				if ( val2 < minVal ) {
					this.dom.rangeMax.value = minVal;
					val2 = minVal;
				}
				this.dom.priceMax.value = val2;
			}
		},

		/**
		 * Collect current filter state from the form.
		 */
		getFilterParams: function () {
			var params = {
				page: this.currentPage,
				per_page: 12,
			};

			// Categories.
			var catCheckboxes = this.wrapper.querySelectorAll( 'input[name="categories[]"]:checked' );
			if ( catCheckboxes.length > 0 ) {
				params.categories = [];
				for ( var i = 0; i < catCheckboxes.length; i++ ) {
					params.categories.push( parseInt( catCheckboxes[ i ].value, 10 ) );
				}
			}

			// Attributes.
			var attrGroups = this.wrapper.querySelectorAll( '[data-filter="attribute"]' );
			var attributes = {};
			var hasAttrs = false;
			for ( var j = 0; j < attrGroups.length; j++ ) {
				var taxonomy = attrGroups[ j ].dataset.taxonomy;
				var checked = attrGroups[ j ].querySelectorAll( 'input:checked' );
				if ( checked.length > 0 ) {
					attributes[ taxonomy ] = [];
					for ( var k = 0; k < checked.length; k++ ) {
						attributes[ taxonomy ].push( parseInt( checked[ k ].value, 10 ) );
					}
					hasAttrs = true;
				}
			}
			if ( hasAttrs ) {
				params.attributes = attributes;
			}

			// Price.
			if ( this.dom.priceMin && this.dom.priceMax ) {
				var minDefault = parseInt( this.dom.priceMin.getAttribute( 'min' ), 10 );
				var maxDefault = parseInt( this.dom.priceMax.getAttribute( 'max' ), 10 );
				var minVal = parseInt( this.dom.priceMin.value, 10 );
				var maxVal = parseInt( this.dom.priceMax.value, 10 );

				if ( minVal > minDefault ) {
					params.min_price = minVal;
				}
				if ( maxVal < maxDefault ) {
					params.max_price = maxVal;
				}
			}

			// Sorting.
			if ( this.dom.sortSelect ) {
				var sortVal = this.dom.sortSelect.value;
				if ( sortVal === 'price-asc' ) {
					params.orderby = 'price';
					params.order = 'ASC';
				} else if ( sortVal === 'price-desc' ) {
					params.orderby = 'price';
					params.order = 'DESC';
				} else if ( sortVal !== 'menu_order' ) {
					params.orderby = sortVal;
				}
			}

			return params;
		},

		/**
		 * Fetch products from the REST endpoint.
		 *
		 * Uses AbortController to cancel previous in-flight requests.
		 * This prevents race conditions where an older request might
		 * resolve after a newer one.
		 */
		fetchProducts: function () {
			var self = this;

			// Cancel any in-flight request.
			if ( this.abortController ) {
				this.abortController.abort();
			}
			this.abortController = new AbortController();

			// Show loading state.
			this.setLoading( true );

			var params = this.getFilterParams();
			var url = this.buildUrl( this.config.restUrl + '/products', params );

			fetch( url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': this.config.nonce,
				},
				signal: this.abortController.signal,
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Request failed: ' + response.status );
					}
					return response.json();
				} )
				.then( function ( data ) {
					self.renderProducts( data.products );
					self.renderPagination( data.pagination );
					self.updateResultsCount( data.pagination.total );
					self.setLoading( false );
				} )
				.catch( function ( error ) {
					// Ignore abort errors - they're expected.
					if ( error.name !== 'AbortError' ) {
						console.error( 'WFF fetch error:', error );
						self.setLoading( false );
					}
				} );
		},

		/**
		 * Build URL with query parameters.
		 * Handles nested objects (attributes) by serializing them.
		 */
		buildUrl: function ( base, params ) {
			var url = new URL( base, window.location.origin );

			Object.keys( params ).forEach( function ( key ) {
				var value = params[ key ];
				if ( Array.isArray( value ) ) {
					value.forEach( function ( v ) {
						url.searchParams.append( key + '[]', v );
					} );
				} else if ( typeof value === 'object' && value !== null ) {
					// Nested object (attributes).
					Object.keys( value ).forEach( function ( subKey ) {
						if ( Array.isArray( value[ subKey ] ) ) {
							value[ subKey ].forEach( function ( v ) {
								url.searchParams.append(
									key + '[' + subKey + '][]',
									v
								);
							} );
						}
					} );
				} else {
					url.searchParams.set( key, value );
				}
			} );

			return url.toString();
		},

		/**
		 * Toggle loading state.
		 */
		setLoading: function ( isLoading ) {
			if ( this.dom.loading ) {
				this.dom.loading.hidden = ! isLoading;
			}
			if ( this.dom.grid ) {
				this.dom.grid.style.opacity = isLoading ? '0.5' : '1';
			}
			if ( this.dom.noResults ) {
				this.dom.noResults.hidden = true;
			}
		},

		/**
		 * Render products into the grid.
		 *
		 * Uses DocumentFragment to batch DOM writes.
		 * This triggers only one reflow instead of one per product card.
		 */
		renderProducts: function ( products ) {
			var grid = this.dom.grid;
			if ( ! grid ) return;

			// Show no results message.
			if ( ! products || products.length === 0 ) {
				grid.innerHTML = '';
				if ( this.dom.noResults ) {
					this.dom.noResults.hidden = false;
				}
				return;
			}

			if ( this.dom.noResults ) {
				this.dom.noResults.hidden = true;
			}

			// Build all cards in a DocumentFragment for performance.
			var fragment = document.createDocumentFragment();

			for ( var i = 0; i < products.length; i++ ) {
				fragment.appendChild( this.createProductCard( products[ i ] ) );
			}

			grid.innerHTML = '';
			grid.appendChild( fragment );
		},

		/**
		 * Create a single product card element.
		 */
		createProductCard: function ( product ) {
			var card = document.createElement( 'a' );
			card.className = 'wff-product-card';
			card.href = product.permalink;

			var html = '';

			// Image.
			html += '<div class="wff-product-image">';
			if ( product.image && product.image.src ) {
				html +=
					'<img src="' +
					this.escAttr( product.image.src ) +
					'"' +
					( product.image.srcset
						? ' srcset="' + this.escAttr( product.image.srcset ) + '"'
						: '' ) +
					' alt="' +
					this.escAttr( product.image.alt || product.name ) +
					'"' +
					' loading="lazy" />';
			}
			if ( product.on_sale ) {
				html +=
					'<span class="wff-product-badge wff-badge-sale">Sale</span>';
			} else if ( ! product.in_stock ) {
				html +=
					'<span class="wff-product-badge wff-badge-out">Sold out</span>';
			}
			html += '</div>';

			// Name.
			html +=
				'<h3 class="wff-product-name">' +
				this.escHtml( product.name ) +
				'</h3>';

			// Price.
			if ( product.price && product.price.html ) {
				html +=
					'<div class="wff-product-price">' +
					product.price.html +
					'</div>';
			}

			// Rating.
			if ( product.rating && product.rating.average > 0 ) {
				html += '<div class="wff-product-rating">';
				html +=
					'<span class="wff-stars">' +
					this.renderStars( product.rating.average ) +
					'</span>';
				html +=
					'<span>(' + product.rating.count + ')</span>';
				html += '</div>';
			}

			card.innerHTML = html;
			return card;
		},

		/**
		 * Render star rating.
		 */
		renderStars: function ( rating ) {
			var stars = '';
			for ( var i = 1; i <= 5; i++ ) {
				if ( i <= Math.round( rating ) ) {
					stars += '\u2605'; // Filled star.
				} else {
					stars += '\u2606'; // Empty star.
				}
			}
			return stars;
		},

		/**
		 * Render pagination controls.
		 */
		renderPagination: function ( pagination ) {
			var container = this.dom.pagination;
			if ( ! container ) return;

			if ( pagination.total_pages <= 1 ) {
				container.innerHTML = '';
				return;
			}

			var self = this;
			var fragment = document.createDocumentFragment();

			// Previous button.
			var prevBtn = document.createElement( 'button' );
			prevBtn.className = 'wff-page-btn';
			prevBtn.textContent = '\u2039';
			prevBtn.disabled = pagination.current_page <= 1;
			prevBtn.addEventListener( 'click', function () {
				self.goToPage( pagination.current_page - 1 );
			} );
			fragment.appendChild( prevBtn );

			// Page numbers (show max 5).
			var startPage = Math.max( 1, pagination.current_page - 2 );
			var endPage = Math.min( pagination.total_pages, startPage + 4 );
			startPage = Math.max( 1, endPage - 4 );

			for ( var i = startPage; i <= endPage; i++ ) {
				( function ( page ) {
					var btn = document.createElement( 'button' );
					btn.className = 'wff-page-btn';
					if ( page === pagination.current_page ) {
						btn.classList.add( 'is-active' );
					}
					btn.textContent = page;
					btn.addEventListener( 'click', function () {
						self.goToPage( page );
					} );
					fragment.appendChild( btn );
				} )( i );
			}

			// Next button.
			var nextBtn = document.createElement( 'button' );
			nextBtn.className = 'wff-page-btn';
			nextBtn.textContent = '\u203A';
			nextBtn.disabled = pagination.current_page >= pagination.total_pages;
			nextBtn.addEventListener( 'click', function () {
				self.goToPage( pagination.current_page + 1 );
			} );
			fragment.appendChild( nextBtn );

			container.innerHTML = '';
			container.appendChild( fragment );
		},

		/**
		 * Navigate to a specific page.
		 */
		goToPage: function ( page ) {
			this.currentPage = page;
			this.fetchProducts();

			// Scroll to top of results.
			if ( this.dom.grid ) {
				this.dom.grid.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		},

		/**
		 * Update results count display.
		 */
		updateResultsCount: function ( total ) {
			if ( this.dom.resultsCount ) {
				this.dom.resultsCount.textContent =
					total + ' ' + ( total === 1 ? 'product' : 'products' );
			}
		},

		/**
		 * Update active filter tags display.
		 */
		updateActiveFilters: function () {
			if ( ! this.showActive || ! this.dom.activeFilters || ! this.dom.activeTags ) {
				return;
			}

			var self = this;
			var tags = [];

			// Checked checkboxes.
			var checked = this.wrapper.querySelectorAll( '.wff-checkbox:checked' );
			for ( var i = 0; i < checked.length; i++ ) {
				var label = checked[ i ]
					.closest( '.wff-checkbox-label' )
					.querySelector( '.wff-checkbox-text' );
				if ( label ) {
					tags.push( {
						text: label.textContent,
						input: checked[ i ],
					} );
				}
			}

			// Price range (only if modified).
			if ( this.dom.priceMin && this.dom.priceMax ) {
				var minDefault = parseInt( this.dom.priceMin.getAttribute( 'min' ), 10 );
				var maxDefault = parseInt( this.dom.priceMax.getAttribute( 'max' ), 10 );
				var minVal = parseInt( this.dom.priceMin.value, 10 );
				var maxVal = parseInt( this.dom.priceMax.value, 10 );

				if ( minVal > minDefault || maxVal < maxDefault ) {
					tags.push( {
						text:
							( self.config.currency ? self.config.currency.symbol : '$' ) +
							minVal +
							' \u2013 ' +
							( self.config.currency ? self.config.currency.symbol : '$' ) +
							maxVal,
						type: 'price',
					} );
				}
			}

			// Show or hide container.
			this.dom.activeFilters.hidden = tags.length === 0;

			// Render tags.
			var fragment = document.createDocumentFragment();
			for ( var j = 0; j < tags.length; j++ ) {
				( function ( tag ) {
					var el = document.createElement( 'span' );
					el.className = 'wff-tag';
					el.textContent = tag.text;

					var remove = document.createElement( 'button' );
					remove.className = 'wff-tag-remove';
					remove.textContent = '\u00d7';
					remove.setAttribute( 'aria-label', 'Remove filter: ' + tag.text );
					remove.addEventListener( 'click', function () {
						if ( tag.input ) {
							tag.input.checked = false;
							tag.input.dispatchEvent(
								new Event( 'change', { bubbles: true } )
							);
						} else if ( tag.type === 'price' ) {
							self.resetPrice();
						}
						if ( self.autoApply ) {
							self.currentPage = 1;
							self.fetchProducts();
						}
						self.updateActiveFilters();
					} );

					el.appendChild( remove );
					fragment.appendChild( el );
				} )( tags[ j ] );
			}

			this.dom.activeTags.innerHTML = '';
			this.dom.activeTags.appendChild( fragment );
		},

		/**
		 * Clear all filters.
		 */
		clearFilters: function () {
			// Uncheck all checkboxes.
			var checked = this.wrapper.querySelectorAll( '.wff-checkbox:checked' );
			for ( var i = 0; i < checked.length; i++ ) {
				checked[ i ].checked = false;
			}

			// Reset price.
			this.resetPrice();

			// Reset sort.
			if ( this.dom.sortSelect ) {
				this.dom.sortSelect.value = 'menu_order';
			}

			this.currentPage = 1;
			this.updateActiveFilters();
			this.fetchProducts();
		},

		/**
		 * Reset price inputs to defaults.
		 */
		resetPrice: function () {
			if ( this.dom.priceMin ) {
				this.dom.priceMin.value = this.dom.priceMin.getAttribute( 'min' );
			}
			if ( this.dom.priceMax ) {
				this.dom.priceMax.value = this.dom.priceMax.getAttribute( 'max' );
			}
			if ( this.dom.rangeMin ) {
				this.dom.rangeMin.value = this.dom.rangeMin.getAttribute( 'min' );
			}
			if ( this.dom.rangeMax ) {
				this.dom.rangeMax.value = this.dom.rangeMax.getAttribute( 'max' );
			}
		},

		/**
		 * Escape HTML for safe insertion.
		 */
		escHtml: function ( str ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML;
		},

		/**
		 * Escape attribute value.
		 */
		escAttr: function ( str ) {
			if ( ! str ) return '';
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#39;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' );
		},
	};

	/**
	 * Initialize all filter instances on the page.
	 * Uses DOMContentLoaded for earliest possible initialization.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var wrappers = document.querySelectorAll( '.wff-wrapper' );
		for ( var i = 0; i < wrappers.length; i++ ) {
			new WFFController( wrappers[ i ] );
		}
	} );
} )();
