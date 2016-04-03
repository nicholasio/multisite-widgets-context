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
            init();
        });

    } );

} ( this, jQuery ) );