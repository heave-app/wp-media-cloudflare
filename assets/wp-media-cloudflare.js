((_, $, wp) => {
    document.querySelectorAll('#wp-media-cloudflare-meta-box button').forEach(button => {
        button.addEventListener('click', () => {
            const name = button.attributes.name.value;
            const value = button.value;
            const textContent = button.textContent;
            button.textContent = 'Saving...';
            
            jQuery.ajax({
                type: 'post',
                data: {
                    action: 'cloudflare_action',
                    name,
                    value
                },
                url: ajaxurl,
                dataType: 'json',
                success: (response) => {
                    console.log(response)
    
                    if (response.success) {
                        button.textContent = textContent;
                    }
                },
                error: (response) => {
                    button.textContent = textContent;
                }
            });
        });
    });


    let mediaViewAttachmentDetailsTwoColumn = wp.media.view.Attachment.Details.TwoColumn;
    wp.media.view.Attachment.Details.TwoColumn = mediaViewAttachmentDetailsTwoColumn.extend({
        initialize: function(){
            // Always make sure that our content is up to date.
            this.model.on('change', this.render, this);
        },
        events: function() {
            return _.extend({}, mediaViewAttachmentDetailsTwoColumn.prototype.events, {
                'click .local-warning': 'confirmS3Removal',
                'click #as3cfpro-toggle-acl': 'toggleACL'
            });
        },
        render: function() {
            mediaViewAttachmentDetailsTwoColumn.prototype.render.apply(this);
            this.renderActionLinks();

            return this;
        },

        renderActionLinks: function () {
            $('.attachment-info').append('<div><h2>Hello</h2></div>')
        }
    });

    let wpMediaView = wp.media.View;

    wp.media.View = wpMediaView.extend({
        render: function() {
            wpMediaView.prototype.render.apply(this); 
            this.views.detach();
            this.views.render();
        },

        addCloudIcon: function () {
            setTimeout(() => {
              
            }, 1000);
        }
    });


    let CloudflareButton = wp.media.view.Button;

    UploadSelectedButton = CloudflareButton.extend(/** @lends wp.media.view.DeleteSelectedButton.prototype */{
        initialize: function() {
            console.log('b')
            Button.prototype.initialize.apply( this, arguments );
            if ( this.options.filters ) {
                this.options.filters.model.on( 'change', this.filterChange, this );
            }
            this.controller.on( 'selection:toggle', this.toggleDisabled, this );
            this.controller.on( 'select:activate', this.toggleDisabled, this );
        },
    
        filterChange: function( model ) {
            if ( 'trash' === model.get( 'status' ) ) {
                this.model.set( 'text', l10n.restoreSelected );
            } else if ( wp.media.view.settings.mediaTrash ) {
                this.model.set( 'text', l10n.trashSelected );
            } else {
                this.model.set( 'text', l10n.deletePermanently );
            }
        },
    
        toggleDisabled: function() {
            this.model.set( 'disabled', ! this.controller.state().get( 'selection' ).length );
        },
    
        render: function() {
            Button.prototype.render.apply( this, arguments );
            if ( this.controller.isModeActive( 'select' ) ) {
                this.$el.addClass( 'delete-selected-button' );
            } else {
                this.$el.addClass( 'delete-selected-button hidden' );
            }
            this.toggleDisabled();
            return this;
        }
    });


    let wpMediaToolbar = wp.media.view.Toolbar;
    wp.media.view.Toolbar = wpMediaToolbar.extend({
        createToolbar: function() {
            console.log('here')
            wpMediaToolbar.prototype.createToolbar.apply(this);

            this.toolbar.set( 'uploadSelectedButton', new wp.media.view.UploadSelectedButton({
				filters: Filters,
				style: 'primary',
				disabled: true,
				text: 'UploadSelectedButton',
				controller: this.controller,
				priority: -20,
				click: function() {
					var changed = [], removed = [],
						selection = this.controller.state().get( 'selection' ),
						library = this.controller.state().get( 'library' );

					if ( ! selection.length ) {
						return;
					}

					if ( ! mediaTrash && ! window.confirm( l10n.warnBulkDelete ) ) {
						return;
					}

					if ( mediaTrash &&
						'trash' !== selection.at( 0 ).get( 'status' ) &&
						! window.confirm( l10n.warnBulkTrash ) ) {

						return;
					}

					selection.each( function( model ) {
						if ( ! model.get( 'nonces' )['delete'] ) {
							removed.push( model );
							return;
						}

						if ( mediaTrash && 'trash' === model.get( 'status' ) ) {
							model.set( 'status', 'inherit' );
							changed.push( model.save() );
							removed.push( model );
						} else if ( mediaTrash ) {
							model.set( 'status', 'trash' );
							changed.push( model.save() );
							removed.push( model );
						} else {
							model.destroy({wait: true});
						}
					} );

					if ( changed.length ) {
						selection.remove( removed );

						$.when.apply( null, changed ).then( _.bind( function() {
							library._requery( true );
							this.controller.trigger( 'selection:action:done' );
						}, this ) );
					} else {
						this.controller.trigger( 'selection:action:done' );
					}
				}
			}).render() );
        }
    });


})(window._, window.jQuery, wp);

