( function( window, $, undefined ){

    /**
     * Init our plugin JS code
     */
    var init = function() {
        var $widget_context_btn = $( '.multisite-widget-context-select .button' );

        $widget_context_btn.on( 'click', function( e ) {
            e.preventDefault();
            var $this = $( this );
            $this.parent().siblings().show();
            $this.parent().remove();
        } );
    };

    $( document ).ready( function() {
        init();

        /**
         * After saving a widget, we need init our js code again
         */
        $( document ).on( 'widget-updated' , function( event, $widget ) {
            var siteName    = $widget.find( '.wpmulwc-form select option:selected' ).text();
            var $widget_h3  = $widget.find( '.widget-top .widget-title h3' );
            var widget_name = $widget.find( '.multisite-widget-context-select' ).data( 'widget-name' );

            $widget_h3.html( widget_name + ' (' + siteName + ')' );

            init();
        });

    } );

} ( this, jQuery ) );