(function( $ ) {
    // I like $
    $( document ).ready(function () {
        /**
         * The first click of the submit button expands the rest of the form. We then deregister the
         * event handler so that the preceding click submits the form.
         *
         * @param e the event fired
         */
        function expandShareForm(e) {
            e.preventDefault();
            var form = e.target;
            $(form).addClass('completed');
            $(form).off('submit');
            $(form).children('.hideable').toggleClass('hidden');

            // as well as toggling the visibility of child elements, if this is an extend form we
            // want to toggle the sibling form for deleting a share
            $(form).siblings('form[id^=dff]').children('.hideable').toggleClass('hidden');
        }

        /**
         * Register the 'submit' event handler to prevent the form being posted
         *
         * @see expandShareForm
         */
        function registerOnSubmitExpand(form) {
            $(form).not('.completed').on('submit', expandShareForm);
        }

        /**
         * if the "Cancel" link is pressed we need to hide the form and re-register the onSubmit
         * handler so that the form can't be submitted.
         */
        function registerOnClickCancel() {
            $('form[id^=dff] #cancel').on('click', function(e) {
                var form = $(e.target).parent('form[id^=dff]');

                // hide the expires/unit inputs
                form.children('.hideable').toggleClass('hidden');

                // toggle hidden elements from sibling forms (i.e. "Stop Sharing" button)
                form.siblings('form[id^=dff]').children('.hideable').toggleClass('hidden');

                // the form hasn't been completed, remove the class and reregister the onSubmit
                // handler
                form.removeClass('completed');
                registerOnSubmitExpand(form);
            });
        }

        // on document-ready, register the event handlers

        // "Share Draft" button
        registerOnSubmitExpand($('form[id^=dff-share-draft]'));

        // "Extend" button
        registerOnSubmitExpand($('form[id^=dff-extend-share]'));

        // "Cancel" button
        registerOnClickCancel();

    });
})( jQuery );